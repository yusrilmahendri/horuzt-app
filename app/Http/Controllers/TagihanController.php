<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\Mempelai;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagihanController extends Controller
{
    /**
     * Display user's billing history (paid invoices only)
     */
    public function index(): JsonResponse
    {
        try {
            $tagihan = Invitation::where('user_id', auth()->id())
                ->whereNotNull('payment_confirmed_at')
                ->where('payment_status', 'paid')
                ->orderBy('payment_confirmed_at', 'desc')
                ->get()
                ->map(function ($invitation) {
                    return [
                        'no_invoice' => '#' . str_pad($invitation->id, 6, '0', STR_PAD_LEFT),
                        'kode_pemesanan' => $invitation->kode_pemesanan ?? $invitation->user->kode_pemesanan ?? '-',
                        'midtrans_order_id' => $invitation->order_id ?? '-',
                        'tanggal_transaksi' => $invitation->payment_confirmed_at->format('d/m/Y H:i:s'),
                        'paket' => $invitation->package_features_snapshot['name_paket'] ?? 'Unknown Package',
                        'status' => 'lunas',
                        'harga' => $invitation->package_price_snapshot
                    ];
                });

            return response()->json([
                'message' => 'Data tagihan berhasil diambil',
                'data' => $tagihan
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data tagihan',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create a new invoice (tagihan) when user confirms manual payment.
     * Sets payment_status to 'pending' and domain_expires_at to the admin-configured
     * trial_masa_aktif value from the settings table (defaults to 3 days if not set).
     * This gives pending users a preview window while awaiting admin payment confirmation.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
            ]);

            $user = User::find($validated['user_id']);

            if (!$user) {
                return response()->json(['message' => 'User tidak ditemukan'], 404);
            }

            // Get invitation with package data
            $invitation = Invitation::where('user_id', $user->id)->first();

            if (!$invitation) {
                return response()->json(['message' => 'Data invitation tidak ditemukan'], 404);
            }

            // Read trial duration from admin-configurable settings, fallback to 3 days
            $trialConfig = Setting::whereNotNull('trial_masa_aktif')
                ->where('trial_masa_aktif', '>', 0)
                ->first();
            $trialDays = $trialConfig ? (int) $trialConfig->trial_masa_aktif : 3;

            // Check if invoice already exists for this invitation (pending)
            if ($invitation->payment_status === 'pending') {
                return response()->json([
                    'message' => 'Tagihan sudah ada',
                    'data' => [
                        'kode_pemesanan' => $invitation->kode_pemesanan,
                        'paket' => $invitation->package_features_snapshot['name_paket'] ?? 'Unknown',
                        'total' => $invitation->package_price_snapshot,
                        'status' => 'pending',
                        'domain_expires_at' => $invitation->domain_expires_at?->format('Y-m-d H:i:s'),
                        'trial_days' => $trialDays,
                    ]
                ], 200);
            }

            // Update invitation to pending status (manual payment initiated)
            $invitation->update([
                'payment_status' => 'pending',
                'domain_expires_at' => now()->addDays($trialDays),
            ]);

            // Update mempelai to Menunggu Konfirmasi
            $mempelai = Mempelai::where('user_id', $user->id)->first();
            if ($mempelai) {
                $mempelai->update([
                    'status' => 'Menunggu Konfirmasi',
                    'kd_status' => 'MK',
                ]);
            }

            return response()->json([
                'message' => 'Tagihan berhasil dibuat',
                'data' => [
                    'kode_pemesanan' => $invitation->kode_pemesanan,
                    'paket' => $invitation->package_features_snapshot['name_paket'] ?? 'Unknown',
                    'total' => $invitation->package_price_snapshot,
                    'status' => 'pending',
                    'domain_expires_at' => $invitation->domain_expires_at->format('Y-m-d H:i:s'),
                    'trial_days' => $trialDays,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat tagihan',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
