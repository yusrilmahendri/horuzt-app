<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccountVerificationToken;
use App\Models\User;
use App\Notifications\CustomResetPasswordNotification;
use App\Services\VerificationCodeService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function __construct(private readonly VerificationCodeService $codes) {}

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['nullable', 'string', 'required_without:email'],
            'email' => ['nullable', 'email', 'required_without:identifier'],
            'channel' => ['nullable', 'string'],
        ]);
        $identifier = $data['identifier'] ?? $data['email'];
        $channel = $data['channel'] ?? 'email';
        if ($channel === 'whatsapp') {
            return response()->json(['status' => 422, 'code' => 'WHATSAPP_UNAVAILABLE', 'message' => 'Verifikasi WhatsApp sementara tidak tersedia.', 'data' => []], 422);
        }
        if ($channel !== 'email') {
            return response()->json(['status' => 422, 'code' => 'RESET_CHANNEL_INVALID', 'message' => 'Channel reset kata sandi tidak valid.', 'data' => []], 422);
        }

        $user = $this->findUser($identifier, $channel);
        if ($user) {
            try {
                $this->sendEmailResetLink($user);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['status' => 200, 'message' => 'Link reset kata sandi telah dikirim ke email Anda.', 'data' => []]);
    }

    public function resend(Request $request): JsonResponse
    {
        return $this->forgotPassword($request);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['nullable', 'string'],
            'email' => ['required', 'email'],
            'channel' => ['nullable', 'string'],
            'code' => ['nullable', 'digits:6'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);
        $identifier = $data['email'];
        $channel = $data['channel'] ?? 'email';
        if ($channel === 'whatsapp') {
            return response()->json(['status' => 422, 'code' => 'WHATSAPP_UNAVAILABLE', 'message' => 'Verifikasi WhatsApp sementara tidak tersedia.', 'data' => []], 422);
        }
        if ($channel !== 'email') {
            return $this->invalid();
        }

        $user = $this->findUser($identifier, $channel);
        if (! $user) {
            $this->logResetVerify($data['email'], null);

            return $this->invalid();
        }

        $record = DB::table('password_reset_tokens')->where('email', $data['email'])->first();
        $this->logResetVerify($data['email'], $record);
        if (! $record || ! Hash::check($data['token'], $record->token)) {
            return $this->invalid();
        }

        if ($this->resetTokenIsExpired($record)) {
            return $this->invalid('RESET_TOKEN_EXPIRED');
        }

        DB::transaction(function () use ($user, $data) {
            $user->forceFill(['password' => Hash::make($data['password']), 'remember_token' => null])->save();
            AccountVerificationToken::query()->whereBelongsTo($user)->where('purpose', 'password_reset')
                ->whereNull('used_at')->update(['used_at' => now()]);
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
            $user->tokens()->delete();
        });
        event(new PasswordReset($user));

        return response()->json(['status' => 200, 'message' => 'Kata sandi berhasil diperbarui.', 'data' => []]);
    }

    private function sendEmailResetLink(User $user): void
    {
        $plain = Str::random(64);
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($plain),
            'created_at' => now(),
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        Log::debug('[RESET_PASSWORD_CREATE]', [
            'email' => $user->email,
            'token_id' => $record?->email,
            'expires_at' => now()->addMinutes($this->resetTokenTtlMinutes())->toDateTimeString(),
        ]);

        $user->notify(new CustomResetPasswordNotification($plain));
    }

    private function resetTokenIsExpired(object $record): bool
    {
        $createdAt = $record->created_at ? \Illuminate\Support\Carbon::parse($record->created_at) : null;
        if (! $createdAt) {
            return true;
        }

        return $createdAt->addMinutes($this->resetTokenTtlMinutes())->isPast();
    }

    private function resetTokenTtlMinutes(): int
    {
        return max(30, (int) config('auth.passwords.users.expire', 60));
    }

    private function logResetVerify(string $email, ?object $record): void
    {
        Log::debug('[RESET_PASSWORD_VERIFY]', [
            'email' => $email,
            'token_found' => (bool) $record,
            'expired' => $record ? $this->resetTokenIsExpired($record) : null,
            'now' => now()->toDateTimeString(),
            'expires_at' => $record?->created_at
                ? \Illuminate\Support\Carbon::parse($record->created_at)->addMinutes($this->resetTokenTtlMinutes())->toDateTimeString()
                : null,
        ]);
    }

    private function findUser(string $identifier, string $channel): ?User
    {
        if ($channel === 'email') {
            return User::where('email', $identifier)->first();
        }
        $normalized = $this->codes->normalizePhone($identifier);
        $local = str_starts_with($normalized, '62') ? '0'.substr($normalized, 2) : $normalized;

        return User::whereIn('phone', array_unique([$identifier, $normalized, '+'.$normalized, $local]))->first();
    }

    private function invalid(string $code = 'RESET_TOKEN_INVALID'): JsonResponse
    {
        $message = $code === 'RESET_TOKEN_EXPIRED' ? 'Token reset sudah kedaluwarsa.' : 'Token reset tidak valid.';

        return response()->json(['status' => 422, 'code' => $code, 'message' => $message, 'errors' => ['token' => [$message]]], 422);
    }
}
