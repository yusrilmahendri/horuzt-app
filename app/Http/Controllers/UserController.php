<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\User\UserResource;
use App\Http\Resources\User\UserCollection;
use Illuminate\Support\Facades\Auth;
use App\Models\User;


class UserController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }       

    public function index() {
        $user = auth()->user();
        if ($user) {
            return new UserCollection(collect([$user]));
        }
        return response()->json(['message' => 'User not found'], 404);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'required|string|min:11',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->password = Hash::make($validated['password']);
        $user->phone = $validated['phone'];
        $user->save();

        return response()->json([
            'message' => 'User updated successfully!',
            'data' => $user
        ], 200);
    }
}
