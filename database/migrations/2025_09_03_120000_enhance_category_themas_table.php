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
        Schema::table('category_themas', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('slug');
            $table->enum('type', ['website', 'video'])->default('website')->after('is_active');
            $table->text('description')->nullable()->after('type');
            $table->string('icon')->nullable()->after('description');
            $table->integer('sort_order')->default(0)->after('icon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('category_themas', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'type', 'description', 'icon', 'sort_order']);
        });
    }
};