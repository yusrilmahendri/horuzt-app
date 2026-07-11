<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreMusicRequest;
use App\Models\FilterUndangan;
use App\Models\Setting;
use App\Models\User;
use App\Services\DomainService;
use App\Services\MusicResolverService;
use App\Services\MusicStreamService;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    protected MusicStreamService $musicStreamService;
    protected MusicResolverService $musicResolverService;
    protected DomainService $domainService;

    public function __construct(
        MusicStreamService $musicStreamService,
        MusicResolverService $musicResolverService,
        DomainService $domainService
    )
    {
        $this->middleware('auth:sanctum');
        $this->musicStreamService = $musicStreamService;
        $this->musicResolverService = $musicResolverService;
        $this->domainService = $domainService;
    }

    public function index()
    {
        $user = Auth::user();

        $setting = Setting::where('user_id', $user->id)->first();

        // Auto-create FilterUndangan with default values if it doesn't exist
        $filterUndangan = FilterUndangan::firstOrCreate(
            ['user_id' => $user->id],
            [
                'halaman_sampul'    => 1,
                'halaman_mempelai'  => 1,
                'halaman_acara'     => 1,
                'halaman_ucapan'    => 1,
                'halaman_galery'    => 1,
                'halaman_cerita'    => 1,
                'halaman_lokasi'    => 1,
                'halaman_prokes'    => 1,
                'halaman_send_gift' => 1,
                'halaman_qoute'     => 1,
            ]
        );

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

        if (array_key_exists('domain', $validatedData) && $validatedData['domain'] !== null) {
            $normalizedDomain = $this->domainService->normalizeToSlug((string) $validatedData['domain']);

            if ($normalizedDomain === '') {
                $this->domainService->logValidation(
                    (int) $user->id,
                    (string) $request->input('domain'),
                    '',
                    false,
                    false,
                    'invalid'
                );
                return response()->json([
                    'message' => 'Domain wajib diisi dengan format slug yang valid.',
                    'errors' => [
                        'domain' => ['Domain wajib diisi dengan format slug yang valid.'],
                    ],
                ], 422);
            }

            $domainUsage = $this->domainService->checkDomainUsage($normalizedDomain, (int) $user->id);
            $this->domainService->logValidation(
                (int) $user->id,
                (string) $request->input('domain'),
                $normalizedDomain,
                $domainUsage['exists_in_settings'],
                $domainUsage['exists_in_invitations'],
                $domainUsage['is_used'] ? 'duplicate' : 'available'
            );

            if ($domainUsage['is_used']) {
                return response()->json([
                    'message' => 'Domain undangan sudah digunakan.',
                    'errors' => [
                        'domain' => ['Domain undangan sudah digunakan.'],
                    ],
                ], 422);
            }

            $validatedData['domain'] = $normalizedDomain;
        }

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

    public function storeMusic(StoreMusicRequest $request)
    {
        $deleteOldResult = null;
        $storeNewResult = false;
        $updateDbResult = false;
        $oldFilePath = null;
        $newFilePath = null;

        try {
            $user = Auth::user();

            // Business rule (BE-4C hardening): the legacy upload endpoint must
            // enforce the same Diamond-only restriction as MusicController::store
            // so it cannot be used to bypass the package lock.
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

            Log::info('Legacy custom music upload started', [
                'user_id' => $user?->id,
                'original_filename' => $originalFilename,
                'extension' => $extension,
                'client_mime_type' => $clientMimeType,
                'detected_mime_type' => $detectedMimeType,
                'size' => $size,
            ]);

            if (!($musicFile instanceof UploadedFile) || !$uploadMeta['is_valid_upload']) {
                Log::warning('Legacy custom music upload rejected: invalid upload object', [
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
                    'message' => 'File musik tidak valid atau tidak dapat diproses.',
                    'errors' => [
                        'musik' => ['File musik tidak valid atau tidak dapat diproses.'],
                    ],
                ], 422);
            }

            $audioInspection = $this->musicStreamService->inspectAudioFile($musicFile);
            if (!$audioInspection['is_valid']) {
                $formatErrorMessage = 'Format file musik tidak didukung. Gunakan MP3, WAV, OGG, atau M4A.';
                $sizeErrorMessage = 'Ukuran file musik melebihi batas maksimum 10 MB.';
                $uploadErrorMessage = 'File musik tidak valid atau tidak dapat diproses.';

                $reason = $audioInspection['reason'] ?? 'unsupported_mime';
                $message = match ($reason) {
                    'size_exceeded' => $sizeErrorMessage,
                    'invalid_upload', 'missing_file' => $uploadErrorMessage,
                    default => $formatErrorMessage,
                };

                Log::warning('Legacy custom music upload rejected by service validation', [
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
            $fileName = (string) \Illuminate\Support\Str::uuid() . '.' . $fileExtension;
            $newFilePath = 'public/music/' . $fileName;
            $oldFilePath = $existingSetting?->musik;

            // Store new file first so old file remains safe if storage write fails.
            $filePath = $musicFile->storeAs('public/music', $fileName);
            $storeNewResult = (bool) $filePath;

            if (!$storeNewResult || !$filePath) {
                Log::error('Legacy custom music upload failed: cannot store new file', [
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

                Log::error('Legacy custom music upload failed: cannot update DB', [
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

                    Log::error('Legacy custom music upload failed: cannot replace old file', [
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
            $state = $this->musicResolverService->selectionState($setting, $user);

            Log::info('Legacy custom music upload completed', [
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

            $message = 'Gagal mengunggah file musik.';
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
            'halaman_sampul'    => $request->input('halaman_sampul', 1),
            'halaman_mempelai'  => $request->input('halaman_mempelai', 1),
            'halaman_acara'     => $request->input('halaman_acara', 1),
            'halaman_ucapan'    => $request->input('halaman_ucapan', 1),
            'halaman_galery'    => $request->input('halaman_galery', 1),
            'halaman_cerita'    => $request->input('halaman_cerita', 1),
            'halaman_lokasi'    => $request->input('halaman_lokasi', 1),
            'halaman_prokes'    => $request->input('halaman_prokes', 1),
            'halaman_send_gift' => $request->input('halaman_send_gift', 1),
            'halaman_qoute'     => $request->input('halaman_qoute', 1),
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

            // Ambil data terbaru setelah update
            $filterUndangan = $filterUndangan->refresh();

            return response()->json([
                'message' => 'Data filter berhasil diperbarui.',
                'data'    => $filterUndangan,
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

    /**
     * Determine whether the authenticated user may upload/replace custom music.
     * Only the Diamond tier (incl. legacy "Platinum") is allowed.
     * Mirrors MusicController::userCanUploadCustomMusic so the legacy upload
     * endpoint enforces the same Diamond lock.
     *
     * @param  mixed  $user
     * @return bool
     */
    private function userCanUploadCustomMusic($user): bool
    {
        return $this->musicResolverService->canUploadCustomMusicForUser($user);
    }

}
