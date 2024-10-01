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
        Schema::create('pernikahans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('nama_panggilan_pria');
            $table->string('nama_panggilan_wanita');
            $table->string('nama_lengkap_pria');
            $table->string('nama_lengkap_wanita');
            $table->string('gender_pria');
            $table->string('gender_wanita');
            $table->text('alamat');
            $table->string('video');
            $table->string('photo_pria');
            $table->string('photo_wanita');
            $table->string('tgl_cerita');
            $table->string('salam_pembuka')->nullable();
            $table->string('salam_wa_atas')->nullable();
            $table->string('salam_wa_bawah')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pernikahans');
    }
};
