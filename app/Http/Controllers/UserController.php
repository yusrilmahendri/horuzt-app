<?php
namespace App\Http\Controllers;

use App\Http\Resources\User\UserCollection;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function userProfile()
    {
        $user = auth()->user();
        if ($user->hasRole('user')) {
            $user->makeHidden(['roles']);
            $dataUser = auth()->user()->load('invitation.paketUndangan');
            if ($dataUser) {
                return response()->json(['data' => $dataUser], 200);
            } else {
                return new UserCollection(collect([$dataUser]));
            }
        }
        if ($user->hasRole('admin')) {
            $usersQuery = User::whereDoesntHave('roles', function ($query) {
                $query->where('name', 'admin');
            });

            $totalUsers = $usersQuery->count();
            $users      = $usersQuery->paginate(5);

            return response()->json([
                'admin'       => new UserCollection(collect([$user])),
                'users'       => new UserCollection($users),
                'total_users' => $totalUsers,
            ]);
        }
        return response()->json(['message' => 'User not found'], 404);
    }

    public function index()
    {
        $user = auth()->user();

        if ($user->hasRole('user')) {
            $user->makeHidden(['roles']);
            $dataUser = auth()->user()->load([
                'invitation.paketUndangan',
                'settingOne',
                'mempelaiOne',
                'invitationOne',
            ]);

            if ($dataUser) {
                return response()->json(['data' => new UserResource($dataUser)], 200);
            } else {
                return new UserCollection(collect([$dataUser]));
            }
        }

        if ($user->hasRole('admin')) {

            $usersQuery = User::whereDoesntHave('roles', function ($query) {
                $query->where('name', 'admin');
            })->with([
                'settingOne',
                'mempelaiOne',
                'invitationOne.paketUndangan',
            ]);


            $totalUsers = $usersQuery->count();


            $users = $usersQuery->paginate(5);


            $allUsers = User::whereDoesntHave('roles', function ($query) {
                $query->where('name', 'admin');
            })->with([
                'mempelaiOne',
                'invitationOne.paketUndangan',
            ])->get();


            $totalKeuntungan = $allUsers->sum(function ($user) {
                if (
                    $user->mempelaiOne &&
                    ($user->mempelaiOne->kd_status === 'SB' || $user->mempelaiOne->status === 'Sudah Bayar')
                ) {
                    return match ($user->invitationOne->paket_undangan_id ?? 0) {
                        1       => 99000,
                        2       => 199000,
                        3       => 299000,
                        default => 0,
                    };
                }
                return 0;
            });


            $jumlahBL = $allUsers->filter(fn($user) =>
                $user->mempelaiOne && $user->mempelaiOne->kd_status === 'BL'
            )->count();

            $jumlahMK = $allUsers->filter(fn($user) =>
                $user->mempelaiOne && $user->mempelaiOne->kd_status === 'MK'
            )->count();

            return response()->json([
                'admin'                          => new UserCollection(collect([$user])),
                'users'                          => new UserCollection($users),
                'total_users'                    => $totalUsers,
                'total_keuntungan'               => $totalKeuntungan,
                'jumlah_belum_lunas_dan_pending' => [
                    'BL' => $jumlahBL,
                    'MK' => $jumlahMK,
                ],
            ]);
        }

        return response()->json(['message' => 'User not found'], 404);
    }

    public function update(Request $request)
    {


        $user = auth()->user();
        if ($user->hasRole('user')) {

            $validatedData = $request->validate([
                'name'     => 'sometimes|string|max:255',
                'email'    => 'sometimes|email|unique:users,email,' . $user->id,
                'password' => 'min:6',
                'phone'    => 'min:11',
            ]);

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
                'user'    => new UserResource($user),
            ], 200);
        }
        if ($user->hasRole('admin')) {
            return new UserCollection(collect([$user]));
        }
    }

}
