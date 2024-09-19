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
        if ($user->hasRole('user')) {
            return new UserCollection(collect([$user]));
        }
        if($user->hasRole('admin')){
            $usersQuery = User::whereDoesntHave('roles', function($query) {
                $query->where('name', 'admin');
            });

            $totalUsers = $usersQuery->count();
            $users = $usersQuery->paginate(5);

            return response()->json([
                'user' => new UserCollection(collect([$user])),
                'users' => new UserCollection($users),
                'total_users' => $totalUsers
            ]);
        }
        return response()->json(['message' => 'User not found'], 404);
    }
}