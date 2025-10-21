<?php

return [
    /*
    |--------------------------------------------------------------------------
    | File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for file upload limits and settings.
    | Configured for production-ready large file uploads (images, music, etc)
    |
    */

    // Maximum file size in KB (50 MB = 51200 KB)
    'max_file_size' => env('MAX_FILE_SIZE', 51200),

    // Maximum file size in MB for display purposes
    'max_file_size_mb' => env('MAX_FILE_SIZE_MB', 50),

    // Maximum music file size in KB (50 MB = 51200 KB)
    'max_music_size' => env('MAX_MUSIC_SIZE', 51200),

    // Maximum music file size in MB for display purposes
    'max_music_size_mb' => env('MAX_MUSIC_SIZE_MB', 50),

    // Allowed image MIME types
    'allowed_image_types' => [
        'jpeg',
        'png',
        'jpg',
        'webp'
    ],

    // Allowed music MIME types
    'allowed_music_types' => [
        'mp3',
        'wav',
        'ogg',
        'm4a',
        'aac',
        'flac'
    ],

    // Image dimension constraints
    'image_dimensions' => [
        'min_width' => 100,
        'min_height' => 100,
        'max_width' => 4000,
        'max_height' => 4000,
    ],

    // PHP upload settings (will be set by middleware)
    'php_settings' => [
        'upload_max_filesize' => '55M',
        'post_max_size' => '60M',
        'max_execution_time' => 300,
        'memory_limit' => '512M',
        'max_file_uploads' => 20,
    ],
];
