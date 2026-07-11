<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMusicRequest;
use App\Http\Requests\StreamMusicRequest;
use App\Models\Setting;
use App\Services\MusicResolverService;
use App\Services\MusicStreamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MusicController extends Controller
{
    protected MusicStreamService $musicStreamService;
    protected MusicResolverService $resolver;

    public function __construct(MusicStreamService $musicStreamService, MusicResolverService $resolver)
    {
        $this->musicStreamService = $musicStreamService;
        $this->resolver = $resolver;
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

            if (!$this->userCanUploadCustomMusic($user)) {
                return response()->json([
                    'message' => 'Upload musik pribadi hanya tersedia untuk paket Diamond/Platinum.',
                    'errors' => [
                        'musik' => ['Upload musik pribadi hanya tersedia untuk paket Diamond/Platinum.'],
                    ],
                ], 403);
            }

            $musicFile = $request->file('musik');

            // Additional validation using service
            if (!$this->musicStreamService->validateAudioFile($musicFile)) {
                return response()->json([
                    'message' => 'Format file musik tidak didukung.',
                    'errors' => [
                        'musik' => ['Format file musik tidak didukung. Gunakan MP3, WAV, OGG, atau M4A.'],
                    ],
                ], 422);
            }

            // Delete existing music file if exists
            $existingSetting = Setting::where('user_id', $user->id)->first();
            if ($existingSetting && $existingSetting->musik) {
                $this->musicStreamService->deleteMusic($existingSetting);
            }

            // Store new file
            $fileName = (string) Str::uuid() . '.' . $musicFile->getClientOriginalExtension();
            $filePath = $musicFile->storeAs('public/music', $fileName);

            // Update or create setting
            $setting = Setting::updateOrCreate(
                ['user_id' => $user->id],
                ['musik' => $filePath]
            );

            $setting = $setting->fresh(['musicTrack']);
            $musicInfo = $this->musicStreamService->getMusicInfo($setting);
            $state = $this->resolver->selectionState($setting, $user);

            return response()->json([
                'message' => 'Musik pribadi berhasil diunggah.',
                'setting' => $setting,
                'music_info' => $musicInfo,
                'data' => $state,
                'active_music' => $state['active_music'],
                'music_source_type' => $state['music_source_type'],
                'selected_catalog_id' => $state['selected_catalog_id'],
                'custom_music' => $state['custom_music'],
                'resolved_music_url' => $state['resolved_music_url'],
                'can_upload_custom_music' => $state['can_upload_custom_music'],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Music upload failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Gagal mengunggah file musik.',
                'errors' => [
                    'musik' => ['Gagal mengunggah file musik.'],
                ],
            ], 500);
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
                $freshSetting = $setting->fresh(['musicTrack']);
                $state = $this->resolver->selectionState($freshSetting, $user);
                
                return response()->json([
                    'message' => 'Music file deleted successfully.',
                    'setting' => $freshSetting,
                    'music_info' => $state['active_music'],
                    'data' => $state,
                    'active_music' => $state['active_music'],
                    'music_source_type' => $state['music_source_type'],
                    'selected_catalog_id' => $state['selected_catalog_id'],
                    'custom_music' => $state['custom_music'],
                    'resolved_music_url' => $state['resolved_music_url'],
                    'can_upload_custom_music' => $state['can_upload_custom_music'],
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

            // Return the effective music (custom upload, selected catalog track,
            // or system default) so the dashboard reflects what actually plays.
            $musicInfo = $this->musicStreamService->getEffectiveMusicInfo($setting);

            if (!$musicInfo) {
                return response()->json(['message' => 'No music file found.'], 404);
            }

            return response()->json([
                'message' => 'Music information retrieved successfully.',
                'music_info' => $musicInfo,
                'setting' => $setting,
                'data' => $this->resolver->selectionState($setting, $user),
                ...$this->resolver->selectionState($setting, $user),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Music info retrieval failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            return response()->json(['message' => 'Failed to retrieve music information.'], 500);
        }
    }

    /**
     * Determine whether the authenticated user may upload/replace custom music.
     * Only the Diamond tier (incl. legacy "Platinum") is allowed.
     *
     * @param  mixed  $user
     * @return bool
     */
    private function userCanUploadCustomMusic($user): bool
    {
        return $this->resolver->canUploadCustomMusicForUser($user);
    }
}
