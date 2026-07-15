<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MusicTrack;
use App\Services\GlobalMusicCatalogSyncService;
use App\Services\MusicStreamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AdminMusicTrackController extends Controller
{
    public const ADMIN_CATALOG_ROLES = ['admin', 'Admin', 'super-admin', 'super_admin', 'administrator'];
    public const ADMIN_CATALOG_ROLE_MIDDLEWARE = 'role:admin|Admin|super-admin|super_admin|administrator';

    protected MusicStreamService $musicStreamService;
    protected GlobalMusicCatalogSyncService $globalCatalogSyncService;

    public function __construct(MusicStreamService $musicStreamService, GlobalMusicCatalogSyncService $globalCatalogSyncService)
    {
        $this->musicStreamService = $musicStreamService;
        $this->globalCatalogSyncService = $globalCatalogSyncService;
    }

    /**
     * GET /api/v1/admin/music-tracks
     */
    public function index(Request $request)
    {
        $query = MusicTrack::query();

        if ($request->has('is_active')) {
            $query->where('is_active', (bool) $request->boolean('is_active'));
        }

        $tracks = $query->orderBy('sort_order', 'asc')
            ->orderBy('title', 'asc')
            ->get()
            ->map(fn (MusicTrack $track) => $this->trackPayload($track));

        return response()->json([
            'message' => 'Daftar musik katalog berhasil diambil.',
            'data' => $tracks,
            'catalog' => $tracks,
        ], 200);
    }

    /**
     * POST /api/v1/admin/music-tracks
     * form-data: musik (file), title, artist?, is_default?, sort_order?, source?
     */
    public function store(Request $request)
    {
        if (! $this->canUploadCatalogMusic()) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses untuk mengupload katalog musik.',
            ], 403);
        }

        $maxMusicSize = config('upload.music_max_file_size', 20480);
        $allowedExtensions = ['mp3', 'wav', 'm4a', 'aac', 'ogg'];
        $formatErrorMessage = 'Format file tidak didukung. Gunakan MP3, WAV, M4A, AAC, atau OGG.';
        $sizeErrorMessage = 'Ukuran file maksimal 20 MB.';

        $validated = $request->validate([
            'musik' => [
                'required',
                'file',
                "max:{$maxMusicSize}",
                function (string $attribute, mixed $value, \Closure $fail) use ($allowedExtensions, $formatErrorMessage): void {
                    if (! $value instanceof \Illuminate\Http\UploadedFile) {
                        $fail('File musik wajib dipilih.');
                        return;
                    }

                    if (! $value->isValid()) {
                        $fail('Gagal menyimpan file musik.');
                        return;
                    }

                    $extension = strtolower((string) $value->getClientOriginalExtension());
                    if ($extension === '' || ! in_array($extension, $allowedExtensions, true)) {
                        $fail($formatErrorMessage);
                    }
                },
            ],
            'title' => ['required', 'string', 'max:255'],
            'artist' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'source' => ['nullable', 'string', 'max:50'],
            'external_id' => ['nullable', 'string', 'max:100'],
        ], [
            'musik.required' => 'File musik wajib dipilih.',
            'musik.file' => 'Gagal menyimpan file musik.',
            'musik.uploaded' => 'Gagal menyimpan file musik.',
            'musik.max' => $sizeErrorMessage,
            'title.required' => 'Judul musik katalog wajib diisi.',
        ]);

        $file = $request->file('musik');
        $uploadMeta = $this->musicStreamService->safeUploadMeta($file);
        $originalFilename = $uploadMeta['original_filename'];
        $extension = $uploadMeta['extension'];
        $size = $uploadMeta['size'];
        $oldFilePath = null;
        $newFilePath = null;
        $storeNewResult = false;
        $deleteOldResult = null;

        Log::info('Admin music catalog upload started', [
            'admin_id' => Auth::id(),
            'original_filename' => $originalFilename,
            'extension' => $extension,
            'size' => $size,
            'old_file_path' => $oldFilePath,
        ]);

        try {
            return DB::transaction(function () use ($request, $validated, $file, $originalFilename, $extension, $size, &$newFilePath, &$storeNewResult, $oldFilePath, $deleteOldResult) {
                $safeExtension = $extension ?: 'mp3';
                $fileName = (string) Str::uuid() . '.' . $safeExtension;
                $filePath = $file->storeAs('public/music/catalog', $fileName);
                $storeNewResult = (bool) $filePath;
                $newFilePath = $filePath;

                if (!$storeNewResult || !$filePath) {
                    Log::error('Admin music catalog upload failed: storage write failed', [
                        'admin_id' => Auth::id(),
                        'original_filename' => $originalFilename,
                        'extension' => $extension,
                        'size' => $size,
                        'old_file_path' => $oldFilePath,
                        'new_file_path' => $newFilePath,
                        'store_new_file_result' => $storeNewResult,
                        'delete_old_file_result' => $deleteOldResult,
                    ]);

                    return response()->json([
                        'message' => 'Gagal menyimpan file musik.',
                    ], 500);
                }

                $makeDefault = $request->boolean('is_default');

                $track = MusicTrack::create([
                    'title' => $validated['title'],
                    'artist' => $validated['artist'] ?? null,
                    'slug' => $this->makeSlug($validated['title']),
                    'file_path' => $filePath,
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => $size,
                    'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
                    'is_default' => $makeDefault,
                    'sort_order' => $validated['sort_order'] ?? 0,
                    'source' => $validated['source'] ?? 'sena_digital',
                    'external_id' => $validated['external_id'] ?? null,
                    'uploaded_by' => Auth::id(),
                ]);

                if ($makeDefault) {
                    $this->promoteDefault($track);
                }

                Log::info('Admin music catalog upload completed', [
                    'admin_id' => Auth::id(),
                    'original_filename' => $originalFilename,
                    'extension' => $extension,
                    'size' => $size,
                    'old_file_path' => $oldFilePath,
                    'new_file_path' => $newFilePath,
                    'store_new_file_result' => $storeNewResult,
                    'delete_old_file_result' => $deleteOldResult,
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Musik katalog berhasil diupload.',
                    'data' => $this->trackPayload($track->fresh()),
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Admin music track create failed', [
                'admin_id' => Auth::id(),
                'original_filename' => $originalFilename,
                'extension' => $extension,
                'size' => $size,
                'old_file_path' => $oldFilePath,
                'new_file_path' => $newFilePath,
                'store_new_file_result' => $storeNewResult,
                'delete_old_file_result' => $deleteOldResult,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal menyimpan file musik.',
            ], 500);
        }
    }

    /**
     * PUT/PATCH /api/v1/admin/music-tracks/{id}
     * Update metadata / flags (no file replacement in MVP).
     */
    public function update(Request $request, $id)
    {
        $track = MusicTrack::find($id);

        if (!$track) {
            return response()->json(['message' => 'Musik katalog tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'artist' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ], [
            'title.required' => 'Judul musik katalog wajib diisi.',
        ]);

        try {
            return DB::transaction(function () use ($request, $validated, $track) {
                $data = [];

                foreach (['title', 'artist'] as $field) {
                    if (array_key_exists($field, $validated)) {
                        $data[$field] = $validated[$field];
                    }
                }

                if (array_key_exists('description', $validated)) {
                    $data['description'] = $validated['description'];
                } elseif (array_key_exists('subtitle', $validated)) {
                    $data['description'] = $validated['subtitle'];
                }

                $track->update($data);

                return response()->json([
                    'status' => true,
                    'message' => 'Musik katalog berhasil diperbarui.',
                    'data' => $this->trackPayload($track->fresh()),
                ], 200);
            });
        } catch (\Exception $e) {
            Log::error('Admin music track update failed', [
                'admin_id' => Auth::id(),
                'track_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal memperbarui metadata musik katalog.',
            ], 500);
        }
    }

    /**
     * PATCH /api/v1/admin/music-tracks/{id}/set-default
     */
    public function setDefault($id)
    {
        $track = MusicTrack::find($id);

        if (!$track) {
            return response()->json(['message' => 'Musik katalog tidak ditemukan.'], 404);
        }

        try {
            return DB::transaction(function () use ($track) {
                // Default must be active.
                $track->update(['is_default' => true, 'is_active' => true]);
                $this->promoteDefault($track->fresh());

                return response()->json([
                    'status' => true,
                    'message' => 'Musik berhasil dijadikan default.',
                    'data' => $this->trackPayload($track->fresh()),
                ], 200);
            });
        } catch (\Exception $e) {
            Log::error('Admin set default track failed', [
                'admin_id' => Auth::id(),
                'track_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal memperbarui musik default.',
            ], 500);
        }
    }

    /**
     * PATCH /api/v1/admin/music-tracks/{id}/toggle-active
     */
    public function toggleActive($id)
    {
        $track = MusicTrack::find($id);

        if (!$track) {
            return response()->json(['message' => 'Musik katalog tidak ditemukan.'], 404);
        }

        $isActive = ! $track->is_active;
        $track->update([
            'is_active' => $isActive,
            'is_default' => $isActive ? $track->is_default : false,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Status musik katalog berhasil diperbarui.',
            'data' => $this->trackPayload($track->fresh()),
        ], 200);
    }

    /**
     * PATCH /api/v1/admin/music-tracks/{id}/status
     * body: { is_active: true|false }
     */
    public function setStatus(Request $request, $id)
    {
        $track = MusicTrack::find($id);
        if (!$track) {
            return response()->json(['message' => 'Musik katalog tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $isActive = (bool) $validated['is_active'];

        $track->update([
            'is_active' => $isActive,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Musik katalog berhasil diperbarui.',
            'data' => $this->trackPayload($track->fresh()),
        ], 200);
    }

    /**
     * PATCH /api/v1/admin/music-tracks/reorder
     * body: { track_ids: [3,1,2] }
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'track_ids' => ['required', 'array', 'min:1'],
            'track_ids.*' => ['integer', 'exists:music_tracks,id'],
        ], [
            'track_ids.required' => 'Urutan musik katalog wajib dikirim.',
            'track_ids.array' => 'Format urutan musik katalog tidak valid.',
        ]);

        $trackIds = array_values(array_unique($validated['track_ids']));

        try {
            DB::transaction(function () use ($trackIds) {
                foreach ($trackIds as $index => $trackId) {
                    MusicTrack::where('id', $trackId)->update(['sort_order' => $index + 1]);
                }
            });

            $tracks = MusicTrack::orderBy('sort_order', 'asc')
                ->orderBy('title', 'asc')
                ->get()
                ->map(fn (MusicTrack $track) => $this->trackPayload($track))
                ->values();

            return response()->json([
                'message' => 'Urutan musik katalog berhasil diperbarui.',
                'data' => $tracks,
                'catalog' => $tracks,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Admin music track reorder failed', [
                'admin_id' => Auth::id(),
                'track_ids' => $trackIds,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal memperbarui urutan musik katalog.',
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/admin/music-tracks/{id}
     * Hard delete + remove file. settings.music_track_id is null-ed by FK.
     */
    public function destroy($id)
    {
        $track = MusicTrack::find($id);

        if (!$track) {
            return response()->json(['message' => 'Musik katalog tidak ditemukan.'], 404);
        }

        try {
            if ($track->file_path) {
                Storage::delete($track->file_path);
            }

            if (Schema::hasTable('settings') && Schema::hasColumn('settings', 'music_track_id')) {
                DB::table('settings')
                    ->where('music_track_id', $track->id)
                    ->update(['music_track_id' => null]);
            }

            $track->delete();

            return response()->json([
                'status' => true,
                'message' => 'Musik katalog berhasil dihapus.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Admin music track delete failed', [
                'admin_id' => Auth::id(),
                'track_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal menghapus musik katalog.',
            ], 500);
        }
    }

    /**
     * POST /api/v1/admin/music-tracks/sync-global
     * Sync cached global catalog metadata into local DB.
     */
    public function syncGlobalCatalog()
    {
        try {
            $result = request()->boolean('seed_mock')
                ? $this->globalCatalogSyncService->seedMock()
                : $this->globalCatalogSyncService->sync();

            return response()->json([
                'message' => 'Global catalog sync completed.',
                'data' => $result,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Global catalog sync failed', [
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to sync global catalog.',
            ], 500);
        }
    }

    /**
     * Ensure only the given track stays default.
     */
    private function promoteDefault(MusicTrack $track): void
    {
        MusicTrack::where('id', '!=', $track->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    private function trackPayload(MusicTrack $track): array
    {
        return [
            'id' => $track->id,
            'title' => $track->title,
            'artist' => $track->artist,
            'subtitle' => $track->getAttribute('description') ?: $track->artist,
            'description' => $track->getAttribute('description'),
            'audio_url' => $track->url,
            'stream_url' => $track->url,
            'file_path' => $track->file_path,
            'duration_seconds' => $track->duration_seconds,
            'mime_type' => $track->mime_type,
            'file_size' => $track->file_size,
            'source' => $track->source,
            'is_active' => $track->is_active,
            'is_default' => $track->is_default,
            'sort_order' => $track->sort_order,
            'created_at' => optional($track->created_at)->toISOString(),
            'updated_at' => optional($track->updated_at)->toISOString(),
        ];
    }

    /**
     * Build a reasonably-unique slug for a title.
     */
    private function makeSlug(string $title): string
    {
        return Str::slug($title) . '-' . Str::lower(Str::random(6));
    }

    private function canUploadCatalogMusic(): bool
    {
        $user = Auth::user();

        if (! $user || ! method_exists($user, 'hasAnyRole')) {
            return false;
        }

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', self::ADMIN_CATALOG_ROLES)
            ->pluck('name')
            ->all();

        return $roles !== [] && $user->hasAnyRole($roles);
    }
}
