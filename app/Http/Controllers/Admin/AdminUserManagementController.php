<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminUserCleanupService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Throwable;

class AdminUserManagementController extends Controller
{
    public function __construct(
        private readonly AdminUserCleanupService $cleanupService
    ) {
        $this->middleware(['auth:sanctum', 'role:admin']);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'expiring_soon', 'expired'])],
            'expiring_within_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'sort_by' => ['nullable', Rule::in(['name', 'email', 'created_at', 'updated_at', 'domain_expires_at'])],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $expiringWithinDays = (int) ($validated['expiring_within_days'] ?? 7);
        $statusFilter = $validated['status'] ?? null;
        $search = $validated['search'] ?? null;
        $sortBy = $validated['sort_by'] ?? 'updated_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = User::query()
            ->whereDoesntHave('roles', function (Builder $builder) {
                $builder->where('name', 'admin');
            })
            ->with([
                'settingOne:id,user_id,domain',
                'invitationOne:id,user_id,paket_undangan_id,domain_expires_at,payment_confirmed_at,created_at,package_features_snapshot',
                'invitationOne.paketUndangan:id,name_paket,jenis_paket',
            ])
            ->withCount([
                'gallery',
                'bukuTamu',
                'weddingGuests',
                'ucapan',
            ]);

        if ($search) {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('settingOne', function (Builder $settingQuery) use ($search) {
                        $settingQuery->where('domain', 'like', "%{$search}%");
                    });
            });
        }

        $users = $query->get();

        $mappedUsers = $users->map(function (User $user) use ($expiringWithinDays) {
            $invitation = $user->invitationOne;
            $expiryAt = $invitation?->domain_expires_at;
            $startAt = $invitation?->payment_confirmed_at ?? $invitation?->created_at;
            $daysRemaining = $expiryAt ? Carbon::now()->diffInDays($expiryAt, false) : null;

            $status = 'active';
            if ($expiryAt && Carbon::now()->gte($expiryAt)) {
                $status = 'expired';
            } elseif ($expiryAt && $daysRemaining !== null && $daysRemaining >= 0 && $daysRemaining <= $expiringWithinDays) {
                $status = 'expiring_soon';
            }

            $packageName = $invitation?->package_features_snapshot['name_paket']
                ?? $invitation?->paketUndangan?->name_paket
                ?? $invitation?->paketUndangan?->jenis_paket
                ?? null;

            return [
                'user_id' => $user->id,
                'nama' => $user->name,
                'email' => $user->email,
                'domain' => $user->settingOne?->domain,
                'nama_paket' => $packageName,
                'status_akun' => $status,
                'tanggal_mulai' => optional($startAt)?->toDateString(),
                'tanggal_expired' => optional($expiryAt)?->toDateString(),
                'sisa_hari' => $daysRemaining,
                'jumlah_media' => (int) $user->gallery_count,
                'jumlah_buku_tamu' => (int) $user->buku_tamu_count,
                'jumlah_guest' => (int) $user->wedding_guests_count,
                'jumlah_ucapan' => (int) $user->ucapan_count,
                'updated_at' => optional($user->updated_at)?->toISOString(),
                'created_at' => optional($user->created_at)?->toISOString(),
                'sort_domain_expires_at' => optional($expiryAt)?->toISOString(),
            ];
        });

        if ($statusFilter) {
            $mappedUsers = $mappedUsers->where('status_akun', $statusFilter)->values();
        }

        $sortField = $sortBy === 'domain_expires_at' ? 'sort_domain_expires_at' : $sortBy;
        $mappedUsers = $mappedUsers
            ->sortBy($sortField, SORT_REGULAR, $sortOrder === 'desc')
            ->values();

        $currentPage = (int) ($validated['page'] ?? 1);
        $total = $mappedUsers->count();
        $items = $mappedUsers->slice(($currentPage - 1) * $perPage, $perPage)->values()->all();

        foreach ($items as &$item) {
            unset($item['sort_domain_expires_at']);
        }
        unset($item);

        return response()->json([
            'status' => 200,
            'message' => 'Data pengguna berhasil diambil.',
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $currentPage,
                'last_page' => (int) ceil(max($total, 1) / $perPage),
            ],
            'filters' => [
                'status' => $statusFilter,
                'expiring_within_days' => $expiringWithinDays,
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    public function softDelete(int $userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'status' => 404,
                'message' => 'Pengguna tidak ditemukan.',
            ], 404);
        }

        if ($user->hasRole('admin')) {
            return response()->json([
                'status' => 422,
                'message' => 'Data admin tidak dapat dibersihkan melalui endpoint ini.',
            ], 422);
        }

        try {
            $summary = $this->cleanupService->softDeleteUserData($user);

            return response()->json([
                'status' => 200,
                'message' => 'Data pengguna berhasil dibersihkan.',
                'data' => $summary,
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 500,
                'message' => 'Gagal menghapus data pengguna.',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    public function hardDelete(int $userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'status' => 404,
                'message' => 'Pengguna tidak ditemukan.',
            ], 404);
        }

        if ($user->hasRole('admin')) {
            return response()->json([
                'status' => 422,
                'message' => 'Akun admin tidak dapat dihapus melalui endpoint ini.',
            ], 422);
        }

        try {
            $summary = $this->cleanupService->hardDeleteUser($user);

            return response()->json([
                'status' => 200,
                'message' => 'Akun pengguna beserta seluruh data berhasil dihapus.',
                'data' => $summary,
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 500,
                'message' => 'Gagal menghapus data pengguna.',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }
}
