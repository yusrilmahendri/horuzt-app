<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('external_music_tracks')) {
            return;
        }

        Schema::table('external_music_tracks', function (Blueprint $table) {
            if (!Schema::hasColumn('external_music_tracks', 'album')) {
                $table->string('album')->nullable()->after('artist');
            }
            if (!Schema::hasColumn('external_music_tracks', 'external_id')) {
                $table->string('external_id', 191)->nullable()->after('provider');
            }
            if (!Schema::hasColumn('external_music_tracks', 'thumbnail_url')) {
                $table->text('thumbnail_url')->nullable()->after('preview_url');
            }
            if (!Schema::hasColumn('external_music_tracks', 'license_name')) {
                $table->string('license_name', 128)->nullable()->after('thumbnail_url');
            }
            if (!Schema::hasColumn('external_music_tracks', 'license_url')) {
                $table->text('license_url')->nullable()->after('license_name');
            }
            if (!Schema::hasColumn('external_music_tracks', 'fetched_at')) {
                $table->timestamp('fetched_at')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('external_music_tracks', 'raw_payload')) {
                $table->json('raw_payload')->nullable()->after('fetched_at');
            }
        });

        if (Schema::hasColumn('external_music_tracks', 'provider_track_id')) {
            DB::table('external_music_tracks')
                ->whereNull('external_id')
                ->update(['external_id' => DB::raw('provider_track_id')]);
        }

        Schema::table('external_music_tracks', function (Blueprint $table) {
            if (Schema::hasColumn('external_music_tracks', 'external_id')) {
                $table->index(['provider', 'external_id'], 'external_music_provider_external_id_index');
            }
            if (Schema::hasColumn('external_music_tracks', 'fetched_at')) {
                $table->index('fetched_at', 'external_music_fetched_at_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('external_music_tracks')) {
            return;
        }

        Schema::table('external_music_tracks', function (Blueprint $table) {
            if (Schema::hasColumn('external_music_tracks', 'fetched_at')) {
                $table->dropIndex('external_music_fetched_at_index');
                $table->dropColumn('fetched_at');
            }
            if (Schema::hasColumn('external_music_tracks', 'raw_payload')) {
                $table->dropColumn('raw_payload');
            }
            if (Schema::hasColumn('external_music_tracks', 'license_url')) {
                $table->dropColumn('license_url');
            }
            if (Schema::hasColumn('external_music_tracks', 'license_name')) {
                $table->dropColumn('license_name');
            }
            if (Schema::hasColumn('external_music_tracks', 'thumbnail_url')) {
                $table->dropColumn('thumbnail_url');
            }
            if (Schema::hasColumn('external_music_tracks', 'external_id')) {
                $table->dropIndex('external_music_provider_external_id_index');
                $table->dropColumn('external_id');
            }
            if (Schema::hasColumn('external_music_tracks', 'album')) {
                $table->dropColumn('album');
            }
        });
    }
};
