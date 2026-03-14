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
                    $errors = [];

                    if (User::where('email', $validated['email'])->where('id', '!=', $user->id)->exists()) {
                        $errors['email'][] = 'Email sudah digunakan oleh pengguna lain';
                    }

                    if (Setting::where('domain', $validated['domain'])->where('user_id', '!=', $user->id)->exists()) {
                        $errors['domain'][] = 'Domain sudah digunakan oleh pengguna lain';
                    }

                    if (!empty($errors)) {
                        return response()->json([
                            'message' => 'Validasi gagal',
                            'errors' => $errors,
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

                    // Get package details for snapshot
                    $paketUndangan = \App\Models\PaketUndangan::find($validated['paket_undangan_id']);
                    if (!$paketUndangan) {
                        return response()->json([
                            'message' => 'Paket undangan tidak ditemukan',
                        ], 422);
                    }

                    $invitation = Invitation::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'status'            => 'step1',
                            'paket_undangan_id' => $validated['paket_undangan_id'],
                            'kode_pemesanan'    => $user->kode_pemesanan,
                            'payment_status'    => 'pending',
                            // Capture package snapshot to preserve original terms
                            'package_price_snapshot' => $paketUndangan->price,
                            'package_duration_snapshot' => $paketUndangan->masa_aktif,
                            'package_features_snapshot' => [
                                'jenis_paket' => $paketUndangan->jenis_paket,
                                'name_paket' => $paketUndangan->name_paket,
                                'halaman_buku' => $paketUndangan->halaman_buku,
                                'kirim_wa' => $paketUndangan->kirim_wa,
                                'bebas_pilih_tema' => $paketUndangan->bebas_pilih_tema,
                                'kirim_hadiah' => $paketUndangan->kirim_hadiah,
                                'import_data' => $paketUndangan->import_data,
                                'snapshot_at' => now()->toISOString()
                            ]
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
                    // Check for authenticated user via Sanctum (user refreshing after registration)
                    $authUser = Auth::guard('sanctum')->user();

                    if ($authUser) {
                        // User is authenticated - check if email/domain belongs to them
                        $errors = [];

                        if (User::where('email', $validated['email'])->where('id', '!=', $authUser->id)->exists()) {
                            $errors['email'][] = 'Email sudah digunakan oleh pengguna lain';
                        }

                        if (Setting::where('domain', $validated['domain'])->where('user_id', '!=', $authUser->id)->exists()) {
                            $errors['domain'][] = 'Domain sudah digunakan oleh pengguna lain';
                        }

                        if (!empty($errors)) {
                            return response()->json([
                                'message' => 'Validasi gagal',
                                'errors' => $errors,
                            ], 422);
                        }

                        // Update existing authenticated user
                        $user = $authUser;
                        $user->update([
                            'email'    => $validated['email'],
                            'password' => Hash::make($validated['password']),
                            'phone'    => $validated['phone'],
                        ]);

                        // Update or create domain
                        $domain = Setting::updateOrCreate(
                            ['user_id' => $user->id],
                            ['domain' => $validated['domain']]
                        );

                        // Get package details for snapshot
                        $paketUndangan = \App\Models\PaketUndangan::find($validated['paket_undangan_id']);
                        if (!$paketUndangan) {
                            return response()->json([
                                'message' => 'Paket undangan tidak ditemukan',
                            ], 422);
                        }

                        $invitation = Invitation::updateOrCreate(
                            ['user_id' => $user->id],
                            [
                                'status'            => 'step1',
                                'paket_undangan_id' => $validated['paket_undangan_id'],
                                'kode_pemesanan'    => $user->kode_pemesanan,
                                'payment_status'    => 'pending',
                                // Capture package snapshot to preserve original terms
                                'package_price_snapshot' => $paketUndangan->price,
                                'package_duration_snapshot' => $paketUndangan->masa_aktif,
                                'package_features_snapshot' => [
                                    'jenis_paket' => $paketUndangan->jenis_paket,
                                    'name_paket' => $paketUndangan->name_paket,
                                    'halaman_buku' => $paketUndangan->halaman_buku,
                                    'kirim_wa' => $paketUndangan->kirim_wa,
                                    'bebas_pilih_tema' => $paketUndangan->bebas_pilih_tema,
                                    'kirim_hadiah' => $paketUndangan->kirim_hadiah,
                                    'import_data' => $paketUndangan->import_data,
                                    'snapshot_at' => now()->toISOString()
                                ]
                            ]
                        );

                        // Refresh token
                        $user->tokens()->delete();
                        $token = $user->createToken('auth_token')->plainTextToken;

                        return response()->json([
                            'message'    => 'Step 1 berhasil diperbarui',
                            'user'       => $user,
                            'token'      => $token,
                            'user_id'    => $user->id,
                            'domain'     => $domain,
                            'invitation' => $invitation,
                        ]);
                    }

                    // New user - validate uniqueness
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

                    // Get package details for snapshot
                    $paketUndangan = \App\Models\PaketUndangan::find($validated['paket_undangan_id']);
                    if (!$paketUndangan) {
                        return response()->json([
                            'message' => 'Paket undangan tidak ditemukan',
                        ], 422);
                    }

                    $invitation = Invitation::create([
                        'status'            => 'step1',
                        'paket_undangan_id' => $validated['paket_undangan_id'],
                        'user_id'           => $user->id,
                        'kode_pemesanan'    => $user->kode_pemesanan,
                        'payment_status'    => 'pending',
                        // Capture package snapshot to preserve original terms
                        'package_price_snapshot' => $paketUndangan->price,
                        'package_duration_snapshot' => $paketUndangan->masa_aktif,
                        'package_features_snapshot' => [
                            'jenis_paket' => $paketUndangan->jenis_paket,
                            'name_paket' => $paketUndangan->name_paket,
                            'halaman_buku' => $paketUndangan->halaman_buku,
                            'kirim_wa' => $paketUndangan->kirim_wa,
                            'bebas_pilih_tema' => $paketUndangan->bebas_pilih_tema,
                            'kirim_hadiah' => $paketUndangan->kirim_hadiah,
                            'import_data' => $paketUndangan->import_data,
                            'snapshot_at' => now()->toISOString()
                        ]
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
        try {
            $validated = $request->validate([
                'user_id'               => 'required|exists:users,id',
                'photo_pria'            => 'nullable|image|mimes:jpeg,png,jpg|max:5222',
                'photo_wanita'          => 'nullable|image|mimes:jpeg,png,jpg|max:5222',
                'cover_photo'           => 'nullable|image|mimes:jpeg,png,jpg|max:5222',
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

            return DB::transaction(function () use ($validated, $request, $user) {
                // Store photos in Gallery table with active status (requirement)
                $galleryPhotos = [];
                $mempelaiPhotos = [];

                if ($request->hasFile('photo_pria')) {
                    $photoPath = $request->file('photo_pria')->store('photos', 'public');

                    // Store in Gallery with status 1 (active)
                    $galleryPhoto = Galery::create([
                        'user_id' => $user->id,
                        'photo' => $photoPath,
                        'nama_foto' => 'Photo Pria',
                        'status' => 1 // Active status for frontend filtering
                    ]);

                    $galleryPhotos['photo_pria'] = $galleryPhoto;
                    $mempelaiPhotos['photo_pria'] = $photoPath;
                }

                if ($request->hasFile('photo_wanita')) {
                    $photoPath = $request->file('photo_wanita')->store('photos', 'public');

                    // Store in Gallery with status 1 (active)
                    $galleryPhoto = Galery::create([
                        'user_id' => $user->id,
                        'photo' => $photoPath,
                        'nama_foto' => 'Photo Wanita',
                        'status' => 1 // Active status for frontend filtering
                    ]);

                    $galleryPhotos['photo_wanita'] = $galleryPhoto;
                    $mempelaiPhotos['photo_wanita'] = $photoPath;
                }

                if ($request->hasFile('cover_photo')) {
                    $photoPath = $request->file('cover_photo')->store('photos', 'public');

                    // Store in Gallery with status 1 (active)
                    $galleryPhoto = Galery::create([
                        'user_id' => $user->id,
                        'photo' => $photoPath,
                        'nama_foto' => 'Cover Photo',
                        'status' => 1 // Active status for frontend filtering
                    ]);

                    $galleryPhotos['cover_photo'] = $galleryPhoto;
                    $mempelaiPhotos['cover_photo'] = $photoPath;
                }

                // Update Mempelai with photo references and other data
                $mempelai = Mempelai::updateOrCreate(
                    ['user_id' => $user->id],
                    array_merge([
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
                    ], $mempelaiPhotos)
                );

                // Update invitation status
                Invitation::where('user_id', $user->id)->update(['status' => 'step2']);

                return response()->json([
                    'message'           => 'Step 2 berhasil disimpan.',
                    'mempelai'          => $mempelai,
                    'gallery_photos'    => $galleryPhotos,
                    'invitation_status' => 'step2',
                ], 200);
            });

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error di storeStepTwo: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal menyimpan data',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    public function storeStepThree(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'photo'   => 'nullable|image|mimes:jpeg,png,jpg|max:5222',
                'status'  => 'nullable|string|in:0,1',
            ]);

            $user = User::find($validated['user_id']);

            if (! $user) {
                return response()->json(['error' => 'User tidak ditemukan.'], 404);
            }

            $galery = null;

            // Only create gallery entry if photo is provided
            if ($request->hasFile('photo')) {
                $galeryPhoto = $request->file('photo')->store('photos', 'public');

                // Create new gallery entry (not update) to allow multiple photos
                $galery = Galery::create([
                    'user_id' => $user->id,
                    'photo'   => $galeryPhoto,
                    'nama_foto' => 'Gallery Upload Step 3',
                    'status'  => $validated['status'] ?? 1,
                ]);
            }

            // Update invitation status
            Invitation::where('user_id', $user->id)->update(['status' => 'step3']);

            return response()->json([
                'message'           => 'Step 3 berhasil disimpan',
                'galery'            => $galery,
                'invitation_status' => 'step3',
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error di storeStepThree: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal menyimpan data',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    public function storeStepFor(Request $request)
    {

        $validated = $request->validate([
            'user_id'        => 'required|exists:users,id',
            'title'          => 'required|array',
            'lead_cerita'    => 'required|array',
            'tanggal_cerita' => 'required|array',
            'status'         => 'nullable|string|in:0,1',
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

        // Update filter_undangan.halaman_cerita based on status
        // status = '0' means checkbox is checked (user wants to disable the feature)
        // status = '1' or null means checkbox is unchecked (feature remains active)
        $statusValue = $request->input('status', '1');
        $halamanCeritaValue = ($statusValue === '0') ? 0 : 1;

        $filterUndangan = \App\Models\FilterUndangan::where('user_id', $user->id)->first();

        if ($filterUndangan) {
            // Update existing record - only change halaman_cerita
            $filterUndangan->update(['halaman_cerita' => $halamanCeritaValue]);
        } else {
            // Create new record with all default values
            \App\Models\FilterUndangan::create([
                'user_id'           => $user->id,
                'halaman_sampul'    => 1,
                'halaman_mempelai'  => 1,
                'halaman_acara'     => 1,
                'halaman_ucapan'    => 1,
                'halaman_galery'    => 1,
                'halaman_cerita'    => $halamanCeritaValue,
                'halaman_lokasi'    => 1,
                'halaman_prokes'    => 1,
                'halaman_send_gift' => 1,
                'halaman_qoute'     => 1,
            ]);
        }

        return response()->json([
            'data'    => $savedCerita,
            'message' => 'Step 4 berhasil disimpan',
        ]);
    }

}
