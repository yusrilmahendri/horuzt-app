<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
        // Sign in user
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid login details'], 401);
        }

        $user = Auth::user();

        // Generate token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Retrieve roles
        $roles = $user->getRoleNames(); // Returns a collection of role names assigned to the user

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'role' => $roles,
        ]);
    }

    
        // Log out user
        public function logout(Request $request)
        {   
            $request->user()->currentAccessToken()->delete();
            return response()->json([
                'message' => 'Successfully logged out',
                'status' => true,
            ])->withCookie(cookie()->forget('token'));
        }
}
