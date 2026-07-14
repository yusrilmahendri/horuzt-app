<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminInvoiceController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:admin']);
    }

    /**
     * Get all invoices with comprehensive user and payment data
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invitation::with([
            'user:id,name,email,phone,kode_pemesanan',
            'user.settingOne:id,user_id,domain',
            'paketUndangan:id,name_paket,price,masa_aktif'
        ]);

        // Search by email, domain, kode_pemesanan, or order_id
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQ) use ($search) {
                    $userQ->where('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('kode_pemesanan', 'like', "%{$search}%");
                })
                    ->orWhere('order_id', 'like', "%{$search}%")
                    ->orWhere('kode_pemesanan', 'like', "%{$search}%")
                    ->orWhereHas('user.settingOne', function ($settingQ) use ($search) {
                        $settingQ->where('domain', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by payment status
        if ($request->filled('status')) {
            $query->where('payment_status', $request->status);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $invoices = $query->latest('created_at')->paginate($perPage);

        $data = $invoices->map(function ($invitation) {
            $invoiceCode = $invitation->kode_pemesanan
                ?? $invitation->user?->kode_pemesanan
                ?? null;

            $packageName = $invitation->package_features_snapshot['name_paket']
                ?? $invitation->paketUndangan?->name_paket
                ?? 'Unknown Package';

            $packagePrice = $invitation->package_price_snapshot
                ?? $invitation->paketUndangan?->price
                ?? 0;

            return [
                'id' => $invitation->id,
                'invoice_id' => $invitation->id,
                'user_id' => $invitation->user_id,
                'email' => $invitation->user?->email ?? '-',
                'phone' => $invitation->user?->phone ?? '-',
                'domain' => $invitation->user?->settingOne?->domain ?? '-',
                'kode_pemesanan' => $invoiceCode ?? '-',
                'invoice_code' => $invoiceCode,
                'has_invoice' => true,
                'midtrans_order_id' => $invitation->order_id ?? '-',
                'paket' => $packageName,
                'nama_paket' => $packageName,
                'harga' => (float) $packagePrice,
                'status' => $this->normalizePaymentStatus($invitation->payment_status ?? $invitation->status),
                'status_pembayaran' => $this->normalizePaymentStatus($invitation->payment_status ?? $invitation->status),
                'payment_status' => $this->normalizePaymentStatus($invitation->payment_status ?? $invitation->status),
                'raw_payment_status' => $invitation->payment_status,
                'can_confirm_payment' => $invoiceCode !== null && $this->isConfirmablePaymentStatus($invitation->payment_status, $invitation->status),
                'payment_confirmed_at' => $invitation->payment_confirmed_at?->format('d/m/Y H:i:s') ?? '-',
                'domain_expires_at' => $invitation->domain_expires_at?->format('d/m/Y') ?? '-',
                'active_until' => $invitation->domain_expires_at?->toISOString(),
                'active_until_formatted' => $invitation->domain_expires_at?->format('d/m/Y'),
                'expired_at_formatted' => $invitation->domain_expires_at?->format('d/m/Y'),
                'tanggal_expired_formatted' => $invitation->domain_expires_at?->format('d/m/Y'),
                'created_at' => $invitation->created_at->format('d/m/Y H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ]
        ]);
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
}
