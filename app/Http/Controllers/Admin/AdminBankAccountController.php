<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Rekening\RekeningCollection;
use App\Http\Resources\Rekening\RekeningResource;
use App\Models\Bank;
use App\Models\Rekening;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBankAccountController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:admin']);
    }

    /**
     * Get all bank accounts with optional filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Rekening::with(['bank', 'user:id,name,email']);

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by bank
        if ($request->filled('kode_bank')) {
            $query->where('kode_bank', $request->kode_bank);
        }

        // Search by account number or owner name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_rekening', 'like', "%{$search}%")
                  ->orWhere('nama_pemilik', 'like', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $bankAccounts = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => new RekeningCollection($bankAccounts),
            'meta' => [
                'current_page' => $bankAccounts->currentPage(),
                'last_page' => $bankAccounts->lastPage(),
                'per_page' => $bankAccounts->perPage(),
                'total' => $bankAccounts->total(),
            ]
        ]);
    }

    /**
     * Show specific bank account details
     */
    public function show(int $id): JsonResponse
    {
        $bankAccount = Rekening::with(['bank', 'user:id,name,email'])->find($id);

        if (!$bankAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Rekening bank tidak ditemukan.',
                'error_code' => 'ACCOUNT_NOT_FOUND'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new RekeningResource($bankAccount)
        ]);
    }

    /**
     * Delete specific bank account (admin only)
     */
    public function destroy(int $id): JsonResponse
    {
        $bankAccount = Rekening::find($id);

        if (!$bankAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Rekening bank tidak ditemukan.',
                'error_code' => 'ACCOUNT_NOT_FOUND'
            ], 404);
        }

        // Delete associated photo
        if ($bankAccount->photo_rek) {
            \Storage::disk('public')->delete($bankAccount->photo_rek);
        }

        $bankAccount->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rekening bank berhasil dihapus.'
        ]);
    }

    /**
     * Get bank account statistics for admin dashboard
     */
    public function statistics(): JsonResponse
    {
        $totalAccounts = Rekening::count();
        $accountsWithLogo = Rekening::whereNotNull('photo_rek')->count();
        $uniqueUsers = Rekening::distinct('user_id')->count();
        $usersWithMaxAccounts = Rekening::selectRaw('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= 2')
            ->get()
            ->count();

        // Top banks by account count
        $topBanks = Rekening::select('kode_bank', 'nama_bank')
            ->selectRaw('COUNT(*) as account_count')
            ->groupBy('kode_bank', 'nama_bank')
            ->orderByDesc('account_count')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_accounts' => $totalAccounts,
                'accounts_with_logo' => $accountsWithLogo,
                'unique_users' => $uniqueUsers,
                'users_with_max_accounts' => $usersWithMaxAccounts,
                'top_banks' => $topBanks
            ]
        ]);
    }

    /**
     * Get users and their bank account counts
     */
    public function userAccounts(): JsonResponse
    {
        $users = User::select('id', 'name', 'email')
            ->withCount('rekenings')
            ->having('rekenings_count', '>', 0)
            ->orderByDesc('rekenings_count')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }
}