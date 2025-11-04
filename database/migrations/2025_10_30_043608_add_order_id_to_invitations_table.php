<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->string('order_id', 100)->nullable()->unique()->after('paket_undangan_id');
            $table->string('midtrans_transaction_id', 100)->nullable()->after('order_id');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending')->change();

            $table->index('order_id');
            $table->index(['order_id', 'midtrans_transaction_id'], 'idx_order_midtrans');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropIndex('invitations_order_id_index');
            $table->dropIndex('idx_order_midtrans');
            $table->dropColumn(['order_id', 'midtrans_transaction_id']);
            $table->enum('payment_status', ['pending', 'paid'])->default('pending')->change();
        });
    }
};
