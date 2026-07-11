<?php

namespace App\Services\GlobalCatalog;

use App\Contracts\GlobalMusicCatalogProvider;

class NullGlobalMusicCatalogProvider implements GlobalMusicCatalogProvider
{
    /**
     * Return an empty catalog for local/dev baseline.
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchCatalog(): array
    {
        return [];
    }
}
