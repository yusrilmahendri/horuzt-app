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
            $packageName = $invitation->package_features_snapshot['name_paket']
                ?? $invitation->paketUndangan?->name_paket
                ?? 'Unknown Package';

            $packagePrice = $invitation->package_price_snapshot
                ?? $invitation->paketUndangan?->price
                ?? 0;

            return [
                'id' => $invitation->id,
                'email' => $invitation->user?->email ?? '-',
                'phone' => $invitation->user?->phone ?? '-',
                'domain' => $invitation->user?->settingOne?->domain ?? '-',
                'kode_pemesanan' => $invitation->kode_pemesanan
                    ?? $invitation->user?->kode_pemesanan
                    ?? '-',
                'midtrans_order_id' => $invitation->order_id ?? '-',
                'paket' => $packageName,
                'harga' => (float) $packagePrice,
                'payment_status' => $invitation->payment_status,
                'payment_confirmed_at' => $invitation->payment_confirmed_at?->format('d/m/Y H:i:s') ?? '-',
                'domain_expires_at' => $invitation->domain_expires_at?->format('d/m/Y') ?? '-',
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
}
