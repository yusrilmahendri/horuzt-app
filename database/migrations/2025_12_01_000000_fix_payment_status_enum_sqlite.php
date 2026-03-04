<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix the payment_status CHECK constraint in SQLite.
 *
 * The original migration added `payment_status` with CHECK IN('pending','paid').
 * A later migration tried to change it to include 'failed','refunded' but
 * ->change() is skipped on SQLite (Doctrine DBAL doesn't handle enum columns).
 * This migration handles the SQLite case using the 12-step ALTER TABLE procedure.
 *
 * For MySQL/Postgres this migration is a no-op (already handled).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return; // MySQL/Postgres already handled by 2025_10_30 migration
        }

        DB::statement('PRAGMA foreign_keys = OFF');
        // Use legacy_alter_table to prevent SQLite from auto-updating FK references
        // in other tables (e.g. payment_logs) when we rename 'invitations'.
        DB::statement('PRAGMA legacy_alter_table = ON');

        // Rename existing table
        DB::statement('ALTER TABLE "invitations" RENAME TO "invitations_backup_enum_fix"');

        // Recreate with expanded payment_status CHECK constraint
        DB::statement('CREATE TABLE "invitations" (
            "id" integer primary key autoincrement not null,
            "user_id" integer not null,
            "paket_undangan_id" integer not null,
            "status" varchar check ("status" in (\'step1\', \'step2\', \'step3\', \'step4\', \'completed\')) not null default \'step1\',
            "created_at" datetime,
            "updated_at" datetime,
            "payment_status" varchar check ("payment_status" in (\'pending\', \'paid\', \'failed\', \'refunded\', \'expired\')) not null default \'pending\',
            "domain_expires_at" datetime,
            "payment_confirmed_at" datetime,
            "package_price_snapshot" numeric,
            "package_duration_snapshot" integer,
            "package_features_snapshot" text,
            "order_id" varchar,
            "midtrans_transaction_id" varchar,
            foreign key("user_id") references "users"("id"),
            foreign key("paket_undangan_id") references "paket_undangans"("id")
        )');

        // Copy all existing data
        DB::statement('INSERT INTO "invitations" SELECT * FROM "invitations_backup_enum_fix"');

        // Drop old table
        DB::statement('DROP TABLE "invitations_backup_enum_fix"');

        // Recreate all indexes
        DB::statement('CREATE INDEX IF NOT EXISTS "invitations_payment_status_domain_expires_at_index" ON "invitations" ("payment_status", "domain_expires_at")');
        DB::statement('CREATE INDEX IF NOT EXISTS "invitations_user_id_payment_status_index" ON "invitations" ("user_id", "payment_status")');
        DB::statement('CREATE INDEX IF NOT EXISTS "invitations_order_id_index" ON "invitations" ("order_id")');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS "invitations_order_id_unique" ON "invitations" ("order_id")');
        DB::statement('CREATE INDEX IF NOT EXISTS "idx_order_midtrans" ON "invitations" ("order_id", "midtrans_transaction_id")');

        DB::statement('PRAGMA legacy_alter_table = OFF');
        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        // Revert only needed on SQLite
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA foreign_keys = OFF');
        DB::statement('ALTER TABLE "invitations" RENAME TO "invitations_backup_enum_revert"');

        DB::statement('CREATE TABLE "invitations" (
            "id" integer primary key autoincrement not null,
            "user_id" integer not null,
            "paket_undangan_id" integer not null,
            "status" varchar check ("status" in (\'step1\', \'step2\', \'step3\', \'step4\', \'completed\')) not null default \'step1\',
            "created_at" datetime,
            "updated_at" datetime,
            "payment_status" varchar check ("payment_status" in (\'pending\', \'paid\')) not null default \'pending\',
            "domain_expires_at" datetime,
            "payment_confirmed_at" datetime,
            "package_price_snapshot" numeric,
            "package_duration_snapshot" integer,
            "package_features_snapshot" text,
            "order_id" varchar,
            "midtrans_transaction_id" varchar,
            foreign key("user_id") references "users"("id"),
            foreign key("paket_undangan_id") references "paket_undangans"("id")
        )');

        DB::statement('INSERT INTO "invitations" SELECT * FROM "invitations_backup_enum_revert"');
        DB::statement('DROP TABLE "invitations_backup_enum_revert"');
        DB::statement('CREATE INDEX IF NOT EXISTS "invitations_payment_status_domain_expires_at_index" ON "invitations" ("payment_status", "domain_expires_at")');
        DB::statement('CREATE INDEX IF NOT EXISTS "invitations_user_id_payment_status_index" ON "invitations" ("user_id", "payment_status")');
        DB::statement('CREATE INDEX IF NOT EXISTS "invitations_order_id_index" ON "invitations" ("order_id")');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS "invitations_order_id_unique" ON "invitations" ("order_id")');
        DB::statement('CREATE INDEX IF NOT EXISTS "idx_order_midtrans" ON "invitations" ("order_id", "midtrans_transaction_id")');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
