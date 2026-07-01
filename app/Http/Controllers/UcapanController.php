<?php

namespace App\Http\Controllers;

use App\Http\Resources\Ucapan\UcapanCollection;
use App\Http\Resources\Ucapan\UcapanResource;
use App\Models\Invitation;
use App\Models\Setting;
use App\Models\Ucapan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class UcapanController extends Controller
{
    /**
     * Display a listing of all ucapan (public endpoint for guests)
     */
    public function publicIndex(): JsonResponse
    {
        try {
            $ucapans = Ucapan::orderBy('created_at', 'desc')->get();

            return response()->json(new UcapanCollection($ucapans), 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve ucapan data.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display a listing of ucapan for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $context = $this->resolveUserUcapanContext($request);
            $user = $context['user'];
            if (! $user) {
                return response()->json([
                    'message' => 'Unauthenticated.'
                ], 401);
            }
            $resolvedDomain = $context['domain'];
            $invitationIds = $context['invitation_ids'];

            $query = Ucapan::query()
                ->where('user_id', $user->id);

            if ($request->filled('search')) {
                $search = trim((string) $request->query('search'));
                $query->where(function ($builder) use ($search) {
                    $builder->where('nama', 'like', "%{$search}%")
                        ->orWhere('pesan', 'like', "%{$search}%");
                });
            }

            $kehadiranFilter = $request->query('kehadiran')
                ?? $request->query('status')
                ?? $request->query('status_kehadiran');
            if (is_string($kehadiranFilter) && in_array($kehadiranFilter, ['hadir', 'tidak_hadir', 'mungkin'], true)) {
                $query->where('kehadiran', $kehadiranFilter);
            }

            $query->orderBy('created_at', 'desc');

            $offset = $request->query('offset');
            if (is_numeric($offset) && (int) $offset > 0) {
                $query->offset((int) $offset);
            }

            $limit = $request->query('limit');
            if (is_numeric($limit) && (int) $limit > 0) {
                $query->limit((int) $limit);
            }

            $ucapans = $query->get();

            Log::info('[UserUcapanScope]', [
                'auth_user_id' => $user->id,
                'domain' => $resolvedDomain,
                'invitation_ids' => $invitationIds,
                'total_result' => $ucapans->count(),
            ]);

            return response()->json(new UcapanCollection($ucapans), 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve ucapan data.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created ucapan (public endpoint)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'domain' => 'required|string|max:255',
                'nama' => 'required|string|max:255',
                'kehadiran' => 'required|in:hadir,tidak_hadir,mungkin',
                'pesan' => 'required|string|max:1000',
            ], [
                'domain.required' => 'Domain undangan wajib diisi.',
                'nama.required' => 'Nama wajib diisi.',
                'nama.max' => 'Nama tidak boleh lebih dari 255 karakter.',
                'kehadiran.required' => 'Status kehadiran wajib dipilih.',
                'kehadiran.in' => 'Status kehadiran tidak valid.',
                'pesan.required' => 'Ucapan wajib diisi.',
                'pesan.max' => 'Ucapan tidak boleh lebih dari 1000 karakter.',
            ]);

            $domain = trim((string) $validated['domain']);
            $ownerUserId = $this->resolveOwnerUserIdByDomain($domain);
            if ($ownerUserId === null) {
                return response()->json([
                    'message' => 'Undangan tidak ditemukan.',
                ], 404);
            }

            if (! User::query()->whereKey($ownerUserId)->exists()) {
                return response()->json([
                    'message' => 'Data pemilik undangan tidak valid.',
                ], 422);
            }

            $ucapan = Ucapan::create([
                'user_id' => $ownerUserId,
                'nama' => $validated['nama'],
                'kehadiran' => $validated['kehadiran'],
                'pesan' => $validated['pesan'],
            ]);

            Log::info('[PublicUcapanOwnerResolved]', [
                'domain' => $domain,
                'owner_user_id' => $ownerUserId,
                'payload_user_id' => $request->input('user_id'),
                'created_ucapan_id' => $ucapan->id ?? null,
            ]);

            return response()->json([
                'message' => 'Ucapan berhasil disimpan!',
                'data' => new UcapanResource($ucapan)
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Data yang dikirim tidak valid.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menyimpan ucapan.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified ucapan (public endpoint)
     */
    public function show(string $id): JsonResponse
    {
        try {
            $ucapan = Ucapan::findOrFail($id);

            return response()->json([
                'data' => new UcapanResource($ucapan)
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Ucapan tidak ditemukan.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data ucapan.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified ucapan (public endpoint)
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $context = $this->resolveUserUcapanContext($request);
            $user = $context['user'];
            if (! $user) {
                return response()->json([
                    'message' => 'Unauthenticated.'
                ], 401);
            }
            $resolvedDomain = $context['domain'];
            $invitationIds = $context['invitation_ids'];

            $ucapan = Ucapan::findOrFail($id);
            if ((int) $ucapan->user_id !== (int) $user->id) {
                Log::info('[UserUcapanScope]', [
                    'auth_user_id' => $user->id,
                    'domain' => $resolvedDomain,
                    'invitation_ids' => $invitationIds,
                    'total_result' => 0,
                ]);

                return response()->json([
                    'message' => 'Anda tidak memiliki akses untuk menghapus ucapan ini.'
                ], 403);
            }

            $ucapan->delete();

            Log::info('[UserUcapanScope]', [
                'auth_user_id' => $user->id,
                'domain' => $resolvedDomain,
                'invitation_ids' => $invitationIds,
                'total_result' => 1,
            ]);

            return response()->json([
                'message' => 'Ucapan berhasil dihapus.'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Ucapan tidak ditemukan.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menghapus ucapan.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get statistics about all ucapan responses (public endpoint)
     */
    public function publicStatistics(): JsonResponse
    {
        try {
            $stats = [
                'total_ucapan' => Ucapan::count(),
                'hadir' => Ucapan::where('kehadiran', 'hadir')->count(),
                'tidak_hadir' => Ucapan::where('kehadiran', 'tidak_hadir')->count(),
                'mungkin' => Ucapan::where('kehadiran', 'mungkin')->count(),
            ];

            return response()->json([
                'data' => $stats
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil statistik ucapan.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get statistics about ucapan responses for authenticated user
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $context = $this->resolveUserUcapanContext($request);
            $user = $context['user'];
            if (! $user) {
                return response()->json([
                    'message' => 'Unauthenticated.'
                ], 401);
            }
            $resolvedDomain = $context['domain'];
            $invitationIds = $context['invitation_ids'];

            $baseQuery = Ucapan::query()
                ->where('user_id', $user->id);

            $stats = [
                'total_ucapan' => (clone $baseQuery)->count(),
                'hadir' => (clone $baseQuery)->where('kehadiran', 'hadir')->count(),
                'tidak_hadir' => (clone $baseQuery)->where('kehadiran', 'tidak_hadir')->count(),
                'mungkin' => (clone $baseQuery)->where('kehadiran', 'mungkin')->count(),
            ];

            Log::info('[UserUcapanScope]', [
                'auth_user_id' => $user->id,
                'domain' => $resolvedDomain,
                'invitation_ids' => $invitationIds,
                'total_result' => $stats['total_ucapan'],
            ]);

            return response()->json([
                'data' => $stats
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil statistik ucapan.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    private function resolveUserUcapanContext(Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            return [
                'user' => null,
                'domain' => null,
                'invitation_ids' => [],
            ];
        }

        $invitationIds = Invitation::query()
            ->where('user_id', $user->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $resolvedDomain = Setting::query()
            ->where('user_id', $user->id)
            ->value('domain');

        return [
            'user' => $user,
            'domain' => $resolvedDomain,
            'invitation_ids' => $invitationIds,
        ];
    }

    private function resolveOwnerUserIdByDomain(string $domain): ?int
    {
        if ($domain === '') {
            return null;
        }

        $setting = Setting::query()
            ->whereRaw('LOWER(domain) = ?', [strtolower($domain)])
            ->first();

        if (! $setting || ! $setting->user_id) {
            return null;
        }

        return (int) $setting->user_id;
    }
}
