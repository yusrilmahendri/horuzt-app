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
            $table->timestamp('selected_at')->nullable()->after('user_id');
            
            // Add index for better query performance
            $table->index(['user_id', 'selected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('result_themas', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'selected_at']);
            $table->dropColumn('selected_at');
        });
    }
};