<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Galery;
use App\Models\Invitation;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GaleryController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum')->except(['publicIndex']);
    }

    public function store(Request $request){
        $validateData = $request->validate([
            'photo' => 'nullable|file|mimes:jpg,png,jpeg|max:5222',
            'url_video' => 'nullable|url|max:500',
            'nama_foto' => 'nullable|string|max:255',
        ]);

        // Validasi: setidaknya salah satu harus ada (photo atau url_video)
        if (!$request->hasFile('photo') && !$request->filled('url_video')) {
            return response()->json([
                'message' => 'Setidaknya harus mengisi photo atau url_video.',
                'errors' => [
                    'photo' => ['Photo atau URL video harus diisi.'],
                    'url_video' => ['Photo atau URL video harus diisi.']
                ]
            ], 422);
        }

        $user = $request->user();
        $userId = $user?->id;
        $photoPath = null;

        // Proses upload photo jika ada
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('photos', 'public');
        }

        $galery = new Galery();
        $galery->photo = $photoPath;
        $galery->url_video = $validateData['url_video'] ?? null;
        $galery->nama_foto = $validateData['nama_foto'] ?? null;
        $galery->user_id = $userId;
        $galery->status = 1;

        if ($galery->save()) {
            return response()->json([
                'message' => 'Galery berhasil disimpan!',
                'data' => [
                    'id' => $galery->id,
                    'user_id' => $galery->user_id,
                    'photo' => $galery->photo,
                    'photo_url' => $this->publicStorageUrl($galery->photo),
                    'image_url' => $this->publicStorageUrl($galery->photo),
                    'preview_url' => $this->publicStorageUrl($galery->photo),
                    'url_video' => $galery->url_video,
                    'nama_foto' => $galery->nama_foto,
                    'status' => $galery->status,
                    'created_at' => $galery->created_at,
                    'updated_at' => $galery->updated_at,
                ],
            ], 201);
        } else {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan data galery.',
            ], 500);
        }
    }

    /**
     * List galery for authenticated user with optional query params: status, per_page
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Galery::where('user_id', $user->id);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Pagination (default 10)
        $perPage = $request->input('per_page', 10);
        $galleries = $query->orderByDesc('id')->paginate($perPage);

        // Transform data to ensure photo_url is included
        $galleries->getCollection()->transform(function ($gallery) {
            $rawPath = $gallery->photo;
            $cleanPath = $this->normalizeStoragePath($rawPath);
            $imageUrl = $this->publicStorageUrl($rawPath);
            $exists = $cleanPath ? Storage::disk('public')->exists($cleanPath) : false;

            Log::info('[GalleryImageScopeDebug]', [
                'context' => 'dashboard',
                'auth_user_id' => request()->user()->id ?? null,
                'domain' => null,
                'owner_user_id' => request()->user()->id ?? null,
                'raw_path' => $rawPath,
                'clean_path' => $cleanPath,
                'image_url' => $imageUrl,
                'exists' => $exists,
            ]);

            return [
                'id' => $gallery->id,
                'user_id' => $gallery->user_id,
                'photo' => $gallery->photo,
                'photo_url' => $imageUrl,
                'image_url' => $imageUrl,
                'preview_url' => $imageUrl,
                'url_video' => $gallery->url_video,
                'nama_foto' => $gallery->nama_foto,
                'status' => $gallery->status,
                'created_at' => $gallery->created_at,
                'updated_at' => $gallery->updated_at,
            ];
        });

        return response()->json([
            'message' => 'Data galery berhasil diambil.',
            'data' => $galleries->items(),
            'pagination' => [
                'current_page' => $galleries->currentPage(),
                'last_page' => $galleries->lastPage(),
                'per_page' => $galleries->perPage(),
                'total' => $galleries->total(),
                'from' => $galleries->firstItem(),
                'to' => $galleries->lastItem(),
            ]
        ]);
    }

    /**
     * Public endpoint - List gallery by domain for wedding invitation display
     * Query params: domain (required), status (optional), per_page (optional)
     */
    public function publicIndex(Request $request)
    {
        $validated = $request->validate([
            'domain' => 'required|string|max:255',
            'status' => 'nullable|in:0,1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $domain = $this->extractDomain($validated['domain']);
        $ownerUserId = $this->resolveOwnerUserIdByDomain($domain);

        if (! $ownerUserId) {
            return response()->json([
                'message' => 'Wedding profile not found for this domain.',
            ], 404);
        }

        $query = Galery::where('user_id', $ownerUserId);

        // Filter by status. For the public wedding view, default to active (status = 1)
        // when the caller does not explicitly request a status.
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        } else {
            $query->where('status', 1);
        }

        // Pagination (default 10)
        $perPage = $request->input('per_page', 10);
        $galleries = $query->orderByDesc('id')->paginate($perPage);

        // Transform data to ensure photo_url is included
        $galleries->getCollection()->transform(function ($gallery) {
            $rawPath = $gallery->photo;
            $cleanPath = $this->normalizeStoragePath($rawPath);
            $imageUrl = $this->publicStorageUrl($rawPath);
            $exists = $cleanPath ? Storage::disk('public')->exists($cleanPath) : false;

            Log::info('[GalleryImageScopeDebug]', [
                'context' => 'public',
                'auth_user_id' => request()->user()->id ?? null,
                'domain' => request()->query('domain'),
                'owner_user_id' => $gallery->user_id,
                'raw_path' => $rawPath,
                'clean_path' => $cleanPath,
                'image_url' => $imageUrl,
                'exists' => $exists,
            ]);

            return [
                'id' => $gallery->id,
                'user_id' => $gallery->user_id,
                'photo' => $gallery->photo,
                'photo_url' => $imageUrl,
                'image_url' => $imageUrl,
                'preview_url' => $imageUrl,
                'url_video' => $gallery->url_video,
                'nama_foto' => $gallery->nama_foto,
                'status' => $gallery->status,
                'created_at' => $gallery->created_at,
                'updated_at' => $gallery->updated_at,
            ];
        });

        return response()->json([
            'message' => 'Data galery berhasil diambil.',
            'data' => $galleries->items(),
            'pagination' => [
                'current_page' => $galleries->currentPage(),
                'last_page' => $galleries->lastPage(),
                'per_page' => $galleries->perPage(),
                'total' => $galleries->total(),
                'from' => $galleries->firstItem(),
                'to' => $galleries->lastItem(),
            ]
        ]);
    }

    /**
     * Hapus galery foto berdasarkan id dari query params
     */
    public function destroy(Request $request)
    {
        $id = $request->query('id');
        if (!$id) {
            return response()->json([
                'message' => 'Parameter id wajib diisi.'
            ], 400);
        }

        // Ownership check: only allow deleting the authenticated user's own gallery item.
        $galery = Galery::where('id', $id)->first();

        if (! $galery) {
            return response()->json([
                'message' => 'Galery tidak ditemukan.'
            ], 404);
        }

        if ((int) $galery->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses untuk menghapus galery ini.'
            ], 403);
        }

        $galery = Galery::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Hapus file foto dari storage jika ada
        $cleanPath = $this->normalizeStoragePath($galery->photo);

        if ($cleanPath && Storage::disk('public')->exists($cleanPath)) {
            Storage::disk('public')->delete($cleanPath);
        }

        $galery->delete();

        return response()->json([
            'message' => 'Galery berhasil dihapus.'
        ]);
    }

    private function extractDomain(string $domain): string
    {
        $domain = trim($domain);
        $parsed = parse_url($domain);

        if (is_array($parsed)) {
            $path = trim((string) ($parsed['path'] ?? ''), '/');

            if ($path !== '') {
                $segments = explode('/', $path);
                $candidate = trim((string) end($segments));

                if ($candidate !== '') {
                    return strtolower($candidate);
                }
            }

            $parsedHost = trim((string) ($parsed['host'] ?? ''));
            if ($parsedHost !== '') {
                return strtolower($parsedHost);
            }
        }

        return strtolower(trim($domain, '/'));
    }

    private function resolveOwnerUserIdByDomain(string $domain): ?int
    {
        if ($domain === '') {
            return null;
        }

        $ownerUserId = Setting::query()
            ->whereRaw('LOWER(domain) = ?', [$domain])
            ->value('user_id');

        if ($ownerUserId) {
            return (int) $ownerUserId;
        }

        return Invitation::query()
            ->whereHas('user.settingOne', function ($query) use ($domain) {
                $query->whereRaw('LOWER(domain) = ?', [$domain]);
            })
            ->value('user_id');
    }

    private function normalizeStoragePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = trim($path);

        $path = preg_replace('#^https?://[^/]+/storage/#', '', $path);
        $path = preg_replace('#^/storage/#', '', $path);
        $path = preg_replace('#^storage/#', '', $path);
        $path = ltrim($path, '/');

        return $path ?: null;
    }

    private function publicStorageUrl(?string $path): ?string
    {
        $cleanPath = $this->normalizeStoragePath($path);

        if (! $cleanPath) {
            return null;
        }

        if (! Storage::disk('public')->exists($cleanPath)) {
            Log::warning('[MissingImageFile]', [
                'original_path' => $path,
                'clean_path' => $cleanPath,
            ]);

            return null;
        }

        return Storage::disk('public')->url($cleanPath);
    }
}
