<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\PaketUndangan;
use App\Models\Setting;
use App\Models\Invitation;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class InvitationController extends Controller
{
    public function storeStepOne(Request $request){
        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'phone' => 'required|string',
            'paket_undangan_id' => 'required|exists:paket_undangans,id',
            'domain' => 'required|string|unique:settings,domain'
        ]);

        try {
            \DB::beginTransaction(); // Mulai transaksi database

            // Simpan user baru
            $user = User::create([
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']), // Gunakan bcrypt
                'phone' => $validated['phone'],
            ]);

            if (method_exists($user, 'assignRole')) {
                $user->assignRole('user');
            }

            // Generate token autentikasi
            $token = $user->createToken('auth_token')->plainTextToken;

            // Simpan domain setting
            $domain = Setting::create([
                'user_id' => $user->id,
                'domain' => $validated['domain'],
            ]);

            // Simpan invitation
            $invitation = Invitation::create([
                'status' => 'step1',
                'paket_undangan_id' => $validated['paket_undangan_id'],
                'user_id' => $user->id,
            ]);

            \DB::commit(); // Simpan perubahan jika semua berhasil

            return response()->json([
                'message' => 'Step 1 berhasil',
                'user' => $user,
                'domain' => $domain,
                'invitation' => $invitation
            ], 201);

        } catch (\Exception $e) {
            \DB::rollBack(); // Batalkan perubahan jika terjadi error

            return response()->json([
                'message' => 'Gagal menyimpan data',
                'error' => $e->getMessage()
            ], 500);
        }
    }



}
