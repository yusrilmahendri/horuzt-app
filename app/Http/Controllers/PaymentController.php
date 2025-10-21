<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MidtransService;

class PaymentController extends Controller
{
    public function create(Request $request, MidtransService $midtrans)
    {
        $params = [
            'transaction_details' => [
                'order_id' => 'ORDER-' . uniqid(),
                'gross_amount' => $request->amount,
            ],
            'customer_details' => [
                'first_name' => $request->name,
                'email' => $request->email,
            ],
        ];

       try {
    // Dapatkan hasil dari service
    $transaction = $midtrans->createTransaction($params);

    // Jika hasilnya string (token langsung)
    if (is_string($transaction)) {
        $snapToken = $transaction;
    } elseif (is_array($transaction) && isset($transaction['token'])) {
        $snapToken = $transaction['token'];
    } else {
        $snapToken = null;
    }

    if (!$snapToken) {
        throw new \Exception('Snap token tidak ditemukan dalam respons Midtrans.');
    }

    return response()->json(['snap_token' => $snapToken]);
} catch (\Exception $e) {
    return response()->json(['error' => $e->getMessage()], 500);
}

    }
}
