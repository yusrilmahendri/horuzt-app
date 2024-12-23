<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mempelai;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\Mempelai\MempelaiCollection;

class MempelaiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(){
        $userId = Auth::id();
        $mempelai = Mempelai::where('user_id', $userId)->get();
        return new MempelaiCollection($mempelai);
    }

    public function store(Request $request)
    {
        try {
            $userId = Auth::id();
            $existingMempelai = Mempelai::where('user_id', $userId)->first();

            $validatedData = $request->validate([
                'photo_pria' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'photo_wanita' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'name_lengkap_pria' => 'nullable|string|max:255',
                'name_lengkap_wanita' => 'nullable|string|max:255',
                'name_panggilan_pria' => 'nullable|string|max:255',
                'name_panggilan_wanita' => 'nullable|string|max:255',
                'ayah_pria' => 'nullable|string|max:255',
                'ayah_wanita' => 'nullable|string|max:255',
                'ibu_pria' => 'nullable|string|max:255',
                'ibu_wanita' => 'nullable|string|max:255',
            ]);

            $photoPriaPath = $request->hasFile('photo_pria') 
                ? $request->file('photo_pria')->store('photos', 'public') 
                : ($existingMempelai->photo_pria ?? null);

            $photoWanitaPath = $request->hasFile('photo_wanita') 
                ? $request->file('photo_wanita')->store('photos', 'public') 
                : ($existingMempelai->photo_wanita ?? null);

            if ($existingMempelai) {
                $existingMempelai->update(array_merge($validatedData, [
                    'photo_pria' => $photoPriaPath,
                    'photo_wanita' => $photoWanitaPath,
                ]));

                return response()->json([
                    'message' => 'Data tambahan mempelai berhasil disimpan',
                    'data' => $existingMempelai,
                ], 200);
            } else {
                $mempelai = Mempelai::create(array_merge($validatedData, [
                    'user_id' => $userId,
                    'photo_pria' => $photoPriaPath,
                    'photo_wanita' => $photoWanitaPath,
                ]));

                return response()->json([
                    'message' => 'Data Mempelai berhasil disimpan.',
                    'data' => $mempelai,
                ], 201);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function storeMempelai(Request $request)
    {
        try {
            $userId = Auth::id();
            $existingMempelai = Mempelai::where('user_id', $userId)->first();

            $validatedData = $request->validate([
                'cover_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'urutan_mempelai' => 'nullable|string|max:255',
            ]);

            $coverPhotoPath = $request->hasFile('cover_photo')
                ? $request->file('cover_photo')->store('photos', 'public')
                : ($existingMempelai->cover_photo ?? null);

            if ($existingMempelai) {
                $existingMempelai->update([
                    'cover_photo' => $coverPhotoPath,
                    'urutan_mempelai' => $validatedData['urutan_mempelai'] ?? $existingMempelai->urutan_mempelai,
                ]);

                return response()->json([
                    'message' => 'Data tambahan Mempelai berhasil disimpan.',
                    'data' => $existingMempelai,
                ], 200);
            } else {
                $mempelai = Mempelai::create([
                    'user_id' => $userId,
                    'cover_photo' => $coverPhotoPath,
                    'urutan_mempelai' => $validatedData['urutan_mempelai'] ?? null,
                ]);

                return response()->json([
                    'message' => 'Data baru Mempelai berhasil disimpan.',
                    'data' => $mempelai,
                ], 201);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateMempelai(Request $request, $id)
    {
        try {
            $mempelai = Mempelai::findOrFail($id);

            $validatedData = $request->validate([
                'photo_pria' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'photo_wanita' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'name_lengkap_pria' => 'nullable|string|max:255',
                'name_lengkap_wanita' => 'nullable|string|max:255',
                'name_panggilan_pria' => 'nullable|string|max:255',
                'name_panggilan_wanita' => 'nullable|string|max:255',
                'ayah_pria' => 'nullable|string|max:255',
                'ayah_wanita' => 'nullable|string|max:255',
                'ibu_pria' => 'nullable|string|max:255',
                'ibu_wanita' => 'nullable|string|max:255',
            ]);

            if ($request->hasFile('photo_pria')) {
                $validatedData['photo_pria'] = $request->file('photo_pria')->store('photos', 'public');
            }

            if ($request->hasFile('photo_wanita')) {
                $validatedData['photo_wanita'] = $request->file('photo_wanita')->store('photos', 'public');
            }

            $mempelai->update($validatedData);

            return response()->json([
                'message' => 'Data Mempelai berhasil diperbarui.',
                'data' => $mempelai,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateCoverMempelai(Request $request, $id)
    {
        try {
            $mempelai = Mempelai::findOrFail($id);

            $validatedData = $request->validate([
                'cover_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'urutan_mempelai' => 'nullable|string|max:255',
            ]);

            if ($request->hasFile('cover_photo')) {
                $validatedData['cover_photo'] = $request->file('cover_photo')->store('photos', 'public');
            }

            $mempelai->update($validatedData);

            return response()->json([
                'message' => 'Cover Mempelai berhasil diperbarui.',
                'data' => $mempelai,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui cover.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
