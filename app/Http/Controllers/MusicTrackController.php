<?php

namespace App\Http\Controllers;

use App\Models\ExternalMusicTrack;
use App\Models\MusicTrack;
use App\Models\Setting;
use App\Services\ExternalMusicCatalogService;
use App\Services\MusicResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MusicTrackController extends Controller
{
    protected MusicResolverService $resolver;
    protected ExternalMusicCatalogService $externalCatalogService;

    public function __construct(MusicResolverService $resolver, ExternalMusicCatalogService $externalCatalogService)
    {
        $this->resolver = $resolver;
        $this->externalCatalogService = $externalCatalogService;
    }

    /**
     * Public catalog list. Returns active tracks only.
     * GET /api/music/tracks
     */
    public function index(Request $request)
    {
        $tracks = $this->adminCatalogPayload();
        $globalCatalog = $this->globalCatalogPayload();
        $catalogSections = [
            'user_uploads' => [],
            'admin_catalog' => $tracks,
            'global_catalog' => $globalCatalog,
        ];

        return response()->json([
            'message' => 'Katalog musik berhasil diambil.',
            'data' => $tracks,
            'catalog' => $tracks,
            'catalog_sections' => $catalogSections,
        ], 200);
    }

    /**
     * Legacy authenticated catalog endpoint used by the local FE.
     * GET /api/v1/user/music-options
     */
    public function options(Request $request)
    {
        $baseResponse = $this->index($request)->getData(true);
        $setting = Setting::where('user_id', Auth::id())->first();
        $state = $this->resolver->selectionState($setting, Auth::user());

        $customUploads = $state['custom_music'] ? [$state['custom_music']] : [];
        $catalogSections = [
            'user_uploads' => $customUploads,
            'admin_catalog' => $baseResponse['catalog_sections']['admin_catalog'] ?? [],
            'global_catalog' => $baseResponse['catalog_sections']['global_catalog'] ?? [],
        ];

        return response()->json(array_merge($baseResponse, $state, [
            'catalog_sections' => $catalogSections,
        ]), 200);
    }

    /**
     * Legacy authenticated current selection endpoint used by the local FE.
     * GET /api/v1/user/music-selection
     */
    public function selection()
    {
        $setting = Setting::where('user_id', Auth::id())->first();

        return response()->json([
            'message' => 'Music selection retrieved successfully.',
            'data' => $this->resolver->selectionState($setting, Auth::user()),
            'setting' => $setting,
            'music_info' => $this->safeResolveInfo($setting),
            ...$this->resolver->selectionState($setting, Auth::user()),
        ], 200);
    }

    /**
     * Legacy authenticated selection update endpoint used by the local FE.
     * PUT /api/v1/user/music-selection
     */
    public function updateSelection(Request $request)
    {
        if (! $this->hasAnyCatalogSelectionSchema()) {
            return response()->json([
                'message' => 'Music catalog is not available.',
            ], 422);
        }

        $validated = $request->validate([
            'source_type' => 'nullable|string|in:admin_catalog,global_catalog',
            'track_id' => 'nullable|integer',
            'music_id' => 'nullable|integer',
            'external_track_id' => 'nullable|integer',
            'global_music_id' => 'nullable|integer',
        ]);

        $sourceType = $validated['source_type'] ?? null;
        $trackId = $validated['music_id'] ?? $validated['track_id'] ?? null;
        $externalTrackId = $validated['global_music_id'] ?? $validated['external_track_id'] ?? null;

        if (empty($trackId) && empty($externalTrackId) && $sourceType === null) {
            return $this->clearSelection();
        }

        if ($sourceType === 'global_catalog' || (!empty($externalTrackId) && empty($trackId))) {
            $request->merge(['external_track_id' => $externalTrackId]);
            return $this->selectGlobalTrack($request);
        }

        $request->merge(['track_id' => $trackId]);
        return $this->selectTrack($request);
    }

    /**
     * Authenticated user selects a catalog track.
     * POST /api/music/select-track  { track_id }
     *
     * Does NOT touch settings.musik: a Diamond user's custom upload keeps
     * priority, while the selection is stored as a fallback.
     */
    public function selectTrack(Request $request)
    {
        if (! $this->hasCatalogSelectionSchema()) {
            return response()->json([
                'message' => 'Music catalog is not available.',
            ], 422);
        }

        $validated = $request->validate([
            'track_id' => 'required|integer|exists:music_tracks,id',
        ]);

        $track = MusicTrack::where('id', $validated['track_id'])
            ->where('is_active', true)
            ->first();

        if (!$track) {
            return response()->json([
                'message' => 'Selected track is not available.',
            ], 422);
        }

        try {
            $user = Auth::user();
            $updatePayload = [
                'music_track_id' => $track->id,
            ];

            if (Schema::hasColumn('settings', 'music_source_type')) {
                $updatePayload['music_source_type'] = 'admin_catalog';
            }
            if (Schema::hasColumn('settings', 'external_music_track_id')) {
                $updatePayload['external_music_track_id'] = null;
            }

            $setting = Setting::updateOrCreate(
                ['user_id' => $user->id],
                $updatePayload
            );

            return response()->json([
                'message' => 'Music track selected successfully.',
                'data' => $this->resolver->selectionState($setting->fresh(['musicTrack']), $user),
                'setting' => $setting->fresh(),
                'music_info' => $this->safeResolveInfo($setting->fresh()),
                ...$this->resolver->selectionState($setting->fresh(['musicTrack']), $user),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Music track selection failed', [
                'user_id' => Auth::id(),
                'track_id' => $validated['track_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to select music track.',
            ], 500);
        }
    }

    /**
     * Authenticated user clears their catalog selection.
     * POST /api/music/clear-selection
     *
     * Falls back to default track (or custom upload if present).
     */
    public function clearSelection()
    {
        try {
            $user = Auth::user();
            $setting = Setting::where('user_id', $user->id)->first();

            if ($setting) {
                $payload = [];

                if (Schema::hasColumn('settings', 'music_track_id')) {
                    $payload['music_track_id'] = null;
                }
                if (Schema::hasColumn('settings', 'external_music_track_id')) {
                    $payload['external_music_track_id'] = null;
                }
                if (Schema::hasColumn('settings', 'music_source_type')) {
                    $payload['music_source_type'] = null;
                }

                if (!empty($payload)) {
                    $setting->update($payload);
                }
            }

            return response()->json([
                'message' => 'Music selection cleared successfully.',
                'data' => $this->resolver->selectionState($setting ? $setting->fresh(['musicTrack']) : null, $user),
                'setting' => $setting ? $setting->fresh() : null,
                'music_info' => $setting ? $this->safeResolveInfo($setting->fresh()) : null,
                ...$this->resolver->selectionState($setting ? $setting->fresh(['musicTrack']) : null, $user),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Music selection clear failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to clear music selection.',
            ], 500);
        }
    }

    /**
     * Resolve music only when the catalog schema exists. This keeps legacy
     * endpoints from failing on older local databases after rollback.
     */
    private function safeResolveInfo(?Setting $setting): ?array
    {
        if (! Schema::hasTable('music_tracks') && ! Schema::hasTable('external_music_tracks')) {
            return null;
        }

        return $this->resolver->resolveInfo($setting);
    }

    private function hasCatalogSelectionSchema(): bool
    {
        return Schema::hasTable('music_tracks')
            && Schema::hasTable('settings')
            && Schema::hasColumn('settings', 'music_track_id');
    }

    private function hasGlobalSelectionSchema(): bool
    {
        return Schema::hasTable('external_music_tracks')
            && Schema::hasTable('settings')
            && Schema::hasColumn('settings', 'external_music_track_id');
    }

    private function hasAnyCatalogSelectionSchema(): bool
    {
        return $this->hasCatalogSelectionSchema() || $this->hasGlobalSelectionSchema();
    }

    /**
     * Select an external/global cached track.
     */
    private function selectGlobalTrack(Request $request)
    {
        if (! $this->hasGlobalSelectionSchema()) {
            return response()->json([
                'message' => 'Global music catalog is not available.',
            ], 422);
        }

        $validated = $request->validate([
            'external_track_id' => 'required|integer|exists:external_music_tracks,id',
        ]);

        $track = ExternalMusicTrack::where('id', $validated['external_track_id'])
            ->where('is_active', true)
            ->first();

        if (!$track) {
            return response()->json([
                'message' => 'Selected global track is not available.',
            ], 422);
        }

        try {
            $user = Auth::user();
            $updatePayload = [];

            if (Schema::hasColumn('settings', 'music_source_type')) {
                $updatePayload['music_source_type'] = 'global_catalog';
            }
            if (Schema::hasColumn('settings', 'external_music_track_id')) {
                $updatePayload['external_music_track_id'] = $track->id;
            }
            if (Schema::hasColumn('settings', 'music_track_id')) {
                $updatePayload['music_track_id'] = null;
            }

            if (empty($updatePayload)) {
                return response()->json([
                    'message' => 'Global music selection schema is not ready.',
                ], 422);
            }

            $setting = Setting::updateOrCreate(
                ['user_id' => $user->id],
                $updatePayload
            );

            return response()->json([
                'message' => 'Global music track selected successfully.',
                'data' => $this->resolver->selectionState($setting->fresh(['externalMusicTrack', 'musicTrack']), $user),
                'setting' => $setting->fresh(),
                'music_info' => $this->safeResolveInfo($setting->fresh()),
                ...$this->resolver->selectionState($setting->fresh(['externalMusicTrack', 'musicTrack']), $user),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Global music track selection failed', [
                'user_id' => Auth::id(),
                'external_track_id' => $validated['external_track_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to select global music track.',
            ], 500);
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function adminCatalogPayload(): array
    {
        if (! Schema::hasTable('music_tracks')) {
            return [];
        }

        return MusicTrack::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('title', 'asc')
            ->get()
            ->map(fn (MusicTrack $track) => $this->resolver->trackPayload($track))
            ->values()
            ->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function globalCatalogPayload(): array
    {
        if (! Schema::hasTable('external_music_tracks')) {
            return [];
        }

        return $this->externalCatalogService->activeCatalog()
            ->map(fn (ExternalMusicTrack $track) => $this->resolver->externalTrackPayload($track))
            ->values()
            ->all();
    }
}
