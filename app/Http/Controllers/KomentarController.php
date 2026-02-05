<?php

namespace App\Http\Controllers;

use App\Http\Resources\Komentar\KomentarResource;
use App\Models\Invitation;
use App\Models\Komentar;
use App\Models\Mempelai;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class KomentarController extends Controller
{
    /**
     * Display komentars for a specific wedding domain (public endpoint)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'domain' => 'nullable|string|exists:settings,domain',
                'user_id' => 'nullable|integer|exists:users,id',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            // Require either domain or user_id
            if (empty($validated['domain']) && empty($validated['user_id'])) {
                return response()->json([
                    'message' => 'Domain atau user_id wajib diisi.',
                ], 422);
            }

            // Find invitation by user_id or domain
            if (! empty($validated['user_id'])) {
                $invitation = Invitation::where('user_id', $validated['user_id'])->firstOrFail();
            } else {
                $setting = Setting::where('domain', $validated['domain'])->firstOrFail();
                $invitation = Invitation::where('user_id', $setting->user_id)->firstOrFail();
            }

            // Get komentars with pagination
            $perPage = $validated['per_page'] ?? 20;
            $komentars = Komentar::where('invitation_id', $invitation->id)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'data' => KomentarResource::collection($komentars->items()),
                'meta' => [
                    'total' => $komentars->total(),
                    'current_page' => $komentars->currentPage(),
                    'per_page' => $komentars->perPage(),
                    'last_page' => $komentars->lastPage(),
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Wedding not found for this domain.',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Data yang dikirim tidak valid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve komentars.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a new komentar (public endpoint with rate limiting)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate input - support both domain and user_id
            $validated = $request->validate([
                'domain' => 'nullable|string|exists:settings,domain',
                'user_id' => 'nullable|integer|exists:users,id',
                'nama' => 'required|string|min:2|max:255',
                'komentar' => 'required|string|min:5|max:500',
            ], [
                'domain.exists' => 'Domain wedding tidak ditemukan.',
                'user_id.exists' => 'User ID tidak valid.',
                'nama.required' => 'Nama wajib diisi.',
                'nama.min' => 'Nama minimal 2 karakter.',
                'nama.max' => 'Nama maksimal 255 karakter.',
                'komentar.required' => 'Komentar wajib diisi.',
                'komentar.min' => 'Komentar minimal 5 karakter.',
                'komentar.max' => 'Komentar maksimal 500 karakter.',
            ]);

            // Require either domain or user_id
            if (empty($validated['domain']) && empty($validated['user_id'])) {
                return response()->json([
                    'message' => 'Domain atau user_id wajib diisi.',
                ], 422);
            }

            // Basic XSS protection - strip HTML tags
            $validated['nama'] = strip_tags($validated['nama']);
            $validated['komentar'] = strip_tags($validated['komentar']);

            // Find invitation by user_id (from localStorage) or domain
            if (! empty($validated['user_id'])) {
                // Use user_id from localStorage (frontend)
                $userId = $validated['user_id'];
                $invitation = Invitation::where('user_id', $userId)->firstOrFail();
            } else {
                // Use domain (legacy/alternative method)
                $setting = Setting::where('domain', $validated['domain'])->firstOrFail();
                $userId = $setting->user_id;
                $invitation = Invitation::where('user_id', $userId)->firstOrFail();
            }

            // Verify invitation is completed
            if ($invitation->status !== 'step3') {
                return response()->json([
                    'message' => 'Wedding invitation is not yet available for public viewing.',
                ], 403);
            }

            // Verify payment status
            $mempelai = Mempelai::where('user_id', $userId)->first();
            if (! $mempelai || $mempelai->kd_status !== 'SB') {
                return response()->json([
                    'message' => 'Wedding invitation is not active.',
                ], 403);
            }

            // Check domain expiry
            if ($invitation->domain_expires_at && $invitation->domain_expires_at->isPast()) {
                return response()->json([
                    'message' => 'This wedding invitation has expired. Comments are no longer accepted.',
                ], 403);
            }

            // Rate limiting check (10 per hour per IP)
            $ipAddress = $request->ip();
            $recentKomentarsCount = Komentar::recentByIp($ipAddress, 1)->count();

            if ($recentKomentarsCount >= 10) {
                return response()->json([
                    'message' => 'Too many comments submitted. Please try again later (limit: 10 per hour).',
                ], 429);
            }

            // Create komentar
            $komentar = Komentar::create([
                'invitation_id' => $invitation->id,
                'nama' => $validated['nama'],
                'komentar' => $validated['komentar'],
                'ip_address' => $ipAddress,
            ]);

            return response()->json([
                'message' => 'Komentar berhasil disimpan!',
                'data' => new KomentarResource($komentar),
            ], 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Wedding not found.',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Data yang dikirim tidak valid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menyimpan komentar.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get statistics about komentars for a specific wedding
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'domain' => 'nullable|string|exists:settings,domain',
                'user_id' => 'nullable|integer|exists:users,id',
            ]);

            // Require either domain or user_id
            if (empty($validated['domain']) && empty($validated['user_id'])) {
                return response()->json([
                    'message' => 'Domain atau user_id wajib diisi.',
                ], 422);
            }

            // Find invitation by user_id or domain
            if (! empty($validated['user_id'])) {
                $invitation = Invitation::where('user_id', $validated['user_id'])->firstOrFail();
                $domain = Setting::where('user_id', $validated['user_id'])->value('domain') ?? 'N/A';
            } else {
                $setting = Setting::where('domain', $validated['domain'])->firstOrFail();
                $invitation = Invitation::where('user_id', $setting->user_id)->firstOrFail();
                $domain = $validated['domain'];
            }

            $stats = [
                'domain' => $domain,
                'total_komentars' => Komentar::where('invitation_id', $invitation->id)->count(),
            ];

            return response()->json(['data' => $stats], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Wedding not found.',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Data yang dikirim tidak valid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil statistik komentar.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
