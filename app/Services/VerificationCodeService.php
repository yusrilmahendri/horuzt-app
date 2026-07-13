<?php

namespace App\Services;

use App\Contracts\WhatsAppGateway;
use App\Models\AccountVerificationToken;
use App\Models\User;
use App\Notifications\VerificationCodeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class VerificationCodeService
{
    public function __construct(private readonly WhatsAppGateway $whatsApp) {}

    public function issue(User $user, string $channel, string $purpose): AccountVerificationToken
    {
        $latest = AccountVerificationToken::query()->whereBelongsTo($user)->where('channel', $channel)
            ->where('purpose', $purpose)->latest('id')->first();
        $cooldown = (int) config('verification.resend_cooldown_seconds', 60);
        if ($latest?->sent_at && $latest->sent_at->copy()->addSeconds($cooldown)->isFuture()) {
            throw new RuntimeException('RESEND_LIMIT_REACHED');
        }
        $plain = (string) random_int(100000, 999999);
        $ttl = $channel === 'email' ? (int) config('verification.email_token_ttl_minutes', 30) : (int) config('verification.otp_ttl_minutes', 10);
        $token = DB::transaction(function () use ($user, $channel, $purpose, $plain, $ttl) {
            AccountVerificationToken::query()->whereBelongsTo($user)->where('channel', $channel)
                ->where('purpose', $purpose)->whereNull('used_at')->update(['used_at' => now()]);

            return AccountVerificationToken::create(['user_id' => $user->id, 'channel' => $channel,
                'purpose' => $purpose, 'token_hash' => Hash::make($plain), 'expires_at' => now()->addMinutes($ttl)]);
        });
        try {
            if ($channel === 'email') {
                $user->notify(new VerificationCodeNotification($plain, $purpose));
            } elseif (! $this->whatsApp->send($this->normalizePhone((string) $user->phone), $this->message($plain, $purpose))) {
                throw new RuntimeException;
            }
        } catch (\Throwable $e) {
            $token->delete();
            throw new RuntimeException('DELIVERY_FAILED', 0, $e);
        }
        $token->update(['sent_at' => now()]);

        return $token->fresh();
    }

    public function verify(User $user, string $channel, string $purpose, string $plain): string
    {
        return DB::transaction(function () use ($user, $channel, $purpose, $plain) {
            $token = AccountVerificationToken::query()->whereBelongsTo($user)->where('channel', $channel)
                ->where('purpose', $purpose)->whereNull('used_at')->latest('id')->lockForUpdate()->first();
            if (! $token) {
                return 'invalid';
            }
            if ($token->expires_at->isPast()) {
                return 'expired';
            }
            $max = (int) config('verification.max_attempts', 5);
            if ($token->attempts >= $max) {
                return 'attempts_exceeded';
            }
            if (! Hash::check($plain, $token->token_hash)) {
                $token->increment('attempts');

                return $token->fresh()->attempts >= $max ? 'attempts_exceeded' : 'invalid';
            }
            $token->update(['used_at' => now()]);

            return 'valid';
        });
    }

    public function resendAvailableIn(User $user, string $channel, string $purpose): int
    {
        $token = AccountVerificationToken::query()->whereBelongsTo($user)->where('channel', $channel)
            ->where('purpose', $purpose)->latest('id')->first();
        if (! $token?->sent_at) {
            return 0;
        }

        return max(0, now()->diffInSeconds($token->sent_at->copy()->addSeconds((int) config('verification.resend_cooldown_seconds', 60)), false));
    }

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($phone, '0')) {
            return '62'.substr($phone, 1);
        }

        return str_starts_with($phone, '62') ? $phone : '62'.$phone;
    }

    private function message(string $code, string $purpose): string
    {
        return ($purpose === 'password_reset' ? 'Kode reset kata sandi Anda: ' : 'Kode verifikasi akun Anda: ').$code.'. Kode ini hanya berlaku sekali.';
    }
}
