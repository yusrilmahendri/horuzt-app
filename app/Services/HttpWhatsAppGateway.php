<?php

namespace App\Services;

use App\Contracts\WhatsAppGateway;
use Illuminate\Support\Facades\Http;

class HttpWhatsAppGateway implements WhatsAppGateway
{
    public function send(string $phone, string $message): bool
    {
        $url = config('verification.whatsapp.url');
        $token = config('verification.whatsapp.token');
        if (! $url || ! $token) {
            return false;
        }

        return Http::asJson()->withToken($token)->timeout(10)->post($url, [
            'phone' => $phone,
            'message' => $message,
        ])->successful();
    }
}
