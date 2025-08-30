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
        Schema::table('ucapans', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->enum('kehadiran', ['hadir', 'tidak_hadir', 'mungkin'])->after('nama');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ucapans', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->dropColumn('kehadiran');
        });
    }
};