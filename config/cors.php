<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // Development
        'http://localhost:4200',
        'http://127.0.0.1:4200',

        // Production
        'https://www.sena-digital.com',
        'https://sena-digital.com',
        'http://www.sena-digital.com',
        'http://sena-digital.com',
        'https://cloud-api.sena-digital.com',
        'https://cloud-api.sena-digital.com/api'
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],


    'supports_credentials' => true,

];
