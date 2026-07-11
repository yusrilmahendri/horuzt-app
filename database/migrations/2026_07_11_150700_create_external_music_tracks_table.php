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
        Schema::create('external_music_tracks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('artist')->nullable();
            $table->string('provider', 64)->default('global');
            $table->string('provider_track_id', 191);
            $table->text('stream_url');
            $table->text('preview_url')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_track_id'], 'external_music_provider_track_unique');
            $table->index(['is_active', 'sort_order'], 'external_music_active_sort_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_music_tracks');
    }
};
