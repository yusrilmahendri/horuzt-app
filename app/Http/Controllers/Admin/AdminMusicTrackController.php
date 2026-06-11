<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MusicTrack;
use App\Services\MusicStreamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminMusicTrackController extends Controller
{
    protected MusicStreamService $musicStreamService;

    public function __construct(MusicStreamService $musicStreamService)
    {
        $this->musicStreamService = $musicStreamService;
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
            ->get();

        return response()->json([
            'message' => 'Music tracks retrieved successfully.',
            'data' => $tracks,
        ], 200);
    }

    /**
     * POST /api/v1/admin/music-tracks
     * form-data: musik (file), title, artist?, is_default?, sort_order?, source?
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'musik' => ['required', 'file', 'mimes:mp3,wav,ogg,m4a', 'max:10240'],
            'title' => ['required', 'string', 'max:255'],
            'artist' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'source' => ['nullable', 'string', 'max:50'],
            'external_id' => ['nullable', 'string', 'max:100'],
        ]);

        $file = $request->file('musik');

        // Defense-in-depth: also run the service-level audio validation.
        if (!$this->musicStreamService->validateAudioFile($file)) {
            return response()->json([
                'message' => 'Invalid audio file format or size.',
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request, $validated, $file) {
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('public/music/catalog', $fileName);

                $makeDefault = $request->boolean('is_default');

                $track = MusicTrack::create([
                    'title' => $validated['title'],
                    'artist' => $validated['artist'] ?? null,
                    'slug' => $this->makeSlug($validated['title']),
                    'file_path' => $filePath,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
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

                return response()->json([
                    'message' => 'Music track created successfully.',
                    'data' => $track->fresh(),
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Admin music track create failed', [
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to create music track.',
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
            return response()->json(['message' => 'Music track not found.'], 404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'artist' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'source' => ['nullable', 'string', 'max:50'],
            'external_id' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            return DB::transaction(function () use ($request, $validated, $track) {
                $data = [];

                foreach (['title', 'artist', 'sort_order', 'source', 'external_id'] as $field) {
                    if (array_key_exists($field, $validated)) {
                        $data[$field] = $validated[$field];
                    }
                }

                if ($request->has('is_active')) {
                    $data['is_active'] = $request->boolean('is_active');
                }

                $makeDefault = $request->has('is_default') ? $request->boolean('is_default') : null;

                if ($makeDefault === true) {
                    // A default track must be active.
                    $data['is_default'] = true;
                    $data['is_active'] = true;
                } elseif ($makeDefault === false) {
                    $data['is_default'] = false;
                }

                $track->update($data);

                if ($makeDefault === true) {
                    $this->promoteDefault($track->fresh());
                }

                return response()->json([
                    'message' => 'Music track updated successfully.',
                    'data' => $track->fresh(),
                ], 200);
            });
        } catch (\Exception $e) {
            Log::error('Admin music track update failed', [
                'admin_id' => Auth::id(),
                'track_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to update music track.',
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
            return response()->json(['message' => 'Music track not found.'], 404);
        }

        try {
            return DB::transaction(function () use ($track) {
                // Default must be active.
                $track->update(['is_default' => true, 'is_active' => true]);
                $this->promoteDefault($track->fresh());

                return response()->json([
                    'message' => 'Default music track updated successfully.',
                    'data' => $track->fresh(),
                ], 200);
            });
        } catch (\Exception $e) {
            Log::error('Admin set default track failed', [
                'admin_id' => Auth::id(),
                'track_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to set default music track.',
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
            return response()->json(['message' => 'Music track not found.'], 404);
        }

        // Guard: never let the active default track be deactivated, otherwise
        // it would leave is_default=true + is_active=false (confusing state the
        // resolver ignores). Admin must set another default first.
        if ($track->is_default && $track->is_active) {
            return response()->json([
                'message' => 'Default music track cannot be deactivated. Please set another default track first.'
            ], 422);
        }

        $track->update(['is_active' => !$track->is_active]);

        return response()->json([
            'message' => 'Music track status updated successfully.',
            'data' => $track->fresh(),
        ], 200);
    }

    /**
     * DELETE /api/v1/admin/music-tracks/{id}
     * Hard delete + remove file. settings.music_track_id is null-ed by FK.
     */
    public function destroy($id)
    {
        $track = MusicTrack::find($id);

        if (!$track) {
            return response()->json(['message' => 'Music track not found.'], 404);
        }

        try {
            if ($track->file_path) {
                Storage::delete($track->file_path);
            }

            $track->delete();

            return response()->json([
                'message' => 'Music track deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Admin music track delete failed', [
                'admin_id' => Auth::id(),
                'track_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to delete music track.',
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

    /**
     * Build a reasonably-unique slug for a title.
     */
    private function makeSlug(string $title): string
    {
        return Str::slug($title) . '-' . Str::lower(Str::random(6));
    }
}
