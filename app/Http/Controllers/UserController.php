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
            $user->makeHidden(['roles']);
            $dataUser = auth()->user()->load('invitation.paketUndangan');
            if($dataUser){
                return response()->json(['data' => $dataUser], 200);
            }else{
                return new UserCollection(collect([$dataUser]));
            }
        }
        if($user->hasRole('admin')){
            $usersQuery = User::whereDoesntHave('roles', function($query) {
                $query->where('name', 'admin');
            });

            $totalUsers = $usersQuery->count();
            $users = $usersQuery->paginate(5);

            return response()->json([
                'admin' => new UserCollection(collect([$user])),
                'users' => new UserCollection($users),
                'total_users' => $totalUsers
            ]);
        }
        return response()->json(['message' => 'User not found'], 404);
    }

    public function update(Request $request)
    {   
    
        // Ambil user yang sedang login
        $user = auth()->user();
        if ($user->hasRole('user')) {
        // Validasi data dari request
        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'min:6',
            'phone' => 'min:11'
        ]);
             // Update data user
        if (isset($validatedData['name'])) {
            $user->name = $validatedData['name'];
        }

        if (isset($validatedData['email'])) {
            $user->email = $validatedData['email'];
        }

        if (isset($validatedData['password'])) {
            $user->password = bcrypt($validatedData['password']);
        }
        
        if (isset($validatedData['phone'])) {
            $user->phone = $validatedData['phone'];
        }

        $user->save();

        return response()->json([
            'message' => 'User updated successfully',
            'user' => new UserResource($user)
        ], 200);
        }
          if ($user->hasRole('admin')) {
            return new UserCollection(collect([$user]));
        }
    }

}