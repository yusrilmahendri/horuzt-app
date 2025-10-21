<?php

return [
    /*
    |--------------------------------------------------------------------------
    | File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for file upload limits and settings.
    |
    */

    // Maximum file size in KB (5.1 MB = 5222 KB)
    'max_file_size' => env('MAX_FILE_SIZE', 5222),

    // Maximum file size in MB for display purposes
    'max_file_size_mb' => env('MAX_FILE_SIZE_MB', '5.1'),

    // Allowed image MIME types
    'allowed_image_types' => [
        'jpeg',
        'png',
        'jpg',
        'webp'
    ],

    // Image dimension constraints
    'image_dimensions' => [
        'min_width' => 100,
        'min_height' => 100,
        'max_width' => 2000,
        'max_height' => 2000,
    ],

    // PHP upload settings (will be set by middleware)
    // NOTE: These values are used by LargeFileHandler middleware
    // For production, configure these in .user.ini or .htaccess instead
    // Run: php artisan app:create-upload-config
    'php_settings' => [
        'upload_max_filesize' => env('PHP_UPLOAD_MAX_FILESIZE', '60M'),
        'post_max_size' => env('PHP_POST_MAX_SIZE', '200M'),
        'max_execution_time' => env('PHP_MAX_EXECUTION_TIME', 600),
        'memory_limit' => env('PHP_MEMORY_LIMIT', '512M'),
        'max_file_uploads' => env('PHP_MAX_FILE_UPLOADS', 20),
    ],
];
