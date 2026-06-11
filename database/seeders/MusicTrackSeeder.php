<?php

namespace Database\Seeders;

use App\Models\MusicTrack;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeds a single default catalog track ONLY when a placeholder file exists.
 *
 * Place a file at: storage/app/public/music/catalog/default.mp3
 * then run: php artisan db:seed --class=Database\\Seeders\\MusicTrackSeeder
 *
 * This seeder is intentionally NOT wired into DatabaseSeeder so a missing
 * file never breaks the main seeding run. It is safe to run repeatedly.
 */
class MusicTrackSeeder extends Seeder
{
    public function run(): void
    {
        $relativePath = 'public/music/catalog/default.mp3';

        // Skip silently if the placeholder file is not present.
        if (! Storage::exists($relativePath)) {
            $this->command?->warn('MusicTrackSeeder skipped: ' . $relativePath . ' not found.');
            return;
        }

        // Avoid duplicating the default track on re-run.
        if (MusicTrack::where('file_path', $relativePath)->exists()) {
            $this->command?->info('MusicTrackSeeder skipped: default track already exists.');
            return;
        }

        $absolute = Storage::path($relativePath);

        $track = MusicTrack::create([
            'title' => 'Default Wedding Song',
            'artist' => 'Sena Digital',
            'slug' => 'default-wedding-song-' . Str::lower(Str::random(6)),
            'file_path' => $relativePath,
            'mime_type' => @mime_content_type($absolute) ?: 'audio/mpeg',
            'file_size' => @filesize($absolute) ?: null,
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 0,
            'source' => 'sena_digital',
        ]);

        // Guarantee single default.
        MusicTrack::where('id', '!=', $track->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $this->command?->info('MusicTrackSeeder: default track created.');
    }
}
