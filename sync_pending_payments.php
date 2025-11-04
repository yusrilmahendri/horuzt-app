<?php

/**
 * Auto-Sync Pending Payments
 *
 * This script automatically updates pending payments by simulating Midtrans webhook
 * Useful for development when webhook is not configured
 *
 * Usage:
 *   php sync_pending_payments.php [--age=hours] [--auto-yes]
 *
 * Options:
 *   --age=N       Only sync orders older than N hours (default: 0 = all)
 *   --auto-yes    Skip confirmation prompt
 *   --dry-run     Show what would be updated without actually updating
 *
 * Examples:
 *   php sync_pending_payments.php                    # Sync all pending orders
 *   php sync_pending_payments.php --age=1            # Sync orders older than 1 hour
 *   php sync_pending_payments.php --auto-yes         # Skip confirmation
 *   php sync_pending_payments.php --dry-run          # Preview only
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

// Parse arguments
$options = getopt('', ['age:', 'auto-yes', 'dry-run', 'help']);

if (isset($options['help'])) {
    echo file_get_contents(__FILE__);
    exit(0);
}

$ageHours = isset($options['age']) ? (int)$options['age'] : 0;
$autoYes = isset($options['auto-yes']);
$dryRun = isset($options['dry-run']);

// Load Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Invitation;
use App\Services\MidtransService;
use Carbon\Carbon;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║           MIDTRANS PENDING PAYMENTS SYNC SCRIPT                ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

if ($dryRun) {
    echo "🔍 DRY RUN MODE - No changes will be made\n\n";
}

// Build query
$query = Invitation::where('payment_status', 'pending')
    ->whereNotNull('order_id');

if ($ageHours > 0) {
    $query->where('created_at', '<=', Carbon::now()->subHours($ageHours));
    echo "📅 Filtering: Orders older than {$ageHours} hour(s)\n";
}

$pendingOrders = $query->with('user')->orderBy('created_at', 'desc')->get();

if ($pendingOrders->isEmpty()) {
    echo "✅ No pending orders found. All payments are up to date!\n\n";
    exit(0);
}

echo "📊 Found {$pendingOrders->count()} pending order(s)\n";
echo str_repeat('=', 120) . "\n\n";

// Display orders
$totalAmount = 0;
foreach ($pendingOrders as $index => $order) {
    $age = $order->created_at->diffForHumans();
    $amount = $order->package_price_snapshot ?? 0;
    $totalAmount += $amount;

    echo sprintf(
        "%2d. %-45s | %-25s | Rp %s | %s\n",
        $index + 1,
        $order->order_id,
        $order->user->email ?? 'N/A',
        number_format($amount, 0, ',', '.'),
        $age
    );
}

echo "\n" . str_repeat('=', 120) . "\n";
echo sprintf("Total Amount: Rp %s\n", number_format($totalAmount, 0, ',', '.'));
echo str_repeat('=', 120) . "\n\n";

// Confirmation
if (!$autoYes && !$dryRun) {
    echo "⚠️  This will update all pending orders above to 'PAID' status.\n";
    echo "   Make sure these orders are actually PAID in Midtrans Dashboard!\n\n";
    echo "Continue? [y/N]: ";

    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);

    if (trim(strtolower($line)) !== 'y') {
        echo "\n❌ Cancelled by user.\n\n";
        exit(0);
    }
    echo "\n";
}

if ($dryRun) {
    echo "💡 Dry run complete. Run without --dry-run to actually update.\n\n";
    exit(0);
}

// Process orders
echo "🔄 Processing orders...\n";
echo str_repeat('=', 120) . "\n";

$updated = 0;
$failed = 0;
$errors = [];

foreach ($pendingOrders as $index => $invitation) {
    $orderId = $invitation->order_id;
    $userEmail = $invitation->user->email ?? 'N/A';
    $amount = $invitation->package_price_snapshot ?? 0;

    echo sprintf("[%d/%d] %s", $index + 1, $pendingOrders->count(), $orderId);

    try {
        // Get server key
        $midtransService = new MidtransService($invitation->user_id);
        $serverKey = $midtransService->getServerKey();

        // Generate signature
        $statusCode = '200';
        $transactionId = 'SYNC-' . time() . '-' . $invitation->id;
        $signatureString = $orderId . $statusCode . $amount . $serverKey;
        $signatureKey = hash('sha512', $signatureString);

        // Create webhook payload
        $webhookData = [
            'transaction_time' => date('Y-m-d H:i:s'),
            'transaction_status' => 'settlement',
            'transaction_id' => $transactionId,
            'status_message' => 'sync payment status',
            'status_code' => $statusCode,
            'signature_key' => $signatureKey,
            'payment_type' => 'credit_card',
            'order_id' => $orderId,
            'merchant_id' => 'AUTO-SYNC',
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

        // Verify update
        $invitation->refresh();
        if ($invitation->payment_status === 'paid') {
            echo " ✓ PAID\n";
            $updated++;
        } else {
            throw new Exception("Status not updated. Current: {$invitation->payment_status}");
        }

        // Small delay
        usleep(100000); // 100ms

    } catch (Exception $e) {
        echo " ✗ FAILED: {$e->getMessage()}\n";
        $failed++;
        $errors[] = [
            'order_id' => $orderId,
            'email' => $userEmail,
            'error' => $e->getMessage(),
        ];
    }
}

echo str_repeat('=', 120) . "\n\n";

// Summary
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                         SYNC SUMMARY                           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo sprintf("   Total Orders:      %d\n", $pendingOrders->count());
echo sprintf("   ✅ Updated:         %d\n", $updated);
if ($failed > 0) {
    echo sprintf("   ✗ Failed:          %d\n", $failed);
}
echo "\n";

if (!empty($errors)) {
    echo "❌ Failed Orders:\n";
    echo str_repeat('-', 120) . "\n";
    foreach ($errors as $error) {
        echo sprintf("   %s (%s)\n      Error: %s\n\n",
            $error['order_id'],
            $error['email'],
            $error['error']
        );
    }
}

if ($updated > 0) {
    echo "✅ Sync completed successfully!\n";
    echo "   {$updated} order(s) have been updated to PAID status.\n\n";

    // Show updated orders
    echo "Updated Orders:\n";
    echo str_repeat('-', 120) . "\n";
    $updatedOrders = Invitation::whereIn('order_id', $pendingOrders->pluck('order_id'))
        ->where('payment_status', 'paid')
        ->with('user')
        ->get();

    foreach ($updatedOrders as $order) {
        echo sprintf("   %s | %s | %s\n",
            $order->order_id,
            $order->user->email ?? 'N/A',
            $order->payment_confirmed_at->format('Y-m-d H:i:s')
        );
    }
    echo "\n";
}

echo "💡 TIP: To avoid manual syncing, setup Midtrans webhook notification URL.\n";
echo "   See: WEBHOOK_SETUP_GUIDE.md\n\n";
