<?php
namespace App\Http\Controllers;

use App\Http\Resources\TagihanTransaction\TagihanTransactionCollection;
use App\Models\Cerita;
use App\Models\Galery;
use App\Models\Invitation;
use App\Models\Mempelai;
use App\Models\MetodeTransaction;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class InvitationController extends Controller
{
    public function masterTagihan()
    {
        $data = MetodeTransaction::get();
        return new TagihanTransactionCollection($data);
    }

    public function storeStepOne(Request $request)
    {
        $validated = $request->validate([
            'email'             => 'required|email|unique:users,email',
            'password'          => 'required|min:6',
            'phone'             => 'required|string',
            'paket_undangan_id' => 'required|exists:paket_undangans,id',
            'domain'            => 'required|string|unique:settings,domain',
        ]);

        try {
            \DB::beginTransaction(); // Mulai transaksi database

            // Simpan user baru
            $user = User::create([
                'email'          => $validated['email'],
                'password'       => Hash::make($validated['password']), // Gunakan bcrypt
                'phone'          => $validated['phone'],
                'kode_pemesanan' => '#' . mt_rand(1000000000, 9999999999),
            ]);

            if (method_exists($user, 'assignRole')) {
                $user->assignRole('user');
            }

            // Generate token autentikasi
            $token = $user->createToken('auth_token')->plainTextToken;

            // Simpan domain setting
            $domain = Setting::create([
                'user_id' => $user->id,
                'domain'  => $validated['domain'],
            ]);

            // Simpan invitation
            $invitation = Invitation::create([
                'status'            => 'step1',
                'paket_undangan_id' => $validated['paket_undangan_id'],
                'user_id'           => $user->id,
            ]);

            \DB::commit(); // Simpan perubahan jika semua berhasil

            return response()->json([
                'message'    => 'Step 1 berhasil',
                'user'       => $user,
                'token'      => $token,
                'user_id'    => $user->id,
                'domain'     => $domain,
                'invitation' => $invitation,
            ], 201);

        } catch (\Exception $e) {
            \DB::rollBack(); // Batalkan perubahan jika terjadi error

            return response()->json([
                'message' => 'Gagal menyimpan data',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function storeStepTwo(Request $request)
    {
        // Ambil user dari token atau session
        $user = auth('sanctum')->user() ?? User::find(session('user_id'));

        if (! $user) {
            return response()->json(['error' => 'User tidak ditemukan.'], 401);
        }

        $validated = $request->validate([
            'photo_pria'            => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'photo_wanita'          => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'cover_photo'           => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'name_lengkap_pria'     => 'nullable|string|max:255',
            'name_lengkap_wanita'   => 'nullable|string|max:255',
            'name_panggilan_pria'   => 'nullable|string|max:255',
            'name_panggilan_wanita' => 'nullable|string|max:255',
            'ayah_pria'             => 'nullable|string|max:255',
            'ayah_wanita'           => 'nullable|string|max:255',
            'ibu_pria'              => 'nullable|string|max:255',
            'ibu_wanita'            => 'nullable|string|max:255',
            'url_video'             => 'nullable|string|max:255',
        ]);

        // Simpan gambar
        $photoPria   = $request->hasFile('photo_pria') ? $request->file('photo_pria')->store('photos', 'public') : null;
        $photoWanita = $request->hasFile('photo_wanita') ? $request->file('photo_wanita')->store('photos', 'public') : null;
        $coverPhoto  = $request->hasFile('cover_photo') ? $request->file('cover_photo')->store('photos', 'public') : null;

        // Simpan data mempelai
        $mempelai = Mempelai::updateOrCreate(
            ['user_id' => $user->id],
            [
                'cover_photo'           => $coverPhoto,
                'photo_pria'            => $photoPria,
                'photo_wanita'          => $photoWanita,
                'name_lengkap_pria'     => $validated['name_lengkap_pria'],
                'name_lengkap_wanita'   => $validated['name_lengkap_wanita'],
                'name_panggilan_pria'   => $validated['name_panggilan_pria'],
                'name_panggilan_wanita' => $validated['name_panggilan_wanita'],
                'ayah_pria'             => $validated['ayah_pria'],
                'ayah_wanita'           => $validated['ayah_wanita'],
                'ibu_pria'              => $validated['ibu_pria'],
                'ibu_wanita'            => $validated['ibu_wanita'],
            ]
        );

        // Update status undangan ke step2
        Invitation::where('user_id', $user->id)->update(['status' => 'step2']);

        return response()->json([
            'message'           => 'Step 2 berhasil disimpan.',
            'mempelai'          => $mempelai,
            'invitation_status' => 'step2',
        ], 200);
    }

    public function updateStepTwo(Request $request)
    {
        // Ambil user berdasarkan user_id
        $user = User::find($request->user_id);

        $validated = $request->validate([
            'photo_pria'            => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'photo_wanita'          => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'cover_photo'           => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'name_lengkap_pria'     => 'nullable|string|max:255',
            'name_lengkap_wanita'   => 'nullable|string|max:255',
            'name_panggilan_pria'   => 'nullable|string|max:255',
            'name_panggilan_wanita' => 'nullable|string|max:255',
            'ayah_pria'             => 'nullable|string|max:255',
            'ayah_wanita'           => 'nullable|string|max:255',
            'ibu_pria'              => 'nullable|string|max:255',
            'ibu_wanita'            => 'nullable|string|max:255',
            'url_video'             => 'nullable|string|max:255',
        ]);

        // Ambil data mempelai yang sudah ada
        $mempelai = Mempelai::where('user_id', $user->id)->first();

        if (! $mempelai) {
            return response()->json(['error' => 'Data mempelai tidak ditemukan.'], 404);
        }

        // Update gambar jika ada file yang diunggah
        if ($request->hasFile('photo_pria')) {
            $validated['photo_pria'] = $request->file('photo_pria')->store('photos', 'public');
        }
        if ($request->hasFile('photo_wanita')) {
            $validated['photo_wanita'] = $request->file('photo_wanita')->store('photos', 'public');
        }
        if ($request->hasFile('cover_photo')) {
            $validated['cover_photo'] = $request->file('cover_photo')->store('photos', 'public');
        }

        // Perbarui data mempelai
        $mempelai->update(array_filter($validated));

        return response()->json([
            'message'  => 'Data mempelai berhasil diperbarui.',
            'mempelai' => $mempelai,
        ], 200);
    }


    public function storeStepThree(Request $request)
    {
        $user = auth('sanctum')->user() ?? User::find(session('user_id'));

        if (! $user) {
            return response()->json(['error' => 'User tidak ditemukan.'], 401);
        }

        $galeryPhoto = $request->hasFile('photo') ? $request->file('photo')->store('photos', 'public') : null;
        $galery      = Galery::updateOrCreate(
            ['user_id' => $user->id], [
                'photo'  => $galeryPhoto,
                'status' => $request->status,
            ]);

        Invitation::where('user_id', $user->id)->update(['status' => 'step2']);

        return response()->json([
            'message'           => 'Step 3 berhasil disimpan',
            'galery'            => $galery,
            'invitation_status' => 'step3',
        ], 200);
    }

    public function storeStepFor(Request $request)
    {

        $user = auth('sanctum')->user() ?? User::find(session('user_id'));

        if (! $user) {
            return response()->json(['error' => 'User tidak ditemukan.'], 401);
        }

        // Validasi input awal
        $title      = $request->input('title', []);
        $leadCerita = $request->input('lead_cerita', []);
        $tglCerita  = $request->input('tanggal_cerita', []);

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
            $cerita                 = new Cerita();
            $cerita->user_id        = $user->id;
            $cerita->title          = $title[$i];
            $cerita->lead_cerita    = $leadCerita[$i];
            $cerita->tanggal_cerita = $tglCerita[$i];
            $cerita->save();

            // Tambahkan ke array untuk response
            $savedCerita[] = [
                'title'          => $cerita->title,
                'lead_cerita'    => $cerita->lead_cerita,
                'tanggal_cerita' => $cerita->tanggal_cerita,
            ];
        }

        // Response sukses
        return response()->json([
            'data'    => $savedCerita,
            'message' => 'Step 4 berhasil disimpan',
        ]);
    }


    public function updateStepFor(Request $request)
{
    $user = User::find($request->user_id);

    if (!$user) {
        return response()->json(['error' => 'User tidak ditemukan.'], 404);
    }

    // Validasi input awal
    $title      = $request->input('title', []);
    $leadCerita = $request->input('lead_cerita', []);
    $tglCerita  = $request->input('tanggal_cerita', []);

    $count = count($title);

    // Validasi jumlah elemen pada input
    if (count($leadCerita) !== $count || count($tglCerita) !== $count) {
        return response()->json([
            'message' => 'Mismatch in the lead cerita data! All fields must have the same number of entries.',
        ], 400);
    }

    // Hapus semua cerita lama dari user_id ini
    Cerita::where('user_id', $user->id)->delete();

    $updatedCerita = [];

    for ($i = 0; $i < $count; $i++) {
        // Validasi per elemen
        if (empty($title[$i]) || empty($leadCerita[$i]) || empty($tglCerita[$i])) {
            return response()->json([
                'message' => 'Some required fields are missing for index ' . $i,
            ], 400);
        }

        // Simpan cerita baru
        $cerita                 = new Cerita();
        $cerita->user_id        = $user->id;
        $cerita->title          = $title[$i];
        $cerita->lead_cerita    = $leadCerita[$i];
        $cerita->tanggal_cerita = $tglCerita[$i];
        $cerita->save();

        // Tambahkan ke array response
        $updatedCerita[] = [
            'title'          => $cerita->title,
            'lead_cerita'    => $cerita->lead_cerita,
            'tanggal_cerita' => $cerita->tanggal_cerita,
        ];
    }

    return response()->json([
        'data'    => $updatedCerita,
        'message' => 'Step 4 berhasil diperbarui',
    ], 200);
}

}
