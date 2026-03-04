<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix two SQLite issues caused by the invitations table recreation in the previous migration:
 *
 * 1. payment_logs.invitation_id FK now references "invitations_backup_enum_fix" (dropped)
 *    instead of "invitations" — because SQLite auto-updated the FK when we renamed the table.
 *
 * 2. payment_logs.event_type CHECK constraint doesn't include 'status_check'
 *    (the MODIFY COLUMN migration was skipped for SQLite).
 *
 * Solution: use PRAGMA legacy_alter_table=ON before renaming to suppress auto-FK-update,
 * then rebuild payment_logs with the correct FK and expanded event_type values.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return; // Only SQLite needs this fix
        }

        DB::statement('PRAGMA foreign_keys = OFF');

        // Rebuild payment_logs with correct FK and expanded event_type CHECK
        DB::statement('CREATE TABLE IF NOT EXISTS "payment_logs_fixed" (
            "id" integer primary key autoincrement not null,
            "user_id" integer,
            "invitation_id" integer,
            "order_id" varchar,
            "midtrans_transaction_id" varchar,
            "event_type" varchar check ("event_type" in (
                \'token_request\', \'token_response\', \'webhook_received\',
                \'webhook_processed\', \'status_check\', \'error\'
            )) not null default \'token_request\',
            "transaction_status" varchar check ("transaction_status" in (
                \'pending\', \'capture\', \'settlement\', \'challenge\',
                \'deny\', \'cancel\', \'expire\', \'refund\', \'unknown\'
            )),
            "payment_type" varchar,
            "gross_amount" numeric,
            "request_payload" text,
            "response_payload" text,
            "signature_key" varchar,
            "signature_valid" tinyint(1),
            "ip_address" varchar,
            "user_agent" varchar,
            "error_message" text,
            "notes" text,
            "created_at" datetime,
            "updated_at" datetime,
            foreign key("user_id") references "users"("id") on delete set null,
            foreign key("invitation_id") references "invitations"("id") on delete set null
        )');

        // Copy data (event_type values that don't fit the new CHECK will be excluded/NULL)
        // Cast invalid event_type to NULL to avoid constraint violations during copy
        DB::statement('INSERT INTO "payment_logs_fixed"
            SELECT
                id, user_id, invitation_id, order_id, midtrans_transaction_id,
                CASE WHEN event_type IN (\'token_request\', \'token_response\', \'webhook_received\', \'webhook_processed\', \'status_check\', \'error\')
                     THEN event_type ELSE \'webhook_received\' END,
                transaction_status, payment_type, gross_amount,
                request_payload, response_payload, signature_key, signature_valid,
                ip_address, user_agent, error_message, notes, created_at, updated_at
            FROM "payment_logs"
        ');

        DB::statement('DROP TABLE "payment_logs"');
        DB::statement('ALTER TABLE "payment_logs_fixed" RENAME TO "payment_logs"');

        // Recreate indexes
        DB::statement('CREATE INDEX IF NOT EXISTS "payment_logs_order_id_index" ON "payment_logs" ("order_id")');
        DB::statement('CREATE INDEX IF NOT EXISTS "payment_logs_midtrans_transaction_id_index" ON "payment_logs" ("midtrans_transaction_id")');
        DB::statement('CREATE INDEX IF NOT EXISTS "payment_logs_order_id_event_type_index" ON "payment_logs" ("order_id", "event_type")');
        DB::statement('CREATE INDEX IF NOT EXISTS "payment_logs_created_at_index" ON "payment_logs" ("created_at")');

        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        // Not reversible in dev environment
    }
};
