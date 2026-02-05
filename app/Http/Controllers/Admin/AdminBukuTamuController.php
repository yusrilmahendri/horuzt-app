<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Bukutamu\BukuTamuCollection;
use App\Http\Resources\Bukutamu\BukuTamuResource;
use App\Models\BukuTamu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBukuTamuController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:admin']);
    }

    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->get('limit', 15);
        $userId = $request->get('user_id');
        $status = $request->get('status');
        $isApproved = $request->get('is_approved');
        $search = $request->get('search');
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $query = BukuTamu::with('user:id,name,email');

        if ($userId) {
            $query->forUser((int) $userId);
        }

        if ($status && in_array($status, ['hadir', 'tidak_hadir', 'ragu'])) {
            $query->byStatus($status);
        }

        if ($isApproved !== null) {
            $query->where('is_approved', filter_var($isApproved, FILTER_VALIDATE_BOOLEAN));
        }

        if ($search) {
            $query->search($search);
        }

        $allowedSorts = ['created_at', 'nama', 'status_kehadiran', 'user_id'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
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
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $bukuTamu = BukuTamu::with('user:id,name,email')->find($id);

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

    public function updateApproval(int $id, Request $request): JsonResponse
    {
        $bukuTamu = BukuTamu::find($id);

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
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:buku_tamus,id'],
            'is_approved' => ['required', 'boolean'],
        ]);

        $updated = BukuTamu::whereIn('id', $request->input('ids'))
            ->update(['is_approved' => $request->boolean('is_approved')]);

        return response()->json([
            'status' => 200,
            'message' => "{$updated} data berhasil diperbarui.",
            'data' => ['updated_count' => $updated],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $bukuTamu = BukuTamu::find($id);

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

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:buku_tamus,id'],
        ]);

        $deleted = BukuTamu::whereIn('id', $request->input('ids'))->delete();

        return response()->json([
            'status' => 200,
            'message' => "{$deleted} data berhasil dihapus.",
            'data' => ['deleted_count' => $deleted],
        ]);
    }

    public function deleteByUser(int $userId): JsonResponse
    {
        $deleted = BukuTamu::forUser($userId)->delete();

        return response()->json([
            'status' => 200,
            'message' => "Semua buku tamu untuk user {$userId} berhasil dihapus ({$deleted} data).",
            'data' => ['deleted_count' => $deleted],
        ]);
    }

    public function statistics(Request $request): JsonResponse
    {
        $userId = $request->get('user_id');

        $baseQuery = BukuTamu::query();
        
        if ($userId) {
            $baseQuery->forUser((int) $userId);
        }

        $totalEntries = (clone $baseQuery)->count();
        $totalHadir = (clone $baseQuery)->hadir()->count();
        $totalTidakHadir = (clone $baseQuery)->tidakHadir()->count();
        $totalRagu = (clone $baseQuery)->ragu()->count();
        $totalTamuHadir = (int) (clone $baseQuery)->hadir()->sum('jumlah_tamu');
        $todayEntries = (clone $baseQuery)->today()->count();
        $approvedEntries = (clone $baseQuery)->approved()->count();
        $pendingEntries = (clone $baseQuery)->pending()->count();

        $statistics = [
            'total_entries' => $totalEntries,
            'total_hadir' => $totalHadir,
            'total_tidak_hadir' => $totalTidakHadir,
            'total_ragu' => $totalRagu,
            'total_tamu_hadir' => $totalTamuHadir,
            'today_entries' => $todayEntries,
            'approved_entries' => $approvedEntries,
            'pending_entries' => $pendingEntries,
            'percentage_hadir' => $totalEntries > 0 ? round(($totalHadir / $totalEntries) * 100, 1) : 0,
            'percentage_tidak_hadir' => $totalEntries > 0 ? round(($totalTidakHadir / $totalEntries) * 100, 1) : 0,
            'percentage_ragu' => $totalEntries > 0 ? round(($totalRagu / $totalEntries) * 100, 1) : 0,
        ];

        if (!$userId) {
            $statistics['total_users_with_entries'] = BukuTamu::distinct('user_id')->count('user_id');
            $statistics['entries_per_user'] = DB::table('buku_tamus')
                ->select('user_id', DB::raw('COUNT(*) as total'))
                ->groupBy('user_id')
                ->orderByDesc('total')
                ->limit(10)
                ->get();
        }

        return response()->json([
            'status' => 200,
            'message' => 'Statistik buku tamu berhasil diambil.',
            'data' => $statistics,
        ]);
    }
}
