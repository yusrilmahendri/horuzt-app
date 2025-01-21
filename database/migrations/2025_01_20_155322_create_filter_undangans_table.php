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
        Schema::create('filter_undangans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->integer('halaman_sampul');
            $table->integer('halaman_mempelai');
            $table->integer('halaman_acara');
            $table->integer('halaman_ucapan');
            $table->integer('halaman_galery');
            $table->integer('halaman_cerita');
            $table->integer('halaman_lokasi');
            $table->integer('halaman_prokes');
            $table->integer('halaman_send_gift');
            $table->integer('halaman_qoute');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filter_undangans');
    }
};
