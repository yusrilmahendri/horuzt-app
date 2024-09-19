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
        Schema::create('result_themas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thema_id');
            $table->unsignedBigInteger('jenis_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('thema_id')->references('id')->on('themas');
            $table->foreign('jenis_id')->references('id')->on('jenis_themas');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('result_themas');
    }
};
