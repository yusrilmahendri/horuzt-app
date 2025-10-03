<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
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
}