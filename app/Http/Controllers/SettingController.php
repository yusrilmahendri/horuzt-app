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
    // Retrieve the raw uploaded file
    $file = $request->file('musik');

    if ($file) {
        \Log::info('File detected:', [
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json([
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);
    } else {
        \Log::info('No file detected in the request');
        return response()->json(['error' => 'No file uploaded'], 400);
    }
}



    public function storeMusics(Request $request)
    {   
         if ($request->hasFile('musik')) {
        $file = $request->file('musik');
        return response()->json([
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);
    } else {
        return response()->json(['error' => 'No file uploaded'], 400);
    }

        // $user = Auth::user();

        // // Validate the uploaded file
        // $validatedData = $request->validate([
        //     'musik' => 'nullable|file|mimes:mp3,wav,aac,ogg,flac,m4a,wma,aiff|max:10240', // Up to 10MB
        // ]);

        // // Check if the setting exists or create a new one
        // $setting = Setting::firstOrNew(['user_id' => $user->id]);

        // if ($request->hasFile('musik')) {
        //     $file = $request->file('musik');
        //     $filename = time() . '_' . $file->getClientOriginalName(); // Generate a unique filename
        //     $path = $file->storeAs('/musik', $filename, 'public'); // Save file to 'storage/app/public/uploads/music'
        //     $setting->musik = $path; // Save the file path to the database
        // }

        // if ($setting->save()) {
        //     return response()->json([
        //         'Message' => 'Music file successfully uploaded and saved.',
        //         'data' => $setting,
        //     ], 200);
        // } else {
        //     return response()->json([
        //         'Message' => 'Failed to save the music file!',
        //     ], 500);
        // }
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
