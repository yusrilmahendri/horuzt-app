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
        Schema::create('paket_nikahs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('price')->nullable();
            $table->string('masa_aktif')->nullable();
            $table->string('buku_tamu')->nullable();
            $table->string('kirim_wa')->nullable();
            $table->string('kirim_hadiah')->nullable();
            $table->string('tema_bebas')->nullable();
            $table->string('import_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paket_nikahs');
    }
};
