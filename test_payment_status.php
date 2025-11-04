<?php

/**
 * Test Payment Status Check Endpoint
 *
 * This script tests the new payment status check endpoint that allows
 * frontend to verify payment status after successful transaction.
 *
 * Usage:
 *   php test_payment_status.php <order_id>
 *
 * Example:
 *   php test_payment_status.php INV-94f2e5cd-bc8f-4cc2-9491-1851fe6763b7
 */

$orderId = $argv[1] ?? 'INV-94f2e5cd-bc8f-4cc2-9491-1851fe6763b7';

echo "Testing Payment Status Check Endpoint\n";
echo "=====================================\n";
echo "Order ID: {$orderId}\n\n";

$url = 'http://localhost:8000/api/v1/midtrans/check-status';

$data = [
    'order_id' => $orderId
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

echo "Sending request to: {$url}\n";
echo "Payload: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response Code: {$httpCode}\n";
echo "Response Body:\n";
echo json_encode(json_decode($response), JSON_PRETTY_PRINT) . "\n";

if ($httpCode === 200) {
    echo "\n✓ Payment status check successful!\n";

    $responseData = json_decode($response, true);
    if ($responseData['success'] && $responseData['payment_status'] === 'paid') {
        echo "✓ Payment is PAID and confirmed in database!\n";
    } else {
        echo "⚠ Payment status: {$responseData['payment_status']}\n";
    }
} else {
    echo "\n✗ Payment status check failed!\n";
}
