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
        Schema::create('pembayarans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bank_id');
            $table->unsignedBigInteger('order_id');
            $table->string('status');
            $table->string('nama_pemilik_rek');
            $table->string('no_rek');
            $table->string('price');
            $table->string('va_number');
            $table->string('type_channel');
            $table->timestamps();

            $table->foreign('bank_id')->references('id')->on('banks');
            $table->foreign('order_id')->references('id')->on('orders');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayarans');
    }
};
