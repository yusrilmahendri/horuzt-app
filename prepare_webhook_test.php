#!/usr/bin/env php
<?php
// Bootstrap Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Invitation;

$email  = $argv[1] ?? 'ahmad@gmail.com';
$suffix = $argv[2] ?? uniqid('INV-TEST-');

$user = User::where('email', $email)->first();
if (!$user) { echo json_encode(['error' => 'user not found']); exit(1); }

$inv = Invitation::where('user_id', $user->id)->first();
if (!$inv) { echo json_encode(['error' => 'invitation not found']); exit(1); }

// Reset to pending, assign a fresh order_id
$orderId = 'INV-TEST-' . strtoupper($suffix);
$inv->update([
    'payment_status'          => 'pending',
    'order_id'                => $orderId,
    'midtrans_transaction_id' => null,
    'payment_confirmed_at'    => null,
    'domain_expires_at'       => null,
]);

echo json_encode([
    'order_id'      => $orderId,
    'invitation_id' => $inv->id,
    'user_id'       => $user->id,
    'amount'        => $inv->package_price_snapshot,
]);
