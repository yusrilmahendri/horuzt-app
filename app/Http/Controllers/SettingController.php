<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\FilterUndangan;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

       public function storeDomainToken(Request $request)
    {
        $user = Auth::user();

        $validatedData = $request->validate([
            'domain' => 'nullable|string|max:255',
            'token' => 'nullable|string|max:255',
        ]);

        $setting = Setting::updateOrCreate(
            ['user_id' => $user->id],
            $validatedData
        );

        if ($setting) {
            return response()->json([
                'Message' => 'Data berhasil disimpan.',
                'testimoni' => $setting
            ], 200);
        } else {
            return response()->json([
                'Message' => 'Data gagal disimpan!'
            ], 500);
        }
    }

    public function storeMusic(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'musik' => 'required|file|mimes:mp3,wav,ogg|max:10240',
            ]);

            if ($request->hasFile('musik')) {
                $file = $request->file('musik');
                $filePath = $file->store('public/music');

                // Save the file path to the database
                $user = Auth::user();
                $setting = Setting::updateOrCreate(
                    ['user_id' => $user->id],
                    ['musik' => $filePath]
                );

                return response()->json([
                    'Message' => 'Music file uploaded successfully.',
                    'file_path' => $filePath,
                    'testimoni' => $setting,
                ], 200);
            } else {
                return response()->json(['error' => 'No file uploaded'], 400);
            }
        } catch (\Exception $e) {
            \Log::error('File upload failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to upload music file.', 'details' => $e->getMessage()], 500);
        }
    }

     public function storeSalam(Request $request)
    {
        $validatedData = $request->validate([
            'salam_pembuka' => 'nullable|string',
            'salam_atas' => 'nullable|string',
            'salam_bawah' => 'nullable|string',
        ]);

        $user = Auth::user();

        $setting = Setting::updateOrCreate(
            ['user_id' => $user->id],
            $validatedData
        );

        if ($setting) {
            return response()->json([
                'Message' => 'Data berhasil disimpan.',
                'testimoni' => $setting
            ], 200);
        } else {
            return response()->json([
                'Message' => 'Data gagal disimpan!'
            ], 500);
        }
    }


    public function downloadMusic($id)
    {
        $setting = Setting::findOrFail($id);

        if ($setting->musik) {
            $filePath = storage_path('app/public/' . $setting->musik);

            if (file_exists($filePath)) {
                return response()->download($filePath);
            }

            return response()->json(['error' => 'File not found.'], 404);
        }

        return response()->json(['error' => 'No music file associated with this setting.'], 404);
    }

    public function streamMusic($id)
    {
        $setting = Setting::findOrFail($id);

        if ($setting->musik) {
            $filePath = storage_path('app/public/' . $setting->musik);

            if (file_exists($filePath)) {
                $mimeType = mime_content_type($filePath);
                return response()->file($filePath, [
                    'Content-Type' => $mimeType,
                ]);
            }

            return response()->json(['error' => 'File not found.'], 404);
        }

        return response()->json(['error' => 'No music file associated with this setting.'], 404);
    }

    public function create(Request $request)
    {
        $user = Auth::user(); // Mendapatkan user yang sedang login

        // Tentukan nilai default jika tidak dikirim dari request
        $defaultData = [
            'halaman_sampul' => $request->input('halaman_sampul', 0),
            'halaman_mempelai' => $request->input('halaman_mempelai', 0),
            'halaman_acara' => $request->input('halaman_acara', 0),
            'halaman_ucapan' => $request->input('halaman_ucapan', 0),
            'halaman_galery' => $request->input('halaman_galery', 0),
            'halaman_cerita' => $request->input('halaman_cerita', 0),
            'halaman_lokasi' => $request->input('halaman_lokasi', 0),
            'halaman_prokes' => $request->input('halaman_prokes', 0),
            'halaman_send_gift' => $request->input('halaman_send_gift', 0),
            'halaman_qoute' => $request->input('halaman_qoute', 0),
        ];

        // Gunakan firstOrCreate untuk memastikan tidak ada duplikasi
        $filterUndangan = FilterUndangan::firstOrCreate(
            ['user_id' => $user->id], // Kondisi pencarian
            $defaultData // Data default untuk dibuat
        );

        return response()->json([
            'message' => $filterUndangan->wasRecentlyCreated
                ? 'Data berhasil dibuat.'
                : 'Data sudah ada sebelumnya.',
            'data' => $filterUndangan
        ], $filterUndangan->wasRecentlyCreated ? 201 : 200);
    }



    public function update(Request $request)
    {
        $user = Auth::user();

        $filterUndangan = FilterUndangan::where('user_id', $user->id)->first();

        if (!$filterUndangan) {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        }

        $filterUndangan->forceFill([
            'halaman_sampul' => $request->halaman_sampul,
            'halaman_mempelai' => $request->halaman_mempelai,
            'halaman_acara' => $request->halaman_acara,
            'halaman_ucapan' => $request->halaman_ucapan,
            'halaman_galery' => $request->halaman_galery,
            'halaman_cerita' => $request->halaman_cerita,
            'halaman_lokasi' => $request->halaman_lokasi,
            'halaman_prokes' => $request->halaman_prokes,
            'halaman_send_gift' => $request->halaman_send_gift,
            'halaman_qoute' => $request->halaman_qoute,
        ])->save();

        return response()->json([
            'message' => 'Data berhasil diperbarui.',
            'data' => $filterUndangan
        ], 200);
    }



}
