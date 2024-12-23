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
        Schema::create('mempelais', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('cover_photo')->nullable();
            $table->string('urutan_mempelai')->nullable();
            $table->string('photo_pria')->nullable();
            $table->string('photo_wanita')->nullable();
            $table->string('name_lengkap_pria')->nullable();
            $table->string('name_lengkap_wanita')->nullable();
            $table->string('name_panggilan_pria')->nullable();
            $table->string('name_panggilan_wanita')->nullable();
            $table->string('ayah_pria')->nullable();
            $table->string('ayah_wanita')->nullable();
            $table->string('ibu_pria')->nullable();
            $table->string('ibu_wanita')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mempelais');
    }
};
