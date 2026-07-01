<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreMusicRequest;
use App\Models\FilterUndangan;
use App\Models\Invitation;
use App\Models\PaketUndangan;
use App\Models\Setting;
use App\Models\User;
use App\Services\DomainService;
use App\Services\MusicStreamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    protected MusicStreamService $musicStreamService;
    protected DomainService $domainService;

    public function __construct(MusicStreamService $musicStreamService, DomainService $domainService)
    {
        $this->middleware('auth:sanctum');
        $this->musicStreamService = $musicStreamService;
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
        try {
            $user = Auth::user();

            // Business rule (BE-4C hardening): the legacy upload endpoint must
            // enforce the same Diamond-only restriction as MusicController::store
            // so it cannot be used to bypass the package lock.
            if (!$this->userCanUploadCustomMusic($user)) {
                return response()->json([
                    'message' => 'Custom music upload is only available for Diamond package.'
                ], 403);
            }

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
        if (!$user) {
            return false;
        }

        // Pick the relevant invitation: prefer a paid one, then the latest.
        $invitation = Invitation::with('paketUndangan')
            ->where('user_id', $user->id)
            ->orderByRaw("CASE WHEN payment_status = 'paid' THEN 0 ELSE 1 END")
            ->orderBy('id', 'desc')
            ->first();

        if (!$invitation) {
            return false;
        }

        $rawName = null;
        if ($invitation->paketUndangan) {
            $rawName = $invitation->paketUndangan->name_paket;
        } elseif (is_array($invitation->package_features_snapshot)) {
            $rawName = $invitation->package_features_snapshot['name_paket'] ?? null;
        }

        if ($rawName === null || trim($rawName) === '') {
            return false;
        }

        // Use the brand-aware tier helper (Platinum/Diamond => "diamond").
        $tier = method_exists(PaketUndangan::class, 'tierCode')
            ? PaketUndangan::tierCode($rawName)
            : strtolower(trim($rawName));

        return $tier === 'diamond';
    }

}
