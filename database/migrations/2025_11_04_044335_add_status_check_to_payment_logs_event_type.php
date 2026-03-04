<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Note: MODIFY COLUMN is MySQL-only syntax. On SQLite enum columns are stored as TEXT
     * and accept any value, so this migration is a no-op on SQLite.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE payment_logs MODIFY COLUMN event_type ENUM('token_request', 'token_response', 'webhook_received', 'webhook_processed', 'status_check', 'error') NOT NULL DEFAULT 'token_request'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE payment_logs MODIFY COLUMN event_type ENUM('token_request', 'token_response', 'webhook_received', 'webhook_processed', 'error') NOT NULL DEFAULT 'token_request'");
    }
};
