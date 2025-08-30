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
        Schema::create('rekenings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('kode_bank');
            $table->string('email');
            $table->string('nomor_rekening');
            $table->string('nama_bank');
            $table->string('nama_pemilik');
            $table->string('methode_pembayaran');
            $table->string('id_methode_pembayaran');
            $table->string('photo_rek')->nullable(); // Ubah dari binary ke string
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rekenings');
    }
};
