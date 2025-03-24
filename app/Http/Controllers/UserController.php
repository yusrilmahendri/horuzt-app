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

    public function index()
    {
        $user = auth()->user();

        if ($user->hasRole('user')) {
            $user->makeHidden(['roles']);
            $dataUser = auth()->user()->load('invitation.paketUndangan', 'kodePemesanan', 'setting');

            return response()->json([
                'data'              => $dataUser,
                'kode_pemesanan'    => $dataUser->kodePemesanan ? $dataUser->kodePemesanan->kode_pemesanan : null,
                'keterangan'        => $dataUser->kodePemesanan ? $dataUser->kodePemesanan->keterangan : null,
                'domain'            => $dataUser->settingOne ? $dataUser->settingOne->domain : null,
                'url_video'         => $dataUser->mempelaiOne ? $dataUser->mempelaiOne->url_video : null,
                'paket_undangan_id' => $dataUser->invitation ? $dataUser->invitation->paket_undangan_id : null,
            ], 200);
        }

        if ($user->hasRole('admin')) {
            $usersQuery = User::whereDoesntHave('roles', function ($query) {
                $query->where('name', 'admin');
            });

            $countPaket1 = User::whereHas('invitation', function ($query) {
                $query->where('paket_undangan_id', 1);
            })->count();

            $countPaket2 = User::whereHas('invitation', function ($query) {
                $query->where('paket_undangan_id', 2);
            })->count();

            $countPaket3 = User::whereHas('invitation', function ($query) {
                $query->where('paket_undangan_id', 3);
            })->count();

            // Kalkulasi total keuntungan
            $totalKeuntungan = ($countPaket1 * 99000) + ($countPaket2 * 199000) + ($countPaket3 * 299000);

            $totalUsers = $usersQuery->count();
            $users      = $usersQuery->with(['kodePemesanan', 'setting'])->paginate(5);

            return response()->json([
                'admin'            => new UserCollection(collect([$user])),
                'users'            => new UserCollection($users),
                'total_users'      => $totalUsers,
                'total_keuntungan' => $totalKeuntungan,
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
                'name'     => 'sometimes|string|max:255',
                'email'    => 'sometimes|email|unique:users,email,' . $user->id,
                'password' => 'min:6',
                'phone'    => 'min:11',
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
                'user'    => new UserResource($user),
            ], 200);
        }
        if ($user->hasRole('admin')) {
            return new UserCollection(collect([$user]));
        }
    }

}
