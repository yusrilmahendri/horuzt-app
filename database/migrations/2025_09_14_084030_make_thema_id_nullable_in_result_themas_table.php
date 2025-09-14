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
        Schema::table('result_themas', function (Blueprint $table) {
            // Make thema_id nullable to support new jenis_themas system
            $table->unsignedBigInteger('thema_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('result_themas', function (Blueprint $table) {
            // Revert thema_id back to not nullable
            $table->unsignedBigInteger('thema_id')->nullable(false)->change();
        });
    }
};
