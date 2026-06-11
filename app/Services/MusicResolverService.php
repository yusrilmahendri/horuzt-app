<?php

namespace App\Services;

use App\Models\MusicTrack;
use App\Models\Setting;

/**
 * Resolves the effective music for a given Setting using a fixed priority:
 *   1. settings.musik          => custom upload (Diamond users)
 *   2. settings.music_track_id => catalog track selected by the user
 *   3. music_tracks.is_default => system default track
 *   4. none                    => null
 *
 * This is the single source of truth for "which music plays" so controllers,
 * the stream service and the wedding profile resource never duplicate the logic.
 */
class MusicResolverService
{
    /**
     * Resolve the full effective music descriptor (incl. absolute path for streaming).
     *
     * @param  Setting|null  $setting
     * @return array<string,mixed>|null
     */
    public function resolve(?Setting $setting): ?array
    {
        // Priority 1: custom upload.
        if ($setting && ! empty($setting->musik)) {
            $info = $this->buildFromPath('custom', $setting->musik, null);
            if ($info) {
                return $info;
            }
        }

        // Priority 2: selected catalog track (must still be active).
        if ($setting && ! empty($setting->music_track_id)) {
            $track = MusicTrack::where('id', $setting->music_track_id)
                ->where('is_active', true)
                ->first();

            if ($track) {
                $info = $this->buildFromTrack('catalog', $track);
                if ($info) {
                    return $info;
                }
            }
        }

        // Priority 3: active default track.
        $default = MusicTrack::where('is_default', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($default) {
            $info = $this->buildFromTrack('default', $default);
            if ($info) {
                return $info;
            }
        }

        // Priority 4: nothing available.
        return null;
    }

    /**
     * Public-safe music info (no absolute filesystem path).
     * Used by WeddingProfileResource and MusicController::info().
     *
     * @param  Setting|null  $setting
     * @return array<string,mixed>|null
     */
    public function resolveInfo(?Setting $setting): ?array
    {
        $resolved = $this->resolve($setting);

        if (! $resolved) {
            return null;
        }

        return [
            'has_music' => true,
            'source' => $resolved['source'],
            'track_id' => $resolved['track_id'],
            'title' => $resolved['title'],
            'artist' => $resolved['artist'],
            'mime_type' => $resolved['mime_type'],
            'file_size' => $resolved['file_size'],
            'url' => $resolved['url'],
            'supports_streaming' => true,
            'supports_range_requests' => true,
            'format_support' => ['mp3', 'wav', 'ogg', 'm4a'],
        ];
    }

    /**
     * Build descriptor from a track, falling back to file path resolution.
     *
     * @return array<string,mixed>|null
     */
    private function buildFromTrack(string $source, MusicTrack $track): ?array
    {
        if (empty($track->file_path)) {
            return null;
        }

        return $this->buildFromPath($source, $track->file_path, $track);
    }

    /**
     * Build descriptor from a stored relative path (e.g. "public/music/x.mp3").
     * Returns null if the underlying file is missing.
     *
     * @return array<string,mixed>|null
     */
    private function buildFromPath(string $source, string $storedPath, ?MusicTrack $track): ?array
    {
        $absolute = storage_path('app/' . $storedPath);

        if (! file_exists($absolute)) {
            return null;
        }

        $publicPath = preg_replace('#^public/#', '', $storedPath);

        $mimeType = ($track && ! empty($track->mime_type))
            ? $track->mime_type
            : (mime_content_type($absolute) ?: 'audio/mpeg');

        $fileSize = filesize($absolute);
        if ($fileSize === false) {
            $fileSize = $track?->file_size;
        }

        return [
            'source' => $source,
            'track_id' => $track?->id,
            'title' => $track?->title,
            'artist' => $track?->artist,
            'storage_path' => $storedPath,
            'absolute_path' => $absolute,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'url' => asset('storage/' . $publicPath),
        ];
    }
}
