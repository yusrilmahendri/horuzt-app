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

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function masterTagihan()
    {
        $data = MetodeTransaction::get();
        return new TagihanTransactionCollection($data);
    }


    public function storeStepOne(Request $request)
    {
        try {

            $validated = $request->validate([
                'kode_pemesanan'     => 'nullable|string',
                'email'              => 'required|email',
                'password'           => 'required|min:6',
                'phone'              => 'required|string',
                'paket_undangan_id'  => 'required|exists:paket_undangans,id',
                'domain'             => 'required|string',
            ]);

            return DB::transaction(function () use ($validated, $request) {

                $user = null;
                if (!empty($validated['kode_pemesanan'])) {
                    $user = User::where('kode_pemesanan', $validated['kode_pemesanan'])->first();
                }

                if ($user) {

                    if (User::where('email', $validated['email'])->where('id', '!=', $user->id)->exists()) {
                        return response()->json([
                            'message' => 'Email sudah digunakan oleh pengguna lain',
                        ], 422);
                    }

                    if (Setting::where('domain', $validated['domain'])->where('user_id', '!=', $user->id)->exists()) {
                        return response()->json([
                            'message' => 'Domain sudah digunakan oleh pengguna lain',
                        ], 422);
                    }


                    $user->update([
                        'email'    => $validated['email'],
                        'password' => Hash::make($validated['password']),
                        'phone'    => $validated['phone'],
                    ]);


                    $domain = Setting::updateOrCreate(
                        ['user_id' => $user->id],
                        ['domain' => $validated['domain']]
                    );


                    $invitation = Invitation::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'status'            => 'step1',
                            'paket_undangan_id' => $validated['paket_undangan_id'],
                        ]
                    );
                    $token = $user->createToken('auth_token')->plainTextToken;
                    return response()->json([
                        'message'    => 'Step 1 berhasil diperbarui',
                        'user'       => $user,
                        'token'      => $token,
                        'user_id'    => $user->id,
                        'domain'     => $domain,
                        'invitation' => $invitation,
                    ]);
                } else {

                    $request->validate([
                        'email'  => 'unique:users,email',
                        'domain' => 'unique:settings,domain',
                    ]);


                    $user = User::create([
                        'email'          => $validated['email'],
                        'password'       => Hash::make($validated['password']),
                        'phone'          => $validated['phone'],
                        'kode_pemesanan' => '#' . mt_rand(1000000000, 9999999999),
                    ]);

                    if (method_exists($user, 'assignRole')) {
                        $user->assignRole('user');
                    }

                    $token = $user->createToken('auth_token')->plainTextToken;

                    $domain = Setting::create([
                        'user_id' => $user->id,
                        'domain'  => $validated['domain'],
                    ]);

                    $invitation = Invitation::create([
                        'status'            => 'step1',
                        'paket_undangan_id' => $validated['paket_undangan_id'],
                        'user_id'           => $user->id,
                    ]);

                    return response()->json([
                        'message'    => 'Step 1 berhasil',
                        'user'       => $user,
                        'token'      => $token,
                        'user_id'    => $user->id,
                        'domain'     => $domain,
                        'invitation' => $invitation,
                    ], 201);
                }
            });

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error di storeStepOne: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal menyimpan data',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function storeStepTwo(Request $request)
    {

        $validated = $request->validate([
            'user_id'               => 'required|exists:users,id',
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
        ]);


        $user = User::find($validated['user_id']);

        if (! $user) {
            return response()->json(['error' => 'User tidak ditemukan.'], 404);
        }


        $photoPria   = $request->hasFile('photo_pria') ? $request->file('photo_pria')->store('photos', 'public') : null;
        $photoWanita = $request->hasFile('photo_wanita') ? $request->file('photo_wanita')->store('photos', 'public') : null;
        $coverPhoto  = $request->hasFile('cover_photo') ? $request->file('cover_photo')->store('photos', 'public') : null;


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
                'status'                => 'Menunggu Konfirmasi',
                'kd_status'             => 'MK',
            ]
        );


        Invitation::where('user_id', $user->id)->update(['status' => 'step2']);

        return response()->json([
            'message'           => 'Step 2 berhasil disimpan.',
            'mempelai'          => $mempelai,
            'invitation_status' => 'step2',
        ], 200);
    }


    public function storeStepThree(Request $request)
    {

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'photo'   => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'status'  => 'nullable|string|max:255',
        ]);


        $user = User::find($validated['user_id']);

        if (! $user) {
            return response()->json(['error' => 'User tidak ditemukan.'], 404);
        }


        $galeryPhoto = $request->hasFile('photo') ? $request->file('photo')->store('photos', 'public') : null;


        $galery = Galery::updateOrCreate(
            ['user_id' => $user->id],
            [
                'photo'  => $galeryPhoto,
                'status' => $validated['status'] ?? null,
            ]
        );


        Invitation::where('user_id', $user->id)->update(['status' => 'step3']);

        return response()->json([
            'message'           => 'Step 3 berhasil disimpan',
            'galery'            => $galery,
            'invitation_status' => 'step3',
        ], 200);
    }


    public function storeStepFor(Request $request)
    {

        $validated = $request->validate([
            'user_id'        => 'required|exists:users,id',
            'title'          => 'required|array',
            'lead_cerita'    => 'required|array',
            'tanggal_cerita' => 'required|array',
        ]);

        $user = User::find($validated['user_id']);

        if (! $user) {
            return response()->json(['error' => 'User tidak ditemukan.'], 404);
        }

        $title      = $validated['title'];
        $leadCerita = $validated['lead_cerita'];
        $tglCerita  = $validated['tanggal_cerita'];

        $count = count($title);

        if (count($leadCerita) !== $count || count($tglCerita) !== $count) {
            return response()->json([
                'message' => 'Mismatch in the lead cerita data! All fields must have the same number of entries.',
            ], 400);
        }

        $savedCerita = [];

        for ($i = 0; $i < $count; $i++) {
            if (empty($title[$i]) || empty($leadCerita[$i]) || empty($tglCerita[$i])) {
                return response()->json([
                    'message' => 'Some required fields are missing for index ' . $i,
                ], 400);
            }

            $cerita = new Cerita();
            $cerita->user_id        = $user->id;
            $cerita->title          = $title[$i];
            $cerita->lead_cerita    = $leadCerita[$i];
            $cerita->tanggal_cerita = $tglCerita[$i];
            $cerita->save();

            $savedCerita[] = [
                'title'          => $cerita->title,
                'lead_cerita'    => $cerita->lead_cerita,
                'tanggal_cerita' => $cerita->tanggal_cerita,
            ];
        }

        return response()->json([
            'data'    => $savedCerita,
            'message' => 'Step 4 berhasil disimpan',
        ]);
    }

}
