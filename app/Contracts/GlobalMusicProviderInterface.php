<?php

namespace App\Contracts;

interface GlobalMusicProviderInterface
{
    /**
     * Fetch latest global catalog entries from upstream provider.
     * Expected normalized keys per item:
     * - provider
     * - external_id
     * - title
     * - artist
     * - album
     * - thumbnail_url
     * - stream_url
     * - preview_url
     * - duration_seconds
     * - license_name
     * - license_url
     * - is_active
     * - sort_order
     * - fetched_at
     * - raw_payload
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchCatalog(): array;
}
