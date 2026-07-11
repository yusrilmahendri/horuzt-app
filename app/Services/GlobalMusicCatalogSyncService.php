<?php

namespace App\Services;

class GlobalMusicCatalogSyncService
{
    protected ExternalMusicCatalogService $externalCatalogService;

    public function __construct(ExternalMusicCatalogService $externalCatalogService)
    {
        $this->externalCatalogService = $externalCatalogService;
    }

    /**
     * @return array<string,int>
     */
    public function sync(): array
    {
        return $this->externalCatalogService->syncCatalog();
    }

    /**
     * @return array<string,int>
     */
    public function seedMock(): array
    {
        return $this->externalCatalogService->seedLocalMockCatalog();
    }
}
