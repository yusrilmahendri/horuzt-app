<?php

namespace App\Http\Controllers;

use App\Models\MusicTrack;
use App\Models\Setting;
use App\Services\MusicResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MusicTrackController extends Controller
{
    protected MusicResolverService $resolver;

    public function __construct(MusicResolverService $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Public catalog list. Returns active tracks only.
     * GET /api/music/tracks
     */
    public function index(Request $request)
    {
        if (! Schema::hasTable('music_tracks')) {
            return response()->json([
                'message' => 'Music tracks table is not available.',
                'data' => [],
                'catalog' => [],
            ], 200);
        }

        $tracks = MusicTrack::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('title', 'asc')
            ->get()
            ->map(fn (MusicTrack $track) => $this->resolver->trackPayload($track));

        return response()->json([
            'message' => 'Music tracks retrieved successfully.',
            'data' => $tracks,
            'catalog' => $tracks,
        ], 200);
    }

    /**
     * Legacy authenticated catalog endpoint used by the local FE.
     * GET /api/v1/user/music-options
     */
    public function options(Request $request)
    {
        $response = $this->index($request);
        $payload = $response->getData(true);
        $setting = Setting::where('user_id', Auth::id())->first();
        $state = $this->resolver->selectionState($setting, Auth::user());

        return response()->json(array_merge($payload, $state), 200);
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
        if (! $this->hasCatalogSelectionSchema()) {
            return response()->json([
                'message' => 'Music catalog is not available.',
            ], 422);
        }

        $validated = $request->validate([
            'track_id' => 'nullable|integer|exists:music_tracks,id',
            'music_id' => 'nullable|integer|exists:music_tracks,id',
        ]);

        $trackId = $validated['music_id'] ?? $validated['track_id'] ?? null;

        if (empty($trackId)) {
            return $this->clearSelection();
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

            $setting = Setting::updateOrCreate(
                ['user_id' => $user->id],
                ['music_track_id' => $track->id]
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

            if ($setting && Schema::hasColumn('settings', 'music_track_id') && $setting->music_track_id) {
                $setting->update(['music_track_id' => null]);
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
        if (! Schema::hasTable('music_tracks')) {
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
}
