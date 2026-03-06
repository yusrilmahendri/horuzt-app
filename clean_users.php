<?php
/**
 * Clean all user data except admin
 * Run: php clean_users.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Starting clean operation...\n\n";

try {
    DB::beginTransaction();

    // Get all non-admin user IDs
    $nonAdminUsers = DB::table('users')
        ->whereNotIn('email', ['admin@gmail.com'])
        ->pluck('id')
        ->toArray();

    if (empty($nonAdminUsers)) {
        echo "No non-admin users found. Nothing to delete.\n";
        DB::rollBack();
        exit(0);
    }

    echo "Found " . count($nonAdminUsers) . " non-admin users to delete.\n";
    echo "User IDs: " . implode(', ', $nonAdminUsers) . "\n\n";

    // Delete in order (respecting foreign key constraints)
    $tables = [
        'payment_logs',
        'midtrans_transactions',
        'tripay_transactions',
        'transaction_tagihans',
        'buku_tamus',
        'ucapans',
        'testimonis',
        'galeries',
        'ceritas',
        'qoutes',
        'rekenings',
        'acaras',
        'countdown_acaras',
        'pernikahans',
        'result_themas',
        'orders',
        'invitations',
        'mempelais',
        'settings',
        'komentars',
        'personal_access_tokens', // Sanctum tokens
    ];

    foreach ($tables as $table) {
        if (DB::getSchemaBuilder()->hasTable($table)) {
            $deleted = DB::table($table)->whereIn('user_id', $nonAdminUsers)->delete();
            if ($deleted > 0) {
                echo "Deleted {$deleted} rows from {$table}\n";
            }
        }
    }

    // Delete users themselves (except admin)
    $deletedUsers = DB::table('users')->whereIn('id', $nonAdminUsers)->delete();
    echo "\nDeleted {$deletedUsers} users\n";

    DB::commit();

    echo "\n✓ All non-admin user data cleaned successfully.\n";
    echo "Admin user preserved.\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
