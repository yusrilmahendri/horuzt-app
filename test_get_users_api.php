<?php

/**
 * Test get-users endpoint
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "Testing /api/v1/admin/get-users endpoint\n";
echo str_repeat('=', 80) . "\n\n";

// Get admin user and create token
$admin = User::find(1);
if (!$admin) {
    echo "Error: Admin user not found\n";
    exit(1);
}

$token = $admin->createToken('test-token')->plainTextToken;

// Test endpoint
$ch = curl_init('http://127.0.0.1:8000/api/v1/admin/get-users');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "Error: HTTP {$httpCode}\n";
    echo $response . "\n";
    exit(1);
}

$data = json_decode($response, true);

echo "HTTP Status: {$httpCode}\n\n";
echo "Results:\n";
echo "  Admin Users: " . count($data['admin']['data']) . "\n";
echo "  Regular Users: " . count($data['users']['data']) . "\n";
echo "  Total Users: " . $data['total_users'] . "\n\n";

echo "Users List:\n";
echo str_repeat('-', 80) . "\n";
foreach ($data['users']['data'] as $user) {
    echo sprintf("ID: %-3d | %-30s | %s\n",
        $user['id'],
        $user['email'],
        $user['name'] ?? '(no name)'
    );
}

echo "\n" . str_repeat('=', 80) . "\n";

if (count($data['users']['data']) === $data['total_users']) {
    echo "✓ SUCCESS: All {$data['total_users']} users are showing!\n";
} else {
    echo "✗ ISSUE: Showing " . count($data['users']['data']) . " out of {$data['total_users']} users\n";
    echo "  This is normal if pagination is enabled. Use ?per_page=all to see all users.\n";
}

echo "\n";

// Clean up token
$admin->tokens()->where('name', 'test-token')->delete();
