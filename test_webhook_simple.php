<?php

/**
 * Simple Midtrans Webhook Testing Script
 * Usage: php test_webhook_simple.php <order_id> [status]
 * Example: php test_webhook_simple.php INV-xxx settlement
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

$orderId = $argv[1] ?? null;
$transactionStatus = $argv[2] ?? 'settlement';

if (!$orderId) {
    echo "Error: Order ID is required\n";
    echo "Usage: php test_webhook_simple.php <order_id> [status]\n";
    echo "\nExample:\n";
    echo "  php test_webhook_simple.php INV-1fc64dd6-2cbf-4b73-a9f6-a37ad28c5c88 settlement\n";
    echo "\nAvailable statuses:\n";
    echo "  - settlement (payment sukses - default)\n";
    echo "  - capture (authorized payment)\n";
    echo "  - pending (menunggu pembayaran)\n";
    echo "  - deny (ditolak)\n";
    echo "  - cancel (dibatalkan)\n";
    echo "  - expire (kadaluarsa)\n";
    exit(1);
}

// Load Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Invitation;
use App\Services\MidtransService;
use Illuminate\Support\Facades\DB;

echo "\n=== Midtrans Webhook Testing Script ===\n\n";

// Get invitation
$invitation = Invitation::where('order_id', $orderId)->first();

if (!$invitation) {
    echo "Error: Order ID not found in database\n";
    exit(1);
}

echo "Order found!\n";
echo "  Order ID: {$orderId}\n";
echo "  User ID: {$invitation->user_id}\n";
echo "  Invitation ID: {$invitation->id}\n";
echo "  Current Payment Status: {$invitation->payment_status}\n";

// Get amount
$grossAmount = $invitation->package_price_snapshot ?? 0;
echo "  Amount: Rp " . number_format($grossAmount, 0, ',', '.') . "\n\n";

// Get server key for signature generation
$midtransService = new MidtransService($invitation->user_id);
$serverKey = $midtransService->getServerKey();

// Generate signature
$statusCode = '200';
$transactionId = 'TEST-' . time();
$signatureString = $orderId . $statusCode . $grossAmount . $serverKey;
$signatureKey = hash('sha512', $signatureString);

echo "Simulating webhook with status: {$transactionStatus}\n\n";

// Create webhook payload
$webhookData = [
    'transaction_time' => date('Y-m-d H:i:s'),
    'transaction_status' => $transactionStatus,
    'transaction_id' => $transactionId,
    'status_message' => 'midtrans payment notification',
    'status_code' => $statusCode,
    'signature_key' => $signatureKey,
    'payment_type' => 'credit_card',
    'order_id' => $orderId,
    'merchant_id' => 'TEST-MERCHANT',
    'gross_amount' => $grossAmount,
    'fraud_status' => 'accept',
    'currency' => 'IDR',
];

echo "Webhook Payload:\n";
echo json_encode($webhookData, JSON_PRETTY_PRINT) . "\n\n";

// Send webhook request using cURL
$ch = curl_init('http://localhost:8000/api/v1/midtrans/webhook');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhookData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
]);

echo "Sending webhook request...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response (HTTP {$httpCode}):\n";
echo json_encode(json_decode($response), JSON_PRETTY_PRINT) . "\n\n";

// Check updated status
$invitation->refresh();
echo "Updated Payment Status:\n";
echo "  Payment Status: {$invitation->payment_status}\n";
if ($invitation->payment_confirmed_at) {
    echo "  Confirmed At: {$invitation->payment_confirmed_at}\n";
}
if ($invitation->domain_expires_at) {
    echo "  Domain Expires: {$invitation->domain_expires_at}\n";
}

if ($invitation->payment_status === 'paid') {
    echo "\n✓ Webhook processed successfully! Payment status updated.\n";
} else {
    echo "\n✗ Payment status not updated to 'paid'. Current status: {$invitation->payment_status}\n";
}

// Show recent payment logs
echo "\nRecent Payment Logs:\n";
$logs = \App\Models\PaymentLog::where('order_id', $orderId)
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get(['event_type', 'transaction_status', 'created_at', 'notes', 'error_message']);

foreach ($logs as $log) {
    $time = $log->created_at->format('Y-m-d H:i:s');
    $msg = "[{$time}] {$log->event_type} - {$log->transaction_status}";
    if ($log->notes) {
        $msg .= " ({$log->notes})";
    }
    if ($log->error_message) {
        $msg .= " ERROR: {$log->error_message}";
    }
    echo $msg . "\n";
}

echo "\n";
