<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RegisterController extends Controller
{
    public function index(Request $request)
    {
        $validatedData = $request->validate([
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:30',
            'verification_channel' => 'nullable|string',
        ]);
        if (($validatedData['verification_channel'] ?? 'email') === 'whatsapp') {
            return response()->json(['status' => 422, 'code' => 'WHATSAPP_UNAVAILABLE', 'message' => 'Verifikasi WhatsApp sementara tidak tersedia.', 'data' => []], 422);
        }
        if (($validatedData['verification_channel'] ?? 'email') !== 'email') {
            return response()->json(['status' => 422, 'code' => 'VERIFICATION_CHANNEL_INVALID', 'message' => 'Channel verifikasi tidak valid.', 'data' => []], 422);
        }

        try {
            $user = User::create([
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'phone' => $validatedData['phone'] ?? null,
                'verification_channel' => $validatedData['verification_channel'] ?? 'email',
            ]);

            // Assign role to user (optional)
            if (method_exists($user, 'assignRole')) {
                $user->assignRole('user');
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 201,
                'message' => 'Registrasi berhasil. Silakan verifikasi akun Anda.',
                'data' => [
                    'user' => $user,
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ],
                // Legacy top-level keys retained for the existing Angular flow.
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 201);

        } catch (\Exception $e) {
            report($e);

            return response()->json(['status' => 500, 'message' => 'Registrasi gagal diproses.', 'data' => []], 500);
        }
    }
}
