<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\MusicTrack;
use App\Models\PaketUndangan;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the effective music for a given Setting using a fixed priority:
 *   1. settings.musik          => custom upload (Diamond/Platinum users)
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
        if ($setting && ! empty($setting->musik) && $this->settingCanUseCustomMusic($setting)) {
            $info = $this->buildFromPath('custom', $setting->musik, null);
            if ($info) {
                return $info;
            }
        }

        // Priority 2: selected catalog track (must still be active).
        if ($this->catalogSchemaReady() && $setting && ! empty($setting->music_track_id)) {
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

        if (! $this->catalogSchemaReady()) {
            return null;
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
            'music_source_type' => $this->normalizeSourceType($resolved['source']),
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

    public function defaultTrack(): ?MusicTrack
    {
        if (! $this->catalogSchemaReady()) {
            return null;
        }

        return MusicTrack::where('is_default', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first()
            ?: MusicTrack::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();
    }

    /**
     * Build dashboard-friendly state while keeping old fields additive.
     *
     * @param  mixed  $user
     * @return array<string,mixed>
     */
    public function selectionState(?Setting $setting, $user = null): array
    {
        $resolved = $this->resolveInfo($setting);
        $selected = null;

        if ($this->catalogSchemaReady() && $setting?->music_track_id) {
            $selected = MusicTrack::where('id', $setting->music_track_id)
                ->where('is_active', true)
                ->first();
        }

        $default = $this->defaultTrack();
        $custom = $this->customMusicInfo($setting);

        return [
            'selected_music_id' => $this->catalogSchemaReady() ? $setting?->music_track_id : null,
            'selected_catalog_id' => $this->catalogSchemaReady() ? $setting?->music_track_id : null,
            'selected_music' => $selected ? $this->trackPayload($selected) : null,
            'default_music' => $default ? $this->trackPayload($default) : null,
            'custom_music' => $custom,
            'custom_music_url' => $custom['url'] ?? null,
            'resolved_music_url' => $resolved['url'] ?? null,
            'can_upload_custom_music' => $this->canUploadCustomMusicForUser($user),
            'music_source_type' => $this->normalizeSourceType($resolved['source'] ?? null),
            'active_music' => $resolved,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function customMusicInfo(?Setting $setting): ?array
    {
        if (! $setting || empty($setting->musik) || ! $this->settingCanUseCustomMusic($setting)) {
            return null;
        }

        $info = $this->buildFromPath('custom', $setting->musik, null);
        if (! $info) {
            return null;
        }

        return [
            'file_name' => basename($info['storage_path']),
            'file_size' => $info['file_size'],
            'mime_type' => $info['mime_type'],
            'url' => $info['url'],
            'storage_path' => $info['storage_path'],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function trackPayload(MusicTrack $track): array
    {
        return [
            'id' => $track->id,
            'title' => $track->title,
            'artist' => $track->artist,
            'subtitle' => $track->artist,
            'audio_url' => $track->url,
            'stream_url' => $track->url,
            'thumbnail_url' => null,
            'source' => $track->source,
            'duration_seconds' => $track->duration_seconds,
            'is_active' => $track->is_active,
            'is_default' => $track->is_default,
            'sort_order' => $track->sort_order,
        ];
    }

    public function canUploadCustomMusicForUser($user): bool
    {
        if (! $user) {
            return false;
        }

        $invitation = Invitation::with('paketUndangan')
            ->where('user_id', $user->id)
            ->orderByRaw("CASE WHEN payment_status = 'paid' THEN 0 ELSE 1 END")
            ->orderBy('id', 'desc')
            ->first();

        if (! $invitation) {
            return false;
        }

        $package = $invitation->paketUndangan;
        $snapshot = is_array($invitation->package_features_snapshot)
            ? $invitation->package_features_snapshot
            : [];

        $code = $package?->code
            ?? $snapshot['code']
            ?? $snapshot['package_code']
            ?? null;
        $rawName = $package?->getRawOriginal('name_paket')
            ?? $package?->name_paket
            ?? $snapshot['name_paket']
            ?? $snapshot['jenis_paket']
            ?? $snapshot['package_name']
            ?? null;

        $normalizedCode = is_string($code) ? strtolower(trim($code)) : null;
        if (in_array($normalizedCode, ['diamond', 'platinum'], true)) {
            return true;
        }

        $tier = PaketUndangan::tierCode($rawName, $code);

        return in_array($tier, ['diamond', 'platinum'], true);
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

    private function settingCanUseCustomMusic(Setting $setting): bool
    {
        $user = $setting->relationLoaded('user') ? $setting->user : $setting->user()->first();

        return $this->canUploadCustomMusicForUser($user);
    }

    private function normalizeSourceType(?string $source): string
    {
        return match ($source) {
            'custom' => 'custom',
            'catalog' => 'catalog',
            default => 'default',
        };
    }

    private function catalogSchemaReady(): bool
    {
        return Schema::hasTable('music_tracks')
            && Schema::hasTable('settings')
            && Schema::hasColumn('settings', 'music_track_id');
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
