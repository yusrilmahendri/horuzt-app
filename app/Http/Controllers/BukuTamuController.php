<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreBukuTamuRequest;
use App\Http\Resources\Bukutamu\BukuTamuCollection;
use App\Http\Resources\Bukutamu\BukuTamuResource;
use App\Models\BukuTamu;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BukuTamuController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['store', 'publicIndex', 'publicStatistics']);
    }

    public function index(Request $request): BukuTamuCollection
    {
        $user = auth()->user();
        $limit = (int) $request->get('limit', 15);
        $status = $request->get('status');
        $search = $request->get('search');
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $query = BukuTamu::forUser($user->id)
            ->search($search);

        if ($status && in_array($status, ['hadir', 'tidak_hadir', 'ragu'])) {
            $query->byStatus($status);
        }

        $allowedSorts = ['created_at', 'nama', 'status_kehadiran'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $data = $query->paginate($limit);

        return new BukuTamuCollection($data);
    }

    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        $bukuTamu = BukuTamu::forUser($user->id)->find($id);

        if (!$bukuTamu) {
            return response()->json([
                'status' => 404,
                'message' => 'Data buku tamu tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Detail buku tamu berhasil diambil.',
            'data' => new BukuTamuResource($bukuTamu),
        ]);
    }

    public function store(StoreBukuTamuRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::find($validated['user_id']);
        if (!$user) {
            return response()->json([
                'status' => 404,
                'message' => 'Undangan tidak ditemukan.',
            ], 404);
        }

        try {
            $bukuTamu = BukuTamu::create([
                'user_id' => $validated['user_id'],
                'nama' => $validated['nama'],
                'email' => $validated['email'] ?? null,
                'telepon' => $validated['telepon'] ?? null,
                'ucapan' => $validated['ucapan'] ?? null,
                'status_kehadiran' => $validated['status_kehadiran'],
                'jumlah_tamu' => $validated['jumlah_tamu'] ?? 1,
                'is_approved' => true,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            Log::info('Buku tamu entry created', [
                'id' => $bukuTamu->id,
                'user_id' => $bukuTamu->user_id,
                'nama' => $bukuTamu->nama,
            ]);

            return response()->json([
                'status' => 201,
                'message' => 'Ucapan dan konfirmasi kehadiran berhasil disimpan.',
                'data' => new BukuTamuResource($bukuTamu),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create buku tamu entry', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal menyimpan data. Silakan coba lagi.',
            ], 500);
        }
    }

    public function publicIndex(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');
        $domain = $request->query('domain');

        if (!$userId && $domain) {
            $user = User::whereHas('settingOne', function ($q) use ($domain) {
                $q->where('token', $domain);
            })->first();
            $userId = $user?->id;
        }

        if (!$userId) {
            return response()->json([
                'status' => 400,
                'message' => 'Parameter user_id atau domain wajib diisi.',
            ], 400);
        }

        $limit = (int) $request->get('limit', 50);
        $status = $request->get('status');

        $query = BukuTamu::forUser((int) $userId)
            ->approved()
            ->orderBy('created_at', 'desc');

        if ($status && in_array($status, ['hadir', 'tidak_hadir', 'ragu'])) {
            $query->byStatus($status);
        }

        $data = $query->paginate($limit);

        return response()->json([
            'status' => 200,
            'message' => 'Data buku tamu berhasil diambil.',
            'data' => BukuTamuResource::collection($data),
            'pagination' => [
                'total' => $data->total(),
                'per_page' => $data->perPage(),
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
            ],
        ]);
    }

    public function publicStatistics(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');
        $domain = $request->query('domain');

        if (!$userId && $domain) {
            $user = User::whereHas('settingOne', function ($q) use ($domain) {
                $q->where('token', $domain);
            })->first();
            $userId = $user?->id;
        }

        if (!$userId) {
            return response()->json([
                'status' => 400,
                'message' => 'Parameter user_id atau domain wajib diisi.',
            ], 400);
        }

        $baseQuery = BukuTamu::forUser((int) $userId)->approved();

        $statistics = [
            'total_entries' => (clone $baseQuery)->count(),
            'total_hadir' => (clone $baseQuery)->hadir()->count(),
            'total_tidak_hadir' => (clone $baseQuery)->tidakHadir()->count(),
            'total_ragu' => (clone $baseQuery)->ragu()->count(),
            'total_tamu_hadir' => (int) (clone $baseQuery)->hadir()->sum('jumlah_tamu'),
        ];

        $total = $statistics['total_entries'];
        $statistics['percentage_hadir'] = $total > 0 ? round(($statistics['total_hadir'] / $total) * 100, 1) : 0;
        $statistics['percentage_tidak_hadir'] = $total > 0 ? round(($statistics['total_tidak_hadir'] / $total) * 100, 1) : 0;
        $statistics['percentage_ragu'] = $total > 0 ? round(($statistics['total_ragu'] / $total) * 100, 1) : 0;

        return response()->json([
            'status' => 200,
            'message' => 'Statistik buku tamu berhasil diambil.',
            'data' => $statistics,
        ]);
    }

    public function statistics(): JsonResponse
    {
        $user = auth()->user();
        $baseQuery = BukuTamu::forUser($user->id);

        $statistics = [
            'total_entries' => (clone $baseQuery)->count(),
            'total_hadir' => (clone $baseQuery)->hadir()->count(),
            'total_tidak_hadir' => (clone $baseQuery)->tidakHadir()->count(),
            'total_ragu' => (clone $baseQuery)->ragu()->count(),
            'total_tamu_hadir' => (int) (clone $baseQuery)->hadir()->sum('jumlah_tamu'),
            'today_entries' => (clone $baseQuery)->today()->count(),
            'approved_entries' => (clone $baseQuery)->approved()->count(),
            'pending_entries' => (clone $baseQuery)->pending()->count(),
        ];

        $total = $statistics['total_entries'];
        $statistics['percentage_hadir'] = $total > 0 ? round(($statistics['total_hadir'] / $total) * 100, 1) : 0;
        $statistics['percentage_tidak_hadir'] = $total > 0 ? round(($statistics['total_tidak_hadir'] / $total) * 100, 1) : 0;
        $statistics['percentage_ragu'] = $total > 0 ? round(($statistics['total_ragu'] / $total) * 100, 1) : 0;

        return response()->json([
            'status' => 200,
            'message' => 'Statistik buku tamu berhasil diambil.',
            'data' => $statistics,
        ]);
    }

    public function updateApproval(int $id, Request $request): JsonResponse
    {
        $user = auth()->user();
        $bukuTamu = BukuTamu::forUser($user->id)->find($id);

        if (!$bukuTamu) {
            return response()->json([
                'status' => 404,
                'message' => 'Data buku tamu tidak ditemukan.',
            ], 404);
        }

        $request->validate([
            'is_approved' => ['required', 'boolean'],
        ]);

        $bukuTamu->update(['is_approved' => $request->boolean('is_approved')]);

        return response()->json([
            'status' => 200,
            'message' => $request->boolean('is_approved') 
                ? 'Ucapan berhasil disetujui.' 
                : 'Ucapan berhasil disembunyikan.',
            'data' => new BukuTamuResource($bukuTamu),
        ]);
    }

    public function bulkUpdateApproval(Request $request): JsonResponse
    {
        $user = auth()->user();

        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:buku_tamus,id'],
            'is_approved' => ['required', 'boolean'],
        ]);

        $updated = BukuTamu::forUser($user->id)
            ->whereIn('id', $request->input('ids'))
            ->update(['is_approved' => $request->boolean('is_approved')]);

        return response()->json([
            'status' => 200,
            'message' => "{$updated} data berhasil diperbarui.",
            'data' => ['updated_count' => $updated],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = auth()->user();
        $bukuTamu = BukuTamu::forUser($user->id)->find($id);

        if (!$bukuTamu) {
            return response()->json([
                'status' => 404,
                'message' => 'Data buku tamu tidak ditemukan.',
            ], 404);
        }

        $bukuTamu->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Data buku tamu berhasil dihapus.',
        ]);
    }

    public function deleteAll(): JsonResponse
    {
        $user = auth()->user();
        $deleted = BukuTamu::forUser($user->id)->delete();

        return response()->json([
            'status' => 200,
            'message' => "Semua data buku tamu berhasil dihapus ({$deleted} data).",
            'data' => ['deleted_count' => $deleted],
        ]);
    }

    public function deleteById(int $id): JsonResponse
    {
        return $this->destroy($id);
    }

    public function export(Request $request): JsonResponse
    {
        $user = auth()->user();
        $format = $request->get('format', 'json');

        $data = BukuTamu::forUser($user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($format === 'csv') {
            $csv = "No,Nama,Email,Telepon,Ucapan,Status Kehadiran,Jumlah Tamu,Tanggal\n";
            foreach ($data as $index => $item) {
                $csv .= implode(',', [
                    $index + 1,
                    '"' . str_replace('"', '""', $item->nama) . '"',
                    $item->email ?? '-',
                    $item->telepon ?? '-',
                    '"' . str_replace('"', '""', $item->ucapan ?? '-') . '"',
                    $item->status_kehadiran,
                    $item->jumlah_tamu,
                    $item->created_at->format('Y-m-d H:i:s'),
                ]) . "\n";
            }

            return response()->json([
                'status' => 200,
                'message' => 'Data buku tamu berhasil diekspor.',
                'data' => [
                    'content' => base64_encode($csv),
                    'filename' => 'buku_tamu_' . now()->format('Y-m-d') . '.csv',
                    'mime_type' => 'text/csv',
                ],
            ]);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Data buku tamu berhasil diekspor.',
            'data' => BukuTamuResource::collection($data),
        ]);
    }
}
