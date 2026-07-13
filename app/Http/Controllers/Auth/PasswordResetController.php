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
use Illuminate\Validation\Rules\Password;

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
                $this->codes->issue($user, $channel, 'password_reset');
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
            'identifier' => ['nullable', 'string', 'required_without:email'],
            'email' => ['nullable', 'email', 'required_without:identifier'],
            'channel' => ['nullable', 'in:email,whatsapp'],
            'code' => ['nullable', 'digits:6', 'required_without:token'],
            'token' => ['nullable', 'string', 'required_without:code'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $identifier = $data['identifier'] ?? $data['email'];
        $channel = $data['channel'] ?? 'email';
        $user = $this->findUser($identifier, $channel);
        $plain = $data['code'] ?? $data['token'];
        if (! $user) {
            return $this->invalid();
        }
        $result = $this->codes->verify($user, $channel, 'password_reset', $plain);
        if ($result !== 'valid') {
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
