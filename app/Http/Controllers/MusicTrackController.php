<?php

namespace App\Http\Controllers;

use App\Models\MusicTrack;
use App\Models\Setting;
use App\Services\MusicResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        $tracks = MusicTrack::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('title', 'asc')
            ->get()
            ->map(function (MusicTrack $track) {
                return [
                    'id' => $track->id,
                    'title' => $track->title,
                    'artist' => $track->artist,
                    'source' => $track->source,
                    'duration_seconds' => $track->duration_seconds,
                    'is_default' => $track->is_default,
                    'url' => $track->url,
                ];
            });

        return response()->json([
            'message' => 'Music tracks retrieved successfully.',
            'data' => $tracks,
        ], 200);
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
                'setting' => $setting->fresh(),
                'music_info' => $this->resolver->resolveInfo($setting->fresh()),
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

            if ($setting && $setting->music_track_id) {
                $setting->update(['music_track_id' => null]);
            }

            return response()->json([
                'message' => 'Music selection cleared successfully.',
                'setting' => $setting ? $setting->fresh() : null,
                'music_info' => $setting ? $this->resolver->resolveInfo($setting->fresh()) : null,
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
}
