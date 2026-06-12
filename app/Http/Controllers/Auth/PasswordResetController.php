<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    /**
     * Send a password reset link to the given email.
     * Public endpoint: POST /api/v1/forgot-password
     *
     * The response is intentionally generic so it never reveals whether an
     * email is registered or not.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

       
        try {
            // Throttling and token storage are handled by the Laravel broker.
            Password::sendResetLink(['email' => $validated['email']]);
        } catch (\Exception $e) {
            // Log internally but keep the public response generic.
            Log::error('Forgot password failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Jika email terdaftar, tautan reset kata sandi akan dikirim.',
            'data' => null,
        ], 200);
    }

    /**
     * Reset the user's password using the emailed token.
     * Public endpoint: POST /api/v1/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'status' => true,
                'message' => 'Kata sandi berhasil diperbarui.',
                'data' => null,
            ], 200);
        }

        return response()->json([
            'status' => false,
            'message' => 'Token reset kata sandi tidak valid atau sudah kedaluwarsa.',
            'errors' => [
                'token' => ['Token reset kata sandi tidak valid atau sudah kedaluwarsa.'],
            ],
        ], 422);
    }
}
