<?php

use App\Services\GlobalCatalog\JamendoMusicProviderService;
use App\Services\GlobalCatalog\NullGlobalMusicCatalogProvider;

return [
    /*
    |--------------------------------------------------------------------------
    | Global Music Catalog Provider
    |--------------------------------------------------------------------------
    |
    | Keep local/backend as the single catalog provider for frontend. The real
    | global source can be integrated later by swapping this provider class.
    |
    */
    'enabled' => filter_var(env('GLOBAL_MUSIC_PROVIDER_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'provider' => env('GLOBAL_MUSIC_PROVIDER', 'jamendo'),
    'default_sync_limit' => (int) env('GLOBAL_MUSIC_SYNC_LIMIT', 20),

    // Legacy override hook for custom provider classes.
    'provider_class' => env('GLOBAL_MUSIC_PROVIDER_CLASS', NullGlobalMusicCatalogProvider::class),

    'providers' => [
        'jamendo' => JamendoMusicProviderService::class,
        'null' => NullGlobalMusicCatalogProvider::class,
    ],

    'jamendo' => [
        'base_url' => env('JAMENDO_BASE_URL', 'https://api.jamendo.com/v3.0/tracks/'),
        'client_id' => env('JAMENDO_CLIENT_ID'),
        'timeout_seconds' => (int) env('JAMENDO_TIMEOUT_SECONDS', 20),
        'tags' => array_values(array_filter(array_map('trim', explode(',', (string) env('GLOBAL_MUSIC_TAGS', ''))))),
        'genres' => array_values(array_filter(array_map('trim', explode(',', (string) env('GLOBAL_MUSIC_GENRES', ''))))),
    ],
];
