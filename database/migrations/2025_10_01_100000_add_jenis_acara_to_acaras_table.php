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
        Schema::table('acaras', function (Blueprint $table) {
            $table->enum('jenis_acara', ['akad', 'resepsi'])
                  ->after('nama_acara')
                  ->comment('Type of wedding event: akad or resepsi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acaras', function (Blueprint $table) {
            $table->dropColumn('jenis_acara');
        });
    }
};