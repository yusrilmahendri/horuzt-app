<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\PaketUndangan;
use App\Models\Setting;
use App\Models\Invitation;
use App\Models\Mempelai;
use App\Models\Galery;
use App\Models\Cerita;


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
                'token' => $token,
                'user_id' => $user->id,
                'domain' => $domain,
                'invitation' => $invitation,
            ], 201);

        } catch (\Exception $e) {
            \DB::rollBack(); // Batalkan perubahan jika terjadi error

            return response()->json([
                'message' => 'Gagal menyimpan data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeStepTwo(Request $request)
    {
        // Ambil user dari token atau session
        $user = auth('sanctum')->user() ?? User::find(session('user_id'));

        if (!$user) {
            return response()->json(['error' => 'User tidak ditemukan.'], 401);
        }

        $validated = $request->validate([
            'photo_pria' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'photo_wanita' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'cover_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'name_lengkap_pria' => 'nullable|string|max:255',
            'name_lengkap_wanita' => 'nullable|string|max:255',
            'name_panggilan_pria' => 'nullable|string|max:255',
            'name_panggilan_wanita' => 'nullable|string|max:255',
            'ayah_pria' => 'nullable|string|max:255',
            'ayah_wanita' => 'nullable|string|max:255',
            'ibu_pria' => 'nullable|string|max:255',
            'ibu_wanita' => 'nullable|string|max:255',
        ]);

        // Simpan gambar
        $photoPria = $request->hasFile('photo_pria') ? $request->file('photo_pria')->store('photos', 'public') : null;
        $photoWanita = $request->hasFile('photo_wanita') ? $request->file('photo_wanita')->store('photos', 'public') : null;
        $coverPhoto = $request->hasFile('cover_photo') ? $request->file('cover_photo')->store('photos', 'public') : null;

        // Simpan data mempelai
        $mempelai = Mempelai::updateOrCreate(
            ['user_id' => $user->id],
            [
                'cover_photo' => $coverPhoto,
                'photo_pria' => $photoPria,
                'photo_wanita' => $photoWanita,
                'name_lengkap_pria' => $validated['name_lengkap_pria'],
                'name_lengkap_wanita' => $validated['name_lengkap_wanita'],
                'name_panggilan_pria' => $validated['name_panggilan_pria'],
                'name_panggilan_wanita' => $validated['name_panggilan_wanita'],
                'ayah_pria' => $validated['ayah_pria'],
                'ayah_wanita' => $validated['ayah_wanita'],
                'ibu_pria' => $validated['ibu_pria'],
                'ibu_wanita' => $validated['ibu_wanita'],
            ]
        );

        // Update status undangan ke step2
        Invitation::where('user_id', $user->id)->update(['status' => 'step2']);

        return response()->json([
            'message' => 'Step 2 berhasil disimpan.',
            'mempelai' => $mempelai,
            'invitation_status' => 'step2'
        ], 200);
    }

    public function storeStepThree(Request $request){
        $user = auth('sanctum')->user() ?? User::find(session('user_id'));

        if (!$user) {
            return response()->json(['error' => 'User tidak ditemukan.'], 401);
        }

        $galeryPhoto = $request->hasFile('photo') ? $request->file('photo')->store('photos', 'public') : null;
        $galery = Galery::updateOrCreate(
             ['user_id' => $user->id], [
                'photo' => $galeryPhoto,
                'status' => $request->status
             ]);
        
            Invitation::where('user_id', $user->id)->update(['status' => 'step2']);

        return response()->json([
            'message' => 'Step 3 berhasil disimpan',
            'galery' => $galery,
            'invitation_status' => 'step3'
        ], 200);
    }

    public function storeStepFor(Request $request){
        
        $user = auth('sanctum')->user() ?? User::find(session('user_id'));

        if (!$user) {
            return response()->json(['error' => 'User tidak ditemukan.'], 401);
        }

         // Validasi input awal
        $title = $request->input('title', []);
        $leadCerita = $request->input('lead_cerita', []);
        $tglCerita = $request->input('tanggal_cerita', []);
    
        $count = count($title);
    
        // Validasi jumlah elemen pada input
        if (count($leadCerita) !== $count || count($tglCerita) !== $count) {
            return response()->json([
                'message' => 'Mismatch in the lead cerita data! All fields must have the same number of entries.',
            ], 400);
        }
    
        $savedCerita = [];
    
        for ($i = 0; $i < $count; $i++) {
            // Validasi per elemen untuk memastikan semua field terisi
            if (empty($title[$i]) || empty($leadCerita[$i]) || empty($tglCerita[$i])) {
                return response()->json([
                    'message' => 'Some required fields are missing for index ' . $i,
                ], 400);
            }
    
            // Buat entitas baru dan isi data
            $cerita = new Cerita();
            $cerita->user_id = $user->id;
            $cerita->title = $title[$i];
            $cerita->lead_cerita = $leadCerita[$i];
            $cerita->tanggal_cerita = $tglCerita[$i];
            $cerita->save();
    
            // Tambahkan ke array untuk response
            $savedCerita[] = [
                'title' => $cerita->title,
                'lead_cerita' => $cerita->lead_cerita,
                'tanggal_cerita' => $cerita->tanggal_cerita,
            ];
        }
    
        // Response sukses
        return response()->json([
            'data' => $savedCerita,
            'message' => 'Step 4 berhasil disimpan',
        ]);
    }


}
