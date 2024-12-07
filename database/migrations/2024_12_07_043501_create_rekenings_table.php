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
            $table->unsignedBigInteger('kode_bank');
            $table->string('nomor_rekening');
            $table->string('nama_pemilik');
            $table->string('photo_rek');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('kode_bank')->references('id')->on('banks');
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
