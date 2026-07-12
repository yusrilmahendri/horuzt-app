<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMusicRequest;
use App\Http\Requests\StreamMusicRequest;
use App\Models\Setting;
use App\Services\MusicResolverService;
use App\Services\MusicStreamService;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
        $deleteOldResult = null;
        $storeNewResult = false;
        $updateDbResult = false;
        $oldFilePath = null;
        $newFilePath = null;

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
            $uploadMeta = $this->musicStreamService->safeUploadMeta($musicFile);
            $clientMimeType = $uploadMeta['client_mime_type'];
            $detectedMimeType = $uploadMeta['detected_mime_type'];
            $originalFilename = $uploadMeta['original_filename'];
            $extension = $uploadMeta['extension'];
            $size = $uploadMeta['size'];

            Log::info('Custom music upload started', [
                'user_id' => $user?->id,
                'original_filename' => $originalFilename,
                'extension' => $extension,
                'client_mime_type' => $clientMimeType,
                'detected_mime_type' => $detectedMimeType,
                'size' => $size,
            ]);

            if (!($musicFile instanceof UploadedFile) || !$uploadMeta['is_valid_upload']) {
                Log::warning('Custom music upload rejected: invalid upload object', [
                    'user_id' => $user?->id,
                    'original_filename' => $originalFilename,
                    'extension' => $extension,
                    'client_mime_type' => $clientMimeType,
                    'detected_mime_type' => $detectedMimeType,
                    'size' => $size,
                    'upload_error_code' => $uploadMeta['upload_error_code'],
                    'upload_error_message' => $uploadMeta['upload_error_message'],
                    'mime_detection_error' => $uploadMeta['mime_detection_error'],
                ]);

                return response()->json([
                    'message' => 'Gagal menyimpan file musik.',
                    'errors' => [
                        'musik' => ['Gagal menyimpan file musik.'],
                    ],
                ], 422);
            }

            $audioInspection = $this->musicStreamService->inspectAudioFile($musicFile);
            if (!$audioInspection['is_valid']) {
                $formatErrorMessage = 'Format musik harus MP3, WAV, M4A, AAC, atau OGG.';
                $sizeErrorMessage = 'Ukuran file musik maksimal 20 MB.';
                $uploadErrorMessage = 'Gagal menyimpan file musik.';

                $reason = $audioInspection['reason'] ?? 'unsupported_extension';
                $message = match ($reason) {
                    'size_exceeded' => $sizeErrorMessage,
                    'invalid_upload', 'missing_file' => $uploadErrorMessage,
                    default => $formatErrorMessage,
                };

                Log::warning('Custom music upload rejected by service validation', [
                    'user_id' => $user?->id,
                    'original_filename' => $originalFilename,
                    'extension' => $audioInspection['extension'],
                    'client_mime_type' => $audioInspection['client_mime_type'],
                    'detected_mime_type' => $audioInspection['detected_mime_type'],
                    'size' => $audioInspection['size'],
                    'validation_reason' => $reason,
                ]);

                return response()->json([
                    'message' => $message,
                    'errors' => [
                        'musik' => [$message],
                    ],
                ], 422);
            }

            $existingSetting = Setting::where('user_id', $user->id)->first();
            $fileExtension = $audioInspection['extension'] ?: 'mp3';
            $fileName = (string) Str::uuid() . '.' . $fileExtension;
            $newFilePath = 'public/music/' . $fileName;
            $oldFilePath = $existingSetting?->musik;

            // Store new file first so old file remains safe if storage write fails.
            $filePath = $musicFile->storeAs('public/music', $fileName);
            $storeNewResult = (bool) $filePath;

            if (!$storeNewResult || !$filePath) {
                Log::error('Custom music upload failed: cannot store new file', [
                    'user_id' => $user?->id,
                    'original_filename' => $originalFilename,
                    'extension' => $extension,
                    'client_mime_type' => $clientMimeType,
                    'detected_mime_type' => $detectedMimeType,
                    'size' => $size,
                    'old_file_path' => $oldFilePath,
                    'new_file_path' => $newFilePath,
                    'delete_old_file_result' => $deleteOldResult,
                    'store_new_file_result' => $storeNewResult,
                    'update_db_result' => $updateDbResult,
                ]);

                return response()->json([
                    'message' => 'Gagal menyimpan file musik.',
                    'errors' => [
                        'musik' => ['Gagal menyimpan file musik.'],
                    ],
                ], 500);
            }

            // Update or create setting
            $settingPayload = ['musik' => $filePath];
            if (Schema::hasColumn('settings', 'music_source_type')) {
                $settingPayload['music_source_type'] = 'user_upload';
            }
            if (Schema::hasColumn('settings', 'external_music_track_id')) {
                $settingPayload['external_music_track_id'] = null;
            }

            $setting = Setting::updateOrCreate(
                ['user_id' => $user->id],
                $settingPayload
            );
            $updateDbResult = (bool) $setting;

            if (!$updateDbResult) {
                Storage::delete($filePath);

                Log::error('Custom music upload failed: cannot update DB', [
                    'user_id' => $user?->id,
                    'original_filename' => $originalFilename,
                    'extension' => $extension,
                    'client_mime_type' => $clientMimeType,
                    'detected_mime_type' => $detectedMimeType,
                    'size' => $size,
                    'old_file_path' => $oldFilePath,
                    'new_file_path' => $newFilePath,
                    'delete_old_file_result' => $deleteOldResult,
                    'store_new_file_result' => $storeNewResult,
                    'update_db_result' => $updateDbResult,
                ]);

                return response()->json([
                    'message' => 'Gagal memperbarui pengaturan musik.',
                    'errors' => [
                        'musik' => ['Gagal memperbarui pengaturan musik.'],
                    ],
                ], 500);
            }

            if ($oldFilePath) {
                if (!Storage::exists($oldFilePath)) {
                    $deleteOldResult = true;
                } else {
                    $deleteOldResult = Storage::delete($oldFilePath);
                }

                if (!$deleteOldResult) {
                    $setting->update(['musik' => $oldFilePath]);
                    Storage::delete($filePath);

                    Log::error('Custom music upload failed: cannot replace old file', [
                        'user_id' => $user?->id,
                        'original_filename' => $originalFilename,
                        'extension' => $extension,
                        'client_mime_type' => $clientMimeType,
                        'detected_mime_type' => $detectedMimeType,
                        'size' => $size,
                        'old_file_path' => $oldFilePath,
                        'new_file_path' => $newFilePath,
                        'delete_old_file_result' => $deleteOldResult,
                        'store_new_file_result' => $storeNewResult,
                        'update_db_result' => $updateDbResult,
                    ]);

                    return response()->json([
                        'message' => 'Gagal mengganti file musik lama.',
                        'errors' => [
                            'musik' => ['Gagal mengganti file musik lama.'],
                        ],
                    ], 500);
                }
            }

            $setting = $setting->fresh(['musicTrack']);
            $musicInfo = $this->musicStreamService->getMusicInfo($setting);
            $state = $this->resolver->selectionState($setting, $user);

            Log::info('Custom music upload completed', [
                'user_id' => $user?->id,
                'original_filename' => $originalFilename,
                'extension' => $extension,
                'client_mime_type' => $clientMimeType,
                'detected_mime_type' => $detectedMimeType,
                'size' => $size,
                'old_file_path' => $oldFilePath,
                'new_file_path' => $newFilePath,
                'delete_old_file_result' => $deleteOldResult,
                'store_new_file_result' => $storeNewResult,
                'update_db_result' => $updateDbResult,
            ]);

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
            Log::error('Music upload failed with exception', [
                'user_id' => Auth::id(),
                ...$this->musicStreamService->safeUploadMeta($request->file('musik')),
                'old_file_path' => $oldFilePath,
                'new_file_path' => $newFilePath,
                'delete_old_file_result' => $deleteOldResult,
                'store_new_file_result' => $storeNewResult,
                'update_db_result' => $updateDbResult,
                'exception_message' => $e->getMessage(),
            ]);

            $message = 'Gagal menyimpan file musik.';
            if (!$storeNewResult) {
                $message = 'Gagal menyimpan file musik.';
            } elseif ($storeNewResult && !$updateDbResult) {
                $message = 'Gagal memperbarui pengaturan musik.';
            } elseif ($storeNewResult && $updateDbResult && $deleteOldResult === false) {
                $message = 'Gagal mengganti file musik lama.';
            } elseif ($storeNewResult && $updateDbResult) {
                $message = 'File musik berhasil disimpan, tetapi proses finalisasi data gagal.';
            }

            return response()->json([
                'message' => $message,
                'errors' => [
                    'musik' => [$message],
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
