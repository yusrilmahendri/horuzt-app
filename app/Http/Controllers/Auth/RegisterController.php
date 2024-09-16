<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;


class RegisterController extends Controller
{
    public function index(Request $request)
    {
        // Validasi input yang masuk
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'required|string|min:11',
        ]);
    
        try {
            // Buat user baru
            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'phone' => $validatedData['phone'],
            ]);
    
            // Assign role to user (optional)
            if (method_exists($user, 'assignRole')) {
                $user->assignRole('user');
            }
    
            // Generate token menggunakan Laravel Sanctum
            $token = $user->createToken('auth_token')->plainTextToken;
    
            // Kembalikan response jika berhasil
            return response()->json([
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 201); // Menggunakan status code 201 untuk created
    
        } catch (\Exception $e) {
            // Menangkap exception jika ada kesalahan
            return response()->json(['error' => 'Failed to create user: ' . $e->getMessage()], 500);
        }
    }
    
}
