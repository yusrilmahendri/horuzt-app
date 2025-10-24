<?php

namespace App\Services;

use App\Models\MidtransTransaction;
use Midtrans\Config;
use Midtrans\Snap;

class MidtransService
{
    public function createTransaction(array $params)
    {
        // Ambil konfigurasi aktif (misal record terakhir atau punya user tertentu)
        $config = MidtransTransaction::latest()->first();

        // Kalau belum ada di DB, fallback ke .env
        $isProduction = $config ? $config->metode_production === 'production' : config('midtrans.is_production');
        $serverKey = $config->server_key ?? config('midtrans.server_key');
        $clientKey = $config->client_key ?? config('midtrans.client_key');

        // Set konfigurasi Midtrans dinamis
        Config::$serverKey = $serverKey;
        Config::$isProduction = $isProduction;
        Config::$isSanitized = true;
        Config::$is3ds = true;

        // Generate Snap Token
        $snapToken = Snap::getSnapToken($params);

        return $snapToken;
    }
}

