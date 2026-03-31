<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Register enum mapping for Doctrine DBAL (needed for ->change() on enum columns in SQLite)
        $this->registerEnumType();

        Schema::table('invitations', function (Blueprint $table) {
            $table->string('order_id', 100)->nullable()->unique()->after('paket_undangan_id');
            $table->string('midtrans_transaction_id', 100)->nullable()->after('order_id');

            // Skip enum change for MySQL due to Doctrine DBAL limitations with enum columns.
            // MySQL already handles enum modification properly, but Doctrine cannot introspect it.
            // Use raw SQL instead for MySQL to expand enum values.
            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE invitations MODIFY COLUMN payment_status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending'");
            }

            $table->index('order_id');
            $table->index(['order_id', 'midtrans_transaction_id'], 'idx_order_midtrans');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->registerEnumType();

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropIndex('invitations_order_id_index');
            $table->dropIndex('idx_order_midtrans');
            $table->dropColumn(['order_id', 'midtrans_transaction_id']);

            // Revert enum change for MySQL using raw SQL
            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE invitations MODIFY COLUMN payment_status ENUM('pending', 'paid') NOT NULL DEFAULT 'pending'");
            }
        });
    }

    private function registerEnumType(): void
    {
        try {
            $platform = DB::connection()->getDoctrineConnection()->getDatabasePlatform();
            $platform->registerDoctrineTypeMapping('enum', 'string');
        } catch (\Throwable) {
            // Doctrine DBAL not available or driver doesn't support it — safe to ignore
        }
    }
};
