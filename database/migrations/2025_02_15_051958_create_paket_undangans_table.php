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
        Schema::create('paket_undangans', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_paket');
            $table->string('name_paket');
            $table->decimal('price', 10, 2); // Menggunakan decimal untuk harga
            $table->integer('masa_aktif'); // Masa aktif dalam hari, integer lebih cocok
            $table->integer('halaman_buku')->nullable(); // Jika opsional, bisa nullable
            $table->boolean('kirim_wa')->default(false);
            $table->boolean('bebas_pilih_tema')->default(false);
            $table->boolean('kirim_hadiah')->default(false);
            $table->boolean('import_data')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paket_undangans');
    }
};
