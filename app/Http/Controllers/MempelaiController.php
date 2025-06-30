<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mempelai;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Http\Resources\Mempelai\MempelaiCollection;
use Illuminate\Support\Facades\Storage;

class MempelaiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }


    private function transformPhotoUrls($mempelai)
    {
        $mempelai->photo_pria = $mempelai->photo_pria ? url('storage/' . $mempelai->photo_pria) : null;
        $mempelai->photo_wanita = $mempelai->photo_wanita ? url('storage/' . $mempelai->photo_wanita) : null;
        $mempelai->cover_photo = $mempelai->cover_photo ? url('storage/' . $mempelai->cover_photo) : null;

        return $mempelai;
    }

    public function index()
    {
        $userId = Auth::id();
        $mempelai = Mempelai::where('user_id', $userId)->get();


        $mempelai = $mempelai->map(function ($item) {
            return $this->transformPhotoUrls($item);
        });

        return new MempelaiCollection($mempelai);
    }

    public function update(Request $request)
{
    try {
        $userId = Auth::id();


        $validated = $request->validate([
            'cover_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'urutan_mempelai' => 'nullable|string|in:pria,wanita',
            'photo_pria' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'photo_wanita' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'name_lengkap_pria' => 'nullable|string|max:255',
            'name_lengkap_wanita' => 'nullable|string|max:255',
            'name_panggilan_pria' => 'nullable|string|max:255',
            'name_panggilan_wanita' => 'nullable|string|max:255',
            'ayah_pria' => 'nullable|string|max:255',
            'ayah_wanita' => 'nullable|string|max:255',
            'ibu_pria' => 'nullable|string|max:255',
            'ibu_wanita' => 'nullable|string|max:255',
        ]);


        $mempelai = Mempelai::where('user_id', $userId)->first();

        if (!$mempelai) {
            return response()->json([
                'message' => 'Data mempelai tidak ditemukan',
            ], 404);
        }


        $updateData = [];


        if ($request->hasFile('cover_photo')) {

            if ($mempelai->cover_photo && Storage::exists('public/' . $mempelai->cover_photo)) {
                Storage::delete('public/' . $mempelai->cover_photo);
            }
            $updateData['cover_photo'] = $request->file('cover_photo')->store('photos', 'public');
        }

        if ($request->hasFile('photo_pria')) {

            if ($mempelai->photo_pria && Storage::exists('public/' . $mempelai->photo_pria)) {
                Storage::delete('public/' . $mempelai->photo_pria);
            }
            $updateData['photo_pria'] = $request->file('photo_pria')->store('photos', 'public');
        }

        if ($request->hasFile('photo_wanita')) {

            if ($mempelai->photo_wanita && Storage::exists('public/' . $mempelai->photo_wanita)) {
                Storage::delete('public/' . $mempelai->photo_wanita);
            }
            $updateData['photo_wanita'] = $request->file('photo_wanita')->store('photos', 'public');
        }


        $textFields = [
            'urutan_mempelai',
            'name_lengkap_pria',
            'name_lengkap_wanita',
            'name_panggilan_pria',
            'name_panggilan_wanita',
            'ayah_pria',
            'ayah_wanita',
            'ibu_pria',
            'ibu_wanita'
        ];

        foreach ($textFields as $field) {
            if ($request->has($field)) {
                $updateData[$field] = $validated[$field];
            }
        }


        $mempelai->update($updateData);


        $mempelai->refresh();


        $mempelai = $this->transformPhotoUrls($mempelai);

        return response()->json([
            'message' => 'Data mempelai berhasil diperbarui',
            'data' => $mempelai,
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Validasi gagal',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Terjadi kesalahan server',
            'error' => $e->getMessage(),
        ], 500);
    }
}


    public function updateStatusBayar(Request $request)
    {
        try {

            $validated = $request->validate([
                'user_id'        => 'required|exists:users,id',
                'kode_pemesanan' => 'required|exists:users,kode_pemesanan',
            ]);


            $user = User::where('kode_pemesanan', $validated['kode_pemesanan'])->first();


            if (!$user) {
                return response()->json([
                    'message' => 'Kode pemesanan tidak valid atau tidak ditemukan',
                ], 404);
            }


            if ($user->id != $validated['user_id']) {
                return response()->json([
                    'message' => 'User ID tidak cocok dengan kode pemesanan',
                ], 400);
            }


            $mempelai = Mempelai::where('user_id', $user->id)->first();


            if (!$mempelai) {
                return response()->json([
                    'message' => 'Data mempelai tidak ditemukan untuk user ini',
                ], 404);
            }


            $mempelai->update([
                'status'    => 'Sudah Bayar',
                'kd_status' => 'SB',
            ]);

            return response()->json([
                'message'   => 'Status berhasil diperbarui',
                'mempelai'  => $mempelai,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Validasi gagal',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Terjadi kesalahan server',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


}
