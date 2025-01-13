<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

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
}
