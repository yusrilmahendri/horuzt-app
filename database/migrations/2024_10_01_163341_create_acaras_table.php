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
        Schema::create('acaras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('countdown_id')->nullable();
            $table->string('nama_acara');
            $table->string('tanggal_acara');
            $table->string('start_acara');
            $table->string('end_acara');
            $table->string('alamat');
            $table->text('link_maps');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('countdown_id')->references('id')->on('countdown_acaras');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acaras');
    }
};
