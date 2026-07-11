<?php

namespace App\Services\GlobalCatalog;

use App\Contracts\GlobalMusicProviderInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JamendoMusicProviderService implements GlobalMusicProviderInterface
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function fetchCatalog(): array
    {
        $enabled = (bool) config('music_catalog.enabled', false);
        $providerName = (string) config('music_catalog.provider', 'jamendo');
        $clientId = (string) config('music_catalog.jamendo.client_id', '');

        if (!$enabled || $providerName !== 'jamendo') {
            return [];
        }

        if ($clientId === '') {
            Log::warning('Jamendo provider skipped: missing JAMENDO_CLIENT_ID');
            return [];
        }

        $limit = (int) config('music_catalog.default_sync_limit', 20);
        $limit = max(1, min($limit, 200));

        $query = [
            'client_id' => $clientId,
            'format' => 'json',
            'limit' => $limit,
            'include' => 'musicinfo',
            'order' => 'popularity_total',
        ];

        $tags = config('music_catalog.jamendo.tags', []);
        if (!empty($tags)) {
            $query['tags'] = implode(',', $tags);
        }

        $genres = config('music_catalog.jamendo.genres', []);
        if (!empty($genres)) {
            $query['fuzzytags'] = implode(',', $genres);
        }

        $baseUrl = rtrim((string) config('music_catalog.jamendo.base_url', 'https://api.jamendo.com/v3.0/tracks/'), '/');
        $endpoint = $baseUrl . '/';

        try {
            $response = Http::timeout((int) config('music_catalog.jamendo.timeout_seconds', 20))
                ->acceptJson()
                ->get($endpoint, $query);

            if (!$response->successful()) {
                Log::warning('Jamendo provider response not successful', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $payload = $response->json();
            $results = is_array($payload['results'] ?? null) ? $payload['results'] : [];
            $now = Carbon::now();
            $tracks = [];

            foreach ($results as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }

                $externalId = (string) ($row['id'] ?? '');
                $title = (string) ($row['name'] ?? $row['title'] ?? '');
                $streamUrl = (string) ($row['audio'] ?? $row['audiodownload'] ?? '');
                $previewUrl = (string) ($row['audiodownload'] ?? $row['audio'] ?? '');

                if ($externalId === '' || $title === '' || $streamUrl === '') {
                    continue;
                }

                $tracks[] = [
                    'provider' => 'jamendo',
                    'external_id' => $externalId,
                    'title' => $title,
                    'artist' => $row['artist_name'] ?? null,
                    'album' => $row['album_name'] ?? null,
                    'thumbnail_url' => $row['image'] ?? $row['album_image'] ?? null,
                    'stream_url' => $streamUrl,
                    'preview_url' => $previewUrl ?: null,
                    'duration_seconds' => isset($row['duration']) ? (int) $row['duration'] : null,
                    'license_name' => $row['license_ccname'] ?? $row['license_name'] ?? null,
                    'license_url' => $row['license_ccurl'] ?? $row['license_url'] ?? null,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'fetched_at' => $now,
                    'raw_payload' => $row,
                ];
            }

            return $tracks;
        } catch (\Throwable $e) {
            Log::error('Jamendo provider fetch failed', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
