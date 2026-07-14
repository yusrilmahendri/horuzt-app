<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\Photo\PhotoResource;
use App\Models\Galery;
use App\Models\Invitation;
use App\Models\PaketUndangan;
use App\Models\Setting;
use App\Services\PhotoImageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GaleryController extends Controller
{
    public function __construct(private PhotoImageService $photoImageService){
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
        $galery->file_path = $photoPath;
        $galery->photo_type = 'gallery';
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
        $query = Galery::where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('photo_type')
                    ->orWhere('photo_type', 'gallery');
            });

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Pagination (default 10)
        $perPage = $request->input('per_page', 10);
        $galleries = $query->orderByDesc('id')->paginate($perPage);

        // Transform data to ensure photo_url is included
        $galleries->getCollection()->transform(function ($gallery) {
            $rawPath = $gallery->file_path ?: $gallery->photo;
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

        $query = Galery::where('user_id', $ownerUserId)
            ->where(function ($query) {
                $query->whereNull('photo_type')
                    ->orWhere('photo_type', 'gallery');
            });

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
            $rawPath = $gallery->file_path ?: $gallery->photo;
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
        $galery = Galery::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $galery) {
            return response()->json([
                'message' => 'Galery tidak ditemukan.'
            ], 404);
        }

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

    public function photosIndex(Request $request)
    {
        $validated = $request->validate([
            'type' => 'nullable|in:gallery,collage',
        ]);

        $query = Galery::ownedBy((int) $request->user()->id)
            ->when(isset($validated['type']), fn ($query) => $query->where('photo_type', $validated['type']))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderByDesc('id');

        $photos = $query->get();

        if (isset($validated['type'])) {
            return response()->json([
                'message' => 'Data foto berhasil diambil.',
                'data' => PhotoResource::collection($photos),
            ]);
        }

        $grouped = $photos->groupBy(fn ($photo) => $photo->photo_type ?: 'gallery');

        return response()->json([
            'message' => 'Data foto berhasil diambil.',
            'data' => [
                'gallery' => PhotoResource::collection($grouped->get('gallery', collect()))->resolve(),
                'collage' => PhotoResource::collection($grouped->get('collage', collect()))->resolve(),
            ],
        ]);
    }

    public function storePhoto(Request $request)
    {
        $validated = $this->validatePhotoRequest($request, true);
        $userId = (int) $request->user()->id;
        $stored = null;

        try {
            $imageFile = $this->photoUploadFile($request);
            if ($imageFile) {
                $stored = $this->photoImageService->compressAndStore($imageFile, $userId, $validated['photo_type']);
            }

            $photo = DB::transaction(function () use ($validated, $stored, $userId) {
                $photoType = $validated['photo_type'];
                $isFeatured = (bool) ($validated['is_featured'] ?? false);
                $sortOrder = array_key_exists('sort_order', $validated)
                    ? (int) $validated['sort_order']
                    : $this->nextSortOrder($userId, $photoType);

                if ($isFeatured) {
                    $this->clearFeaturedPhotos($userId, $photoType);
                }

                $photo = new Galery([
                    'photo' => $stored['path'] ?? null,
                    'file_path' => $stored['path'] ?? null,
                    'photo_type' => $photoType,
                    'description' => $validated['description'] ?? null,
                    'url_video' => $validated['url_video'] ?? null,
                    'position' => $validated['position'] ?? 'center',
                    'display_mode' => $validated['display_mode'] ?? 'cover',
                    'focal_point_x' => $validated['focal_point_x'] ?? null,
                    'focal_point_y' => $validated['focal_point_y'] ?? null,
                    'is_featured' => $isFeatured,
                    'sort_order' => $sortOrder,
                    'original_name' => $stored['original_name'] ?? null,
                    'original_size' => $stored['original_size'] ?? null,
                    'compressed_size' => $stored['compressed_size'] ?? null,
                    'mime_type' => $stored['mime_type'] ?? null,
                    'quality' => $stored['quality'] ?? null,
                    'nama_foto' => $stored['original_name'] ?? null,
                    'status' => 1,
                ]);
                $photo->user_id = $userId;
                $photo->save();

                return $photo;
            });

            return (new PhotoResource($photo))->response()->setStatusCode(201);
        } catch (\Throwable $e) {
            if (isset($stored['path'])) {
                Storage::disk('public')->delete($stored['path']);
            }

            Log::error('Photo upload failed', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal memproses foto.',
            ], 500);
        }
    }

    public function updatePhoto(Request $request, int $id)
    {
        $validated = $this->validatePhotoRequest($request, false);
        $userId = (int) $request->user()->id;
        $photo = Galery::ownedBy($userId)->where('id', $id)->firstOrFail();
            $stored = null;
            $oldPath = $this->normalizeStoragePath($photo->file_path ?: $photo->photo);

        try {
            $imageFile = $this->photoUploadFile($request);
            if ($imageFile) {
                $stored = $this->photoImageService->compressAndStore(
                    $imageFile,
                    $userId,
                    $photo->photo_type ?: 'gallery'
                );
            }

            DB::transaction(function () use ($photo, $validated, $stored, $userId) {
                $updates = [];

                foreach ([
                    'description',
                    'position',
                    'display_mode',
                    'focal_point_x',
                    'focal_point_y',
                    'is_featured',
                    'sort_order',
                    'url_video',
                ] as $field) {
                    if (array_key_exists($field, $validated)) {
                        $updates[$field] = match ($field) {
                            'sort_order' => (int) $validated[$field],
                            'is_featured' => (bool) $validated[$field],
                            default => $validated[$field],
                        };
                    }
                }

                if (($updates['is_featured'] ?? false) === true) {
                    $this->clearFeaturedPhotos($userId, $photo->photo_type ?: 'gallery', (int) $photo->id);
                }

                if ($stored !== null) {
                    $updates = array_merge($updates, [
                        'photo' => $stored['path'],
                        'file_path' => $stored['path'],
                        'original_name' => $stored['original_name'],
                        'original_size' => $stored['original_size'],
                        'compressed_size' => $stored['compressed_size'],
                        'mime_type' => $stored['mime_type'],
                        'quality' => $stored['quality'],
                        'nama_foto' => $stored['original_name'],
                    ]);
                }

                $photo->fill($updates);
                $photo->save();
            });

            if ($stored !== null && $oldPath && $oldPath !== $stored['path']) {
                Storage::disk('public')->delete($oldPath);
            }

            return new PhotoResource($photo->refresh());
        } catch (\Throwable $e) {
            if (isset($stored['path'])) {
                Storage::disk('public')->delete($stored['path']);
            }

            Log::error('Photo update failed', [
                'user_id' => $userId,
                'photo_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal memperbarui foto.',
            ], 500);
        }
    }

    public function destroyPhoto(Request $request, int $id)
    {
        $userId = (int) $request->user()->id;
        $photo = Galery::ownedBy($userId)->where('id', $id)->firstOrFail();
        $path = $this->normalizeStoragePath($photo->file_path ?: $photo->photo);

        try {
            DB::transaction(fn () => $photo->delete());

            if ($path) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'message' => 'Foto berhasil dihapus.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Photo delete failed', [
                'user_id' => $userId,
                'photo_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal menghapus foto.',
            ], 500);
        }
    }

    public function sortPhotos(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|distinct',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        $userId = (int) $request->user()->id;
        $ids = collect($validated['items'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ownedCount = Galery::ownedBy($userId)->whereIn('id', $ids)->count();

        if ($ownedCount !== count($ids)) {
            return response()->json([
                'message' => 'Satu atau lebih foto tidak ditemukan.',
            ], 404);
        }

        DB::transaction(function () use ($validated, $userId) {
            $ids = collect($validated['items'])->pluck('id')->map(fn ($id) => (int) $id)->all();
            $photosById = Galery::ownedBy($userId)
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

            foreach ($validated['items'] as $item) {
                $photo = $photosById->get((int) $item['id']);

                if (! $photo) {
                    continue;
                }

                Galery::ownedBy($userId)
                    ->where('id', (int) $item['id'])
                    ->where('photo_type', $photo->photo_type ?: 'gallery')
                    ->update(['sort_order' => (int) $item['sort_order']]);
            }
        });

        return response()->json([
            'message' => 'Urutan foto berhasil diperbarui.',
        ]);
    }

    private function validatePhotoRequest(Request $request, bool $isCreate): array
    {
        $maxSizeKb = $this->maxPhotoSizeKb($request);
        $videoUrl = $this->videoUrlFromRequest($request);
        if ($videoUrl !== null) {
            $request->merge(['url_video' => $videoUrl]);
        }

        if (! $request->hasFile('image') && $request->hasFile('photo')) {
            $request->files->set('image', $request->file('photo'));
        }

        $imageIsRequired = $isCreate && $videoUrl === null;
        $rules = [
            'image' => [
                $imageIsRequired ? 'required' : 'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:' . $maxSizeKb,
            ],
            'photo' => ['nullable'],
            'url_video' => ['nullable', 'string', 'max:500'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'link_video' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
            'position' => ['nullable', 'in:center,top,bottom,left,right,top-left,top-right,bottom-left,bottom-right'],
            'object_position' => ['nullable', 'string', 'max:100'],
            'display_mode' => ['nullable', 'in:cover,contain'],
            'focal_point_x' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_with:focal_point_y'],
            'focal_point_y' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_with:focal_point_x'],
            'is_featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];

        if ($isCreate) {
            $rules['photo_type'] = ['required', 'in:gallery,collage'];
        }

        $validated = Validator::make($request->all(), $rules)->validate();
        $validated['url_video'] = $videoUrl;

        return $validated;
    }

    private function videoUrlFromRequest(Request $request): ?string
    {
        foreach (['url_video', 'video_url', 'link_video'] as $field) {
            $value = trim((string) $request->input($field, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function photoUploadFile(Request $request)
    {
        return $request->file('image') ?: $request->file('photo');
    }

    private function nextSortOrder(int $userId, string $photoType): int
    {
        return ((int) Galery::ownedBy($userId)
            ->where('photo_type', $photoType)
            ->max('sort_order')) + 1;
    }

    private function clearFeaturedPhotos(int $userId, string $photoType, ?int $exceptId = null): void
    {
        Galery::ownedBy($userId)
            ->where('photo_type', $photoType)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->update(['is_featured' => false]);
    }

    private function maxPhotoSizeKb(Request $request): int
    {
        return $this->userHasPlatinumPackage($request->user()) ? 8192 : 5120;
    }

    private function userHasPlatinumPackage($user): bool
    {
        $user->loadMissing('invitationOne.paketUndangan');
        $invitation = $user->invitationOne;
        $package = $invitation?->paketUndangan;

        $tier = $package ? PaketUndangan::tierCode($package->name_paket ?? null, $package->code ?? null) : null;
        if ($tier === 'diamond') {
            return true;
        }

        $labels = collect([
            $package?->code,
            $package?->name_paket,
            $package?->jenis_paket,
            $package?->name_paket_display,
            $package?->display_label,
        ])->merge($invitation?->packageNameHints() ?? []);

        return $labels
            ->filter()
            ->contains(function ($label) {
                $label = strtolower((string) $label);

                return str_contains($label, 'platinum') || str_contains($label, 'diamond');
            });
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
