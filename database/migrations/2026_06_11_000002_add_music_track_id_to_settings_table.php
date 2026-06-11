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
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'music_track_id')) {
                $table->unsignedBigInteger('music_track_id')->nullable()->after('musik');

                $table->foreign('music_track_id')
                    ->references('id')
                    ->on('music_tracks')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'music_track_id')) {
                $table->dropForeign(['music_track_id']);
                $table->dropColumn('music_track_id');
            }
        });
    }
};
