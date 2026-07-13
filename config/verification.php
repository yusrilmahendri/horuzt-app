<?php

return [
    'otp_ttl_minutes' => (int) env('VERIFICATION_OTP_TTL', 10),
    'email_token_ttl_minutes' => (int) env('VERIFICATION_EMAIL_TTL', 30),
    'max_attempts' => (int) env('VERIFICATION_MAX_ATTEMPTS', 5),
    'resend_cooldown_seconds' => (int) env('VERIFICATION_RESEND_COOLDOWN', 60),
    'frontend_url' => env('FRONTEND_URL', env('APP_URL')),
    'whatsapp' => [
        'url' => env('WHATSAPP_GATEWAY_URL'),
        'token' => env('WHATSAPP_GATEWAY_TOKEN'),
    ],
];
