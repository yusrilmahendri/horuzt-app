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
        Schema::table('jenis_themas', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->string('image')->nullable()->after('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jenis_themas', function (Blueprint $table) {
            $table->dropColumn(['slug', 'image']);
        });
    }
};
