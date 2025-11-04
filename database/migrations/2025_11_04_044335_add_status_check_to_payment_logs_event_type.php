<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE payment_logs MODIFY COLUMN event_type ENUM('token_request', 'token_response', 'webhook_received', 'webhook_processed', 'status_check', 'error') NOT NULL DEFAULT 'token_request'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE payment_logs MODIFY COLUMN event_type ENUM('token_request', 'token_response', 'webhook_received', 'webhook_processed', 'error') NOT NULL DEFAULT 'token_request'");
    }
};
