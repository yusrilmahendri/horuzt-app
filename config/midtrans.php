<?php

$frontendUrl = rtrim(env('FRONTEND_URL', 'https://www.sena-digital.com'), '/');

return [
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    'is_sanitized' => env('MIDTRANS_IS_SANITIZED', true),
    'is_3ds' => env('MIDTRANS_IS_3DS', true),

    'frontend_finish_url' => env('MIDTRANS_FINISH_URL', $frontendUrl . '/buat-undangan?payment=finish'),
    'frontend_error_url' => env('MIDTRANS_ERROR_URL', $frontendUrl . '/buat-undangan?payment=error'),
    'frontend_pending_url' => env('MIDTRANS_PENDING_URL', $frontendUrl . '/buat-undangan?payment=unfinish'),

    'payment_limits' => [
        'min_amount' => env('MIDTRANS_MIN_AMOUNT', 10000),
        'max_amount' => env('MIDTRANS_MAX_AMOUNT', 100000000),
    ],

    'token_expiry_hours' => env('MIDTRANS_TOKEN_EXPIRY_HOURS', 24),

    'webhook_timeout' => env('MIDTRANS_WEBHOOK_TIMEOUT', 30),

    'logging' => [
        'enabled' => env('MIDTRANS_LOGGING_ENABLED', true),
        'channel' => env('MIDTRANS_LOG_CHANNEL', 'stack'),
    ],
];
