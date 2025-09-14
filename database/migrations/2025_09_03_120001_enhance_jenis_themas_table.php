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
            $table->boolean('is_active')->default(true)->after('url_thema');
            $table->text('description')->nullable()->after('is_active');
            $table->string('demo_url')->nullable()->after('description');
            $table->integer('sort_order')->default(0)->after('demo_url');
            $table->json('features')->nullable()->after('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jenis_themas', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'description', 'demo_url', 'sort_order', 'features']);
        });
    }
};