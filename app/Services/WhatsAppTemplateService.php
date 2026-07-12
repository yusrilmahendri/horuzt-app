<?php

namespace App\Services;

class WhatsAppTemplateService
{
    private const ALLOWED_PLACEHOLDERS = [
        'guest_name',
        'bride_name',
        'groom_name',
        'invitation_url',
        'event_date',
        'event_location',
    ];

    public function render(?string $template, array $context = []): ?string
    {
        if ($template === null) {
            return null;
        }

        return preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', function (array $matches) use ($context) {
            $key = $matches[1];

            if (! in_array($key, self::ALLOWED_PLACEHOLDERS, true)) {
                return $matches[0];
            }

            return (string) ($context[$key] ?? '');
        }, $template);
    }

    public function allowedPlaceholders(): array
    {
        return self::ALLOWED_PLACEHOLDERS;
    }
}
