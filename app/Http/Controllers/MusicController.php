<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMusicRequest;
use App\Http\Requests\StreamMusicRequest;
use App\Models\Setting;
use App\Services\MusicStreamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MusicController extends Controller
{
    protected MusicStreamService $musicStreamService;

    public function __construct(MusicStreamService $musicStreamService)
    {
        $this->musicStreamService = $musicStreamService;
        $this->middleware('auth:sanctum')->except(['streamPublic']);
    }

    /**
     * Stream music file for authenticated users
     * 
     * @param StreamMusicRequest $request
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function stream(StreamMusicRequest $request)
    {
        $user = Auth::user();
        $setting = Setting::findOrFail($request->validated()['id']);

        // Check if user owns this setting
        if ($setting->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized access to this resource.'], 403);
        }

        return $this->musicStreamService->streamMusic($setting, $request);
    }

    /**
     * Stream music file for public access (wedding invitations)
     * 
     * @param StreamMusicRequest $request
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function streamPublic(StreamMusicRequest $request)
    {
        $setting = Setting::findOrFail($request->validated()['id']);

        return $this->musicStreamService->streamMusic($setting, $request);
    }

    /**
     * Download music file
     * 
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\Response
     */
    public function download(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:settings,id'
        ]);

        $user = Auth::user();
        $setting = Setting::findOrFail($request->id);

        // Check if user owns this setting
        if ($setting->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized access to this resource.'], 403);
        }

        return $this->musicStreamService->downloadMusic($setting);
    }

    /**
     * Upload music file
     * 
     * @param StoreMusicRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreMusicRequest $request)
    {
        try {
            $user = Auth::user();
            $musicFile = $request->file('musik');

            // Additional validation using service
            if (!$this->musicStreamService->validateAudioFile($musicFile)) {
                return response()->json([
                    'message' => 'Invalid audio file format or size.'
                ], 422);
            }

            // Delete existing music file if exists
            $existingSetting = Setting::where('user_id', $user->id)->first();
            if ($existingSetting && $existingSetting->musik) {
                $this->musicStreamService->deleteMusic($existingSetting);
            }

            // Store new file
            $fileName = time() . '_' . $musicFile->getClientOriginalName();
            $filePath = $musicFile->storeAs('public/music', $fileName);

            // Update or create setting
            $setting = Setting::updateOrCreate(
                ['user_id' => $user->id],
                ['musik' => $filePath]
            );

            // Get file info
            $musicInfo = $this->musicStreamService->getMusicInfo($setting);

            return response()->json([
                'message' => 'Music file uploaded successfully.',
                'setting' => $setting,
                'music_info' => $musicInfo
            ], 200);

        } catch (\Exception $e) {
            Log::error('Music upload failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            return response()->json(['message' => 'Failed to upload music file.'], 500);
        }
    }

    /**
     * Delete music file
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy()
    {
        try {
            $user = Auth::user();
            $setting = Setting::where('user_id', $user->id)->first();

            if (!$setting) {
                return response()->json(['message' => 'Setting not found.'], 404);
            }

            if (!$setting->musik) {
                return response()->json(['message' => 'No music file to delete.'], 404);
            }

            $deleted = $this->musicStreamService->deleteMusic($setting);

            if ($deleted) {
                $setting->update(['musik' => null]);
                
                return response()->json([
                    'message' => 'Music file deleted successfully.',
                    'setting' => $setting->fresh()
                ], 200);
            }

            return response()->json(['message' => 'Failed to delete music file.'], 500);

        } catch (\Exception $e) {
            Log::error('Music deletion failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            return response()->json(['message' => 'Failed to delete music file.'], 500);
        }
    }

    /**
     * Get music file information
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function info()
    {
        try {
            $user = Auth::user();
            $setting = Setting::where('user_id', $user->id)->first();

            if (!$setting) {
                return response()->json(['message' => 'Setting not found.'], 404);
            }

            $musicInfo = $this->musicStreamService->getMusicInfo($setting);

            if (!$musicInfo) {
                return response()->json(['message' => 'No music file found.'], 404);
            }

            return response()->json([
                'message' => 'Music information retrieved successfully.',
                'music_info' => $musicInfo,
                'setting' => $setting
            ], 200);

        } catch (\Exception $e) {
            Log::error('Music info retrieval failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            return response()->json(['message' => 'Failed to retrieve music information.'], 500);
        }
    }
}