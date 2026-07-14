<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccountVerificationToken;
use App\Models\User;
use App\Services\VerificationCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function __construct(private readonly VerificationCodeService $codes) {}

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['nullable', 'string', 'required_without:email'],
            'email' => ['nullable', 'email', 'required_without:identifier'],
            'channel' => ['nullable', 'in:email,whatsapp'],
        ]);
        $identifier = $data['identifier'] ?? $data['email'];
        $channel = $data['channel'] ?? 'email';
        $user = $this->findUser($identifier, $channel);
        if ($user) {
            try {
                if ($channel === 'email') {
                    PasswordBroker::broker()->sendResetLink(['email' => $user->email]);
                } else {
                    $this->codes->issue($user, $channel, 'password_reset');
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['status' => 200, 'message' => 'Jika akun terdaftar, instruksi penggantian password akan dikirim.', 'data' => []]);
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
            'channel' => ['nullable', 'in:email,whatsapp'],
            'code' => ['nullable', 'digits:6'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);
        $identifier = $data['email'];
        $channel = $data['channel'] ?? 'email';
        $user = $this->findUser($identifier, $channel);
        if (! $user) {
            return $this->invalid();
        }

        $brokerStatus = null;
        if ($channel === 'email') {
            $brokerStatus = $this->resetWithPasswordBroker($data);
            if ($brokerStatus === PasswordBroker::PASSWORD_RESET) {
                return response()->json(['status' => 200, 'message' => 'Kata sandi berhasil diperbarui.', 'data' => []]);
            }
        }

        $plain = $data['code'] ?? $data['token'];
        $result = $this->codes->verify($user, $channel, 'password_reset', $plain);
        if ($result !== 'valid') {
            if ($brokerStatus === PasswordBroker::INVALID_TOKEN && $this->brokerTokenIsExpired($data['email'], $data['token'])) {
                return $this->invalid('RESET_TOKEN_EXPIRED');
            }

            return $this->invalid($result === 'expired' ? 'RESET_TOKEN_EXPIRED' : 'RESET_TOKEN_INVALID');
        }

        DB::transaction(function () use ($user, $data) {
            $user->forceFill(['password' => Hash::make($data['password']), 'remember_token' => null])->save();
            AccountVerificationToken::query()->whereBelongsTo($user)->where('purpose', 'password_reset')
                ->whereNull('used_at')->update(['used_at' => now()]);
            $user->tokens()->delete();
        });

        return response()->json(['status' => 200, 'message' => 'Kata sandi berhasil diperbarui.', 'data' => []]);
    }

    private function resetWithPasswordBroker(array $data): string
    {
        return PasswordBroker::broker()->reset(
            [
                'email' => $data['email'],
                'token' => $data['token'],
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'] ?? $data['password'],
            ],
            function (User $user, string $password): void {
                DB::transaction(function () use ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => null,
                    ])->save();

                    AccountVerificationToken::query()
                        ->whereBelongsTo($user)
                        ->where('purpose', 'password_reset')
                        ->whereNull('used_at')
                        ->update(['used_at' => now()]);

                    $user->tokens()->delete();
                });
            }
        );
    }

    private function brokerTokenIsExpired(string $email, string $plainToken): bool
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (! $record || ! Hash::check($plainToken, $record->token)) {
            return false;
        }

        $createdAt = $record->created_at ? \Illuminate\Support\Carbon::parse($record->created_at) : null;
        if (! $createdAt) {
            return true;
        }

        return $createdAt->addMinutes((int) config('auth.passwords.users.expire', 60))->isPast();
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
