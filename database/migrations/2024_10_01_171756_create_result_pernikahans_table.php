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
        Schema::create('result_pernikahans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('mempelai_id');
            $table->unsignedBigInteger('acara_id');
            $table->unsignedBigInteger('pengunjung_id');
            $table->unsignedBigInteger('qoute_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('mempelai_id')->references('id')->on('mempelais');
            $table->foreign('acara_id')->references('id')->on('acaras');
            $table->foreign('pengunjung_id')->references('id')->on('pengujungs');
            $table->foreign('qoute_id')->references('id')->on('qoutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('result_pernikahans');
    }
};
