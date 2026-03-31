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
        Schema::create('active_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('metode_transaction_id')->unique();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->foreign('metode_transaction_id')->references('id')->on('metode_transactions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('active_payment_methods');
    }
};
