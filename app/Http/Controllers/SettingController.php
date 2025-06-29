<?php
namespace App\Http\Controllers;

use App\Models\FilterUndangan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index()
    {
        $user = Auth::user();

        $setting        = Setting::where('user_id', $user->id)->first();
        $filterUndangan = FilterUndangan::where('user_id', $user->id)->first();

        if (! $setting && ! $filterUndangan) {
            return response()->json(['message' => 'Data setting dan filter undangan tidak ditemukan.'], 404);
        }

        return response()->json([
            'message'         => 'Data ditemukan.',
            'setting'         => $setting,
            'filter_undangan' => $filterUndangan,
        ], 200);
    }

    public function storeDomainToken(Request $request)
    {
        $user = Auth::user();

        $validatedData = $request->validate([
            'domain' => 'nullable|string|max:255',
            'token'  => 'nullable|string|max:255',
        ]);

        $setting = Setting::updateOrCreate(
            ['user_id' => $user->id],
            $validatedData
        );

        if ($setting) {
            return response()->json([
                'Message'   => 'Data berhasil disimpan.',
                'testimoni' => $setting,
            ], 200);
        } else {
            return response()->json([
                'Message' => 'Data gagal disimpan!',
            ], 500);
        }
    }

    public function storeMusic(Request $request)
    {
        try {

            $request->validate([
                'musik' => 'required|file|mimes:mp3,wav,ogg|max:10240',
            ]);

            $user = Auth::user();

            $existingSetting = Setting::where('user_id', $user->id)->first();
            if ($existingSetting && $existingSetting->musik) {
                Storage::delete($existingSetting->musik);
            }

            $musicFile = $request->file('musik');
            $fileName  = time() . '_' . $musicFile->getClientOriginalName();
            $filePath  = $musicFile->storeAs('public/music', $fileName);

            $setting = Setting::updateOrCreate(
                ['user_id' => $user->id],
                ['musik' => $filePath]
            );

            return response()->json([
                'message'   => 'Music file uploaded successfully.',
                'file_path' => Storage::url($filePath),
                'setting'   => $setting,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Music upload failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to upload music file.'], 500);
        }
    }

    public function deleteMusic()
    {
        try {
            $user    = Auth::user();
            $setting = Setting::where('user_id', $user->id)->first();

            if (! $setting) {
                return response()->json(['message' => 'Setting not found.'], 404);
            }

            if (! $setting->musik) {
                return response()->json(['message' => 'No music file to delete.'], 404);
            }

            Storage::delete($setting->musik);

            $setting->update(['musik' => null]);

            return response()->json([
                'message' => 'Music file deleted successfully.',
                'setting' => $setting,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Music deletion failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to delete music file.'], 500);
        }
    }

    public function getMusic(Request $request)
    {
        try {

            $request->validate([
                'id' => 'required|integer|exists:settings,id',
            ]);

            $id      = $request->query('id');
            $setting = Setting::findOrFail($id);

            if (! $setting->musik) {
                return response()->json(['message' => 'No music file associated with this setting.'], 404);
            }

            $filePath = storage_path('app/' . $setting->musik);

            if (! file_exists($filePath)) {
                return response()->json(['message' => 'Music file not found.'], 404);
            }

            $mimeType = mime_content_type($filePath);
            return response()->file($filePath, [
                'Content-Type'        => $mimeType,
                'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Invalid ID provided',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Setting not found.'], 404);
        } catch (\Exception $e) {
            Log::error('Error retrieving music file', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve music file.'], 500);
        }
    }

    public function storeSalam(Request $request)
    {
        $validatedData = $request->validate([
            'salam_pembuka' => 'nullable|string',
            'salam_atas'    => 'nullable|string',
            'salam_bawah'   => 'nullable|string',
        ]);

        $user = Auth::user();

        $setting = Setting::updateOrCreate(
            ['user_id' => $user->id],
            $validatedData
        );

        if ($setting) {
            return response()->json([
                'Message'   => 'Data berhasil disimpan.',
                'testimoni' => $setting,
            ], 200);
        } else {
            return response()->json([
                'Message' => 'Data gagal disimpan!',
            ], 500);
        }
    }

    public function downloadMusic(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer|exists:settings,id',
            ]);

            $id      = $request->query('id');
            $user    = Auth::user();
            $setting = Setting::findOrFail($id);

            if ($setting->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized access to this resource.'], 403);
            }

            if (! $setting->musik) {
                return response()->json(['message' => 'No music file associated with this setting.'], 404);
            }

            $filePath = storage_path('app/' . $setting->musik);

            if (! file_exists($filePath)) {
                return response()->json(['message' => 'Music file not found.'], 404);
            }

            return response()->download($filePath, basename($filePath));

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Invalid ID provided',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Setting not found.'], 404);
        } catch (\Exception $e) {
            Log::error('Music download failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to download music file.'], 500);
        }
    }

    public function create(Request $request)
    {
        $user = Auth::user();

        $defaultData = [
            'halaman_sampul'    => $request->input('halaman_sampul', 0),
            'halaman_mempelai'  => $request->input('halaman_mempelai', 0),
            'halaman_acara'     => $request->input('halaman_acara', 0),
            'halaman_ucapan'    => $request->input('halaman_ucapan', 0),
            'halaman_galery'    => $request->input('halaman_galery', 0),
            'halaman_cerita'    => $request->input('halaman_cerita', 0),
            'halaman_lokasi'    => $request->input('halaman_lokasi', 0),
            'halaman_prokes'    => $request->input('halaman_prokes', 0),
            'halaman_send_gift' => $request->input('halaman_send_gift', 0),
            'halaman_qoute'     => $request->input('halaman_qoute', 0),
        ];

        $filterUndangan = FilterUndangan::firstOrCreate(
            ['user_id' => $user->id],
            $defaultData
        );

        return response()->json([
            'message' => $filterUndangan->wasRecentlyCreated
            ? 'Data berhasil dibuat.'
            : 'Data sudah ada sebelumnya.',
            'data'    => $filterUndangan,
        ], $filterUndangan->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        try {
            $validatedData = $request->validate([
                'halaman_sampul'    => 'nullable|integer',
                'halaman_mempelai'  => 'nullable|integer',
                'halaman_acara'     => 'nullable|integer',
                'halaman_ucapan'    => 'nullable|integer',
                'halaman_galery'    => 'nullable|integer',
                'halaman_cerita'    => 'nullable|integer',
                'halaman_lokasi'    => 'nullable|integer',
                'halaman_prokes'    => 'nullable|integer',
                'halaman_send_gift' => 'nullable|integer',
                'halaman_qoute'     => 'nullable|integer',
            ]);

            $filterUndangan = FilterUndangan::where('user_id', $user->id)->first();

            if (! $filterUndangan) {
                return response()->json(['message' => 'Data tidak ditemukan.'], 404);
            }

            $dataToUpdate = array_filter($validatedData, function ($value) {
                return $value !== null;
            });

            $filterUndangan->update($dataToUpdate);

            return response()->json([
                'message' => 'Data filter berhasil diperbarui.',
                'data'    => $filterUndangan->fresh(),
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Filter update failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Terjadi kesalahan saat memperbarui data.'], 500);
        }
    }

}
