#!/usr/bin/env php
<?php
// Bootstrap Laravel
$app = require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Invitation;
use App\Models\MidtransTransaction;
use App\Models\PaketUndangan;

$user = User::where('email', 'ahmad@gmail.com')->first();
if (!$user) {
    echo "ERROR: ahmad@gmail.com not found\n";
    exit(1);
}

$paket = PaketUndangan::first();

$inv = Invitation::firstOrCreate(
    ['user_id' => $user->id],
    [
        'paket_undangan_id' => $paket?->id ?? 1,
        'payment_status' => 'pending',
        'package_price_snapshot' => $paket?->price ?? 150000,
        'package_duration_snapshot' => $paket?->masa_aktif ?? 1,
    ]
);
echo "Invitation ID: " . $inv->id . "\n";

$mid = MidtransTransaction::firstOrCreate(
    ['user_id' => $user->id],
    [
        'url'                   => 'https://api.sandbox.midtrans.com',
        'server_key'            => 'SB-Mid-server-TESTLOCAL12345',
        'client_key'            => 'SB-Mid-client-TESTLOCAL12345',
        'metode_production'     => 'sandbox',
        'methode_pembayaran'    => 'snap',
        'id_methode_pembayaran' => 'snap',
    ]
);
echo "Midtrans config ID: " . $mid->id . "\n";
echo "InvitationUserId: " . $inv->user_id . "\n";
echo "Paket price: " . ($paket?->price ?? 150000) . "\n";
