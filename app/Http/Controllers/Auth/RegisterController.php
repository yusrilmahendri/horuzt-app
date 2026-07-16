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
            'name' => 'required|string|min:3|max:100',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:30',
            'verification_channel' => 'nullable|string',
        ], [
            'name.required' => 'Nama pengguna wajib diisi.',
            'name.min' => 'Nama pengguna minimal 3 karakter.',
            'name.max' => 'Nama pengguna maksimal 100 karakter.',
            'name.string' => 'Nama pengguna harus berupa teks.',
        ]);
        if (($validatedData['verification_channel'] ?? 'email') === 'whatsapp') {
            return response()->json(['status' => 422, 'code' => 'WHATSAPP_UNAVAILABLE', 'message' => 'Verifikasi WhatsApp sementara tidak tersedia.', 'data' => []], 422);
        }
        if (($validatedData['verification_channel'] ?? 'email') !== 'email') {
            return response()->json(['status' => 422, 'code' => 'VERIFICATION_CHANNEL_INVALID', 'message' => 'Channel verifikasi tidak valid.', 'data' => []], 422);
        }

        try {
            $user = User::create([
                'name' => trim($validatedData['name']),
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'phone' => $validatedData['phone'] ?? null,
                'verification_channel' => $validatedData['verification_channel'] ?? 'email',
            ]);

            // Assign role to user (optional)
            if (method_exists($user, 'assignRole')) {
                Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
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
