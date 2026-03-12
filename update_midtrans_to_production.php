#!/usr/bin/env php
<?php

/**
 * Update Midtrans configuration to production
 * Run once: php update_midtrans_to_production.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Updating Midtrans configuration to production...\n\n";

try {
    $productionConfig = [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'metode_production' => 'production',
    ];

    echo "Production credentials from .env:\n";
    echo "- Client Key: {$productionConfig['client_key']}\n";
    echo "- Server Key: {$productionConfig['server_key']}\n";
    echo "- Mode: {$productionConfig['metode_production']}\n\n";

    // Check current records
    $currentRecords = DB::table('midtrans_transactions')->get();
    echo "Current Midtrans records before update:\n";
    foreach ($currentRecords as $record) {
        echo "- ID: {$record->id}\n";
        echo "  Mode: {$record->metode_production}\n";
        echo "  Client Key: {$record->client_key}\n\n";
    }

    // Update all records
    $updated = DB::table('midtrans_transactions')
        ->update([
            'server_key' => $productionConfig['server_key'],
            'client_key' => $productionConfig['client_key'],
            'metode_production' => $productionConfig['metode_production'],
            'updated_at' => now(),
        ]);

    echo "✓ Updated {$updated} Midtrans transaction record(s) to production mode\n\n";

    // Verify the update
    $updatedRecords = DB::table('midtrans_transactions')->get();
    echo "Midtrans records after update:\n";
    foreach ($updatedRecords as $record) {
        echo "- ID: {$record->id}\n";
        echo "  Mode: {$record->metode_production}\n";
        echo "  Client Key: {$record->client_key}\n";
        echo "  Server Key: " . substr($record->server_key, 0, 20) . "...\n\n";
    }

    echo "✅ Migration to production completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Restart Laravel server: php artisan serve\n";
    echo "2. Test payment flow in frontend\n";
    echo "3. Verify UI shows 'Production' not 'Sandbox (Testing)'\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
