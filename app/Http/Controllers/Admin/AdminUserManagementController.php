<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\PaketUndangan;
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
                'invitationOne:id,user_id,paket_undangan_id,kode_pemesanan,status,payment_status,domain_expires_at,payment_confirmed_at,created_at,package_features_snapshot',
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
            $invitation = $this->invoiceForAdminUser($user) ?? $user->invitationOne;
            $expiryAt = $invitation?->domain_expires_at;
            $startAt = $invitation?->payment_confirmed_at ?? $invitation?->created_at;
            $daysRemaining = $expiryAt ? Carbon::now()->diffInDays($expiryAt, false) : null;
            $paymentStatus = $this->normalizePaymentStatus($invitation?->payment_status ?? $invitation?->status);
            $invoiceCode = $invitation?->kode_pemesanan ?? $user->kode_pemesanan;
            $hasInvoice = $invitation !== null;
            $canConfirm = $hasInvoice && $invoiceCode && $this->isConfirmablePaymentStatus($invitation?->payment_status, $invitation?->status);

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

            $packageCode = $invitation?->package_features_snapshot['code']
                ?? $invitation?->paketUndangan?->code
                ?? PaketUndangan::tierCode($packageName);

            return [
                'user_id' => $user->id,
                'nama' => $user->name,
                'email' => $user->email,
                'domain' => $user->settingOne?->domain,
                'nama_paket' => $packageName,
                'package_code' => $packageCode,
                'has_invoice' => $hasInvoice,
                'invoice_id' => $invitation?->id,
                'kode_pemesanan' => $invoiceCode,
                'invoice_code' => $invoiceCode,
                'status_pembayaran' => $paymentStatus,
                'payment_status' => $paymentStatus,
                'raw_payment_status' => $invitation?->payment_status,
                'can_confirm_payment' => (bool) $canConfirm,
                'status_akun' => $status,
                'tanggal_mulai' => optional($startAt)?->toDateString(),
                'tanggal_expired' => optional($expiryAt)?->toDateString(),
                'tanggal_mulai_formatted' => $this->formatDate($startAt),
                'tanggal_expired_formatted' => $this->formatDate($expiryAt),
                'active_until' => optional($expiryAt)?->toISOString(),
                'active_until_formatted' => $this->formatDate($expiryAt),
                'expired_at_formatted' => $this->formatDate($expiryAt),
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

    private function invoiceForAdminUser(User $user): ?Invitation
    {
        return Invitation::with('paketUndangan:id,code,name_paket,jenis_paket')
            ->where('user_id', $user->id)
            ->orderByRaw("
                CASE
                    WHEN LOWER(COALESCE(payment_status, status, '')) IN ('pending', 'belum selesai', 'unpaid', 'menunggu pembayaran') THEN 0
                    WHEN payment_status IN ('paid', 'confirmed') THEN 1
                    ELSE 2
                END
            ")
            ->orderByDesc('id')
            ->first();
    }

    private function normalizePaymentStatus(?string $status): ?string
    {
        if ($status === null || trim($status) === '') {
            return null;
        }

        return $this->isConfirmablePaymentStatus($status) ? 'pending' : strtolower(trim($status));
    }

    private function isConfirmablePaymentStatus(?string ...$statuses): bool
    {
        $confirmable = ['pending', 'belum selesai', 'unpaid', 'menunggu pembayaran'];

        foreach ($statuses as $status) {
            $normalized = strtolower(trim((string) $status));
            if (in_array($normalized, $confirmable, true)) {
                return true;
            }
        }

        return false;
    }

    private function formatDate($date): ?string
    {
        if (! $date) {
            return null;
        }

        return $date instanceof \DateTimeInterface
            ? $date->format('d/m/Y')
            : Carbon::parse($date)->format('d/m/Y');
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
