<?php

namespace App\Services;

use App\Contracts\GlobalMusicProviderInterface;
use App\Models\ExternalMusicTrack;
use App\Services\GlobalCatalog\NullGlobalMusicCatalogProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class ExternalMusicCatalogService
{
    protected GlobalMusicProviderInterface $provider;

    public function __construct(GlobalMusicProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * @return \Illuminate\Support\Collection<int,\App\Models\ExternalMusicTrack>
     */
    public function activeCatalog()
    {
        if (! $this->catalogSchemaReady()) {
            return collect();
        }

        return ExternalMusicTrack::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('title', 'asc')
            ->get();
    }

    /**
     * Sync local cache table from global provider abstraction.
     *
     * @return array<string,int>
     */
    public function syncCatalog(): array
    {
        if (! $this->catalogSchemaReady()) {
            return [
                'received' => 0,
                'upserted' => 0,
            ];
        }

        $rows = $this->provider->fetchCatalog();
        $upserted = 0;

        foreach ($rows as $row) {
            $provider = (string) ($row['provider'] ?? config('music_catalog.provider_name', 'global'));
            $externalId = (string) ($row['external_id'] ?? $row['provider_track_id'] ?? '');
            $streamUrl = (string) ($row['stream_url'] ?? '');
            $title = (string) ($row['title'] ?? '');

            if ($externalId === '' || $streamUrl === '' || $title === '') {
                continue;
            }

            ExternalMusicTrack::updateOrCreate(
                [
                    'provider' => $provider,
                    'provider_track_id' => $externalId,
                ],
                [
                    'external_id' => $externalId,
                    'title' => $title,
                    'artist' => $row['artist'] ?? null,
                    'album' => $row['album'] ?? null,
                    'stream_url' => $streamUrl,
                    'preview_url' => $row['preview_url'] ?? null,
                    'thumbnail_url' => $row['thumbnail_url'] ?? $row['thumbnail'] ?? null,
                    'license_name' => $row['license_name'] ?? null,
                    'license_url' => $row['license_url'] ?? null,
                    'duration_seconds' => $row['duration_seconds'] ?? $row['duration'] ?? null,
                    'mime_type' => $row['mime_type'] ?? null,
                    'file_size' => $row['file_size'] ?? null,
                    'is_active' => (bool) ($row['is_active'] ?? true),
                    'fetched_at' => $row['fetched_at'] ?? Carbon::now(),
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'raw_payload' => $row['raw_payload'] ?? $row['payload'] ?? $row,
                    'payload' => $row['payload'] ?? $row,
                    'last_synced_at' => Carbon::now(),
                ]
            );

            $upserted++;
        }

        return [
            'received' => count($rows),
            'upserted' => $upserted,
        ];
    }

    private function catalogSchemaReady(): bool
    {
        return Schema::hasTable('external_music_tracks');
    }

    /**
     * Factory helper for local jobs/scripts where DI is unavailable.
     */
    public static function makeDefaultProvider(): GlobalMusicProviderInterface
    {
        return app()->bound(GlobalMusicProviderInterface::class)
            ? app(GlobalMusicProviderInterface::class)
            : new NullGlobalMusicCatalogProvider();
    }

    /**
     * Seed lightweight local mock tracks for development testing.
     *
     * @return array<string,int>
     */
    public function seedLocalMockCatalog(): array
    {
        if (! $this->catalogSchemaReady()) {
            return ['upserted' => 0];
        }

        $rows = [
            [
                'provider' => 'global_mock',
                'external_id' => 'mock-track-1',
                'title' => 'Global Mock Sunrise',
                'artist' => 'Global Artist',
                'preview_url' => 'https://cdn.example.com/music/mock-track-1-preview.mp3',
                'stream_url' => 'https://cdn.example.com/music/mock-track-1-stream.mp3',
                'duration' => 168,
                'thumbnail' => 'https://cdn.example.com/music/mock-track-1.jpg',
                'license_type' => 'royalty_free',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'provider' => 'global_mock',
                'external_id' => 'mock-track-2',
                'title' => 'Global Mock Twilight',
                'artist' => 'Global Artist',
                'preview_url' => 'https://cdn.example.com/music/mock-track-2-preview.mp3',
                'stream_url' => 'https://cdn.example.com/music/mock-track-2-stream.mp3',
                'duration' => 195,
                'thumbnail' => 'https://cdn.example.com/music/mock-track-2.jpg',
                'license_type' => 'royalty_free',
                'is_active' => true,
                'sort_order' => 2,
            ],
        ];

        foreach ($rows as $row) {
            ExternalMusicTrack::updateOrCreate(
                [
                    'provider' => $row['provider'],
                    'provider_track_id' => $row['external_id'],
                ],
                [
                    'external_id' => $row['external_id'],
                    'title' => $row['title'],
                    'artist' => $row['artist'],
                    'album' => null,
                    'stream_url' => $row['stream_url'],
                    'preview_url' => $row['preview_url'],
                    'thumbnail_url' => $row['thumbnail'],
                    'license_name' => $row['license_type'],
                    'license_url' => null,
                    'duration_seconds' => $row['duration'],
                    'is_active' => true,
                    'fetched_at' => Carbon::now(),
                    'sort_order' => $row['sort_order'],
                    'raw_payload' => $row,
                    'payload' => $row,
                    'last_synced_at' => Carbon::now(),
                ]
            );
        }

        return ['upserted' => count($rows)];
    }
}
