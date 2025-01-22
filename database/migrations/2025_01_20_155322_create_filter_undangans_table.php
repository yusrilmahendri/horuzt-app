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
            $table->integer('halaman_sampul')->default(0);
            $table->integer('halaman_mempelai')->default(0);
            $table->integer('halaman_acara')->default(0);
            $table->integer('halaman_ucapan')->default(0);
            $table->integer('halaman_galery')->default(0);
            $table->integer('halaman_cerita')->default(0);
            $table->integer('halaman_lokasi')->default(0);
            $table->integer('halaman_prokes')->default(0);
            $table->integer('halaman_send_gift')->default(0);
            $table->integer('halaman_qoute')->default(0);
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
