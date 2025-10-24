<?php

namespace App\Http\Controllers;

use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MidtransController extends Controller
{
    protected $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }

    public function createSnapToken(Request $request)
    {
        $user = Auth::user();

        // Contoh data transaksi (bisa diambil dari invitation/package yang user pilih)
        $orderId = 'INV-' . time();
        $grossAmount = $request->amount ?? 50000;

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $user->name ?? 'Guest',
                'email' => $user->email ?? 'guest@example.com',
            ],
            'callbacks' => [
                'finish' => url('/v1/midtrans/webhook'),
            ],
        ];

        $snapToken = $this->midtransService->createTransaction($params);

        // Simpan dulu data transaksi (opsional)
        // Transaction::create([...]);

        return response()->json([
            'snap_token' => $snapToken,
            'order_id' => $orderId
        ]);
    }

    public function handleWebhook(Request $request)
    {
        $serverKey = config('midtrans.server_key');
        $signatureKey = hash('sha512',
            $request->order_id .
            $request->status_code .
            $request->gross_amount .
            $serverKey
        );

        if ($signatureKey !== $request->signature_key) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $invitation = \App\Models\Invitation::where('order_id', $request->order_id)->first();

        if ($invitation) {
            if (in_array($request->transaction_status, ['capture', 'settlement'])) {
                $invitation->update([
                    'payment_status' => 'paid',
                    'payment_confirmed_at' => now(),
                ]);
            } elseif (in_array($request->transaction_status, ['deny', 'cancel', 'expire'])) {
                $invitation->update([
                    'payment_status' => 'failed',
                ]);
            }
        }

        return response()->json(['message' => 'Webhook processed']);
    }
}
