<?php

/**
 * Bulk Payment Status Update Script
 * Updates all pending orders to paid status (simulates successful Midtrans webhook)
 *
 * Usage: php bulk_update_payments.php [--dry-run]
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

$dryRun = in_array('--dry-run', $argv);

// Load Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Invitation;
use App\Services\MidtransService;
use Illuminate\Support\Facades\DB;

echo "\n=== Bulk Payment Status Update Script ===\n\n";

if ($dryRun) {
    echo "🔍 DRY RUN MODE - No changes will be made\n\n";
}

// Get all pending orders
$pendingOrders = Invitation::where('payment_status', 'pending')
    ->whereNotNull('order_id')
    ->with('user')
    ->orderBy('created_at', 'desc')
    ->get();

if ($pendingOrders->isEmpty()) {
    echo "✓ No pending orders found. All orders are up to date!\n\n";
    exit(0);
}

echo "Found {$pendingOrders->count()} pending order(s)\n";
echo str_repeat('=', 120) . "\n";

$updated = 0;
$failed = 0;
$errors = [];

foreach ($pendingOrders as $invitation) {
    $orderId = $invitation->order_id;
    $userEmail = $invitation->user->email ?? 'N/A';
    $amount = $invitation->package_price_snapshot ?? 0;

    echo sprintf(
        "[%d/%d] Processing: %-45s | %-25s | Rp %s\n",
        $updated + $failed + 1,
        $pendingOrders->count(),
        $orderId,
        $userEmail,
        number_format($amount, 0, ',', '.')
    );

    if ($dryRun) {
        echo "         → Would update to PAID (dry run)\n";
        $updated++;
        continue;
    }

    try {
        // Get server key for signature
        $midtransService = new MidtransService($invitation->user_id);
        $serverKey = $midtransService->getServerKey();

        // Generate signature
        $statusCode = '200';
        $transactionId = 'BULK-UPDATE-' . time() . '-' . $invitation->id;
        $signatureString = $orderId . $statusCode . $amount . $serverKey;
        $signatureKey = hash('sha512', $signatureString);

        // Create webhook payload
        $webhookData = [
            'transaction_time' => date('Y-m-d H:i:s'),
            'transaction_status' => 'settlement',
            'transaction_id' => $transactionId,
            'status_message' => 'bulk payment status update',
            'status_code' => $statusCode,
            'signature_key' => $signatureKey,
            'payment_type' => 'credit_card',
            'order_id' => $orderId,
            'merchant_id' => 'BULK-UPDATE',
            'gross_amount' => $amount,
            'fraud_status' => 'accept',
            'currency' => 'IDR',
        ];

        // Send webhook request
        $ch = curl_init('http://localhost:8000/api/v1/midtrans/webhook');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhookData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("cURL Error: {$curlError}");
        }

        if ($httpCode !== 200) {
            throw new Exception("HTTP {$httpCode}: {$response}");
        }

        $responseData = json_decode($response, true);

        if (!isset($responseData['message']) || $responseData['message'] !== 'Webhook processed successfully') {
            throw new Exception("Unexpected response: {$response}");
        }

        // Verify update
        $invitation->refresh();
        if ($invitation->payment_status === 'paid') {
            echo "         ✓ Updated to PAID successfully\n";
            $updated++;
        } else {
            throw new Exception("Status not updated. Current status: {$invitation->payment_status}");
        }

        // Small delay to prevent overwhelming the server
        usleep(100000); // 100ms

    } catch (Exception $e) {
        echo "         ✗ FAILED: {$e->getMessage()}\n";
        $failed++;
        $errors[] = [
            'order_id' => $orderId,
            'email' => $userEmail,
            'error' => $e->getMessage(),
        ];
    }
}

echo str_repeat('=', 120) . "\n";
echo "\n📊 Summary:\n";
echo "   Total Orders: {$pendingOrders->count()}\n";
echo "   ✓ Successfully Updated: {$updated}\n";

if ($failed > 0) {
    echo "   ✗ Failed: {$failed}\n";

    if (!empty($errors)) {
        echo "\n❌ Failed Orders:\n";
        foreach ($errors as $error) {
            echo "   - {$error['order_id']} ({$error['email']}): {$error['error']}\n";
        }
    }
}

if ($dryRun) {
    echo "\n💡 This was a dry run. Run without --dry-run to actually update the orders.\n";
} else {
    echo "\n✅ Bulk update completed!\n";
}

echo "\n";
