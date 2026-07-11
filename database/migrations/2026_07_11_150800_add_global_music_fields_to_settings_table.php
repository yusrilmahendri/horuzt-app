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
            if (! Schema::hasColumn('settings', 'music_source_type')) {
                $table->string('music_source_type', 32)->nullable()->after('music_track_id');
            }

            if (! Schema::hasColumn('settings', 'external_music_track_id')) {
                $table->unsignedBigInteger('external_music_track_id')->nullable()->after('music_source_type');
            }
        });

        Schema::table('settings', function (Blueprint $table) {
            $hasExternalColumn = Schema::hasColumn('settings', 'external_music_track_id');
            $hasExternalTable = Schema::hasTable('external_music_tracks');

            if ($hasExternalColumn && $hasExternalTable) {
                $table->foreign('external_music_track_id', 'settings_external_music_track_fk')
                    ->references('id')
                    ->on('external_music_tracks')
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
            if (Schema::hasColumn('settings', 'external_music_track_id')) {
                try {
                    $table->dropForeign('settings_external_music_track_fk');
                } catch (\Throwable $e) {
                    // noop for local dev DBs without FK metadata.
                }

                $table->dropColumn('external_music_track_id');
            }

            if (Schema::hasColumn('settings', 'music_source_type')) {
                $table->dropColumn('music_source_type');
            }
        });
    }
};
