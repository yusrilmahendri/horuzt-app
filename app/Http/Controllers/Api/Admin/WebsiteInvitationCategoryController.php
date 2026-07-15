<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategoryThemas;
use App\Models\JenisThemas;
use App\Models\PaketUndangan;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebsiteInvitationCategoryController extends Controller
{
    private const PRIMARY_WEBSITE_THEMES = [
        'soft-ivory' => ['name' => 'Soft Ivory', 'category' => 'minimalis', 'package' => 'ruby', 'sort_order' => 10],
        'lavender-bloom' => ['name' => 'Lavender Bloom', 'category' => 'floral', 'package' => 'ruby', 'sort_order' => 20],
        'garden-whisper' => ['name' => 'Garden Whisper', 'category' => 'floral', 'package' => 'sapphire', 'sort_order' => 30],
        'champagne-rose' => ['name' => 'Champagne Rose', 'category' => 'elegant', 'package' => 'diamond', 'sort_order' => 40],
        'diamond-garden' => ['name' => 'Diamond Garden', 'category' => 'luxury', 'package' => 'diamond', 'sort_order' => 50],
    ];

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }

    /**
     * Display a listing of website invitation categories
     */
    public function index(Request $request)
    {
        try {
            $themes = JenisThemas::with('category')
                ->whereIn('slug', array_keys(self::PRIMARY_WEBSITE_THEMES))
                ->get()
                ->keyBy('slug');

            $items = collect(self::PRIMARY_WEBSITE_THEMES)
                ->map(fn (array $definition, string $slug) => $this->websiteThemePayload($slug, $definition, $themes->get($slug)))
                ->when($request->filled('search'), function ($collection) use ($request) {
                    $search = strtolower((string) $request->search);

                    return $collection->filter(fn ($item) => str_contains(strtolower($item['nama_kategori']), $search)
                        || str_contains(strtolower($item['slug']), $search));
                })
                ->when($request->has('status'), function ($collection) use ($request) {
                    $isActive = $request->status === 'active';

                    return $collection->filter(fn ($item) => (bool) $item['is_active'] === $isActive);
                })
                ->sortBy('urutan')
                ->values();

            $perPage = (int) $request->get('per_page', 15);
            $currentPage = (int) $request->get('page', 1);
            $pagedItems = $items->slice(($currentPage - 1) * $perPage, $perPage)->values();
            $categories = new LengthAwarePaginator(
                $pagedItems,
                $items->count(),
                $perPage,
                $currentPage
            );

            return response()->json([
                'status' => true,
                'data' => $categories->items(),
                'meta' => [
                    'current_page' => $categories->currentPage(),
                    'from' => $categories->firstItem(),
                    'to' => $categories->lastItem(),
                    'per_page' => $categories->perPage(),
                    'total' => $categories->total()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Website category index failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve website categories.'
            ], 500);
        }
    }

    /**
     * Store a newly created website invitation category and its synchronized theme
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'nama_kategori' => 'required|string|max:255',
                'slug' => 'nullable|string|max:255|unique:category_themas,slug',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'is_active' => 'nullable|in:true,false,1,0'
            ]);

            DB::beginTransaction();

            $slug = $request->slug ?: Str::slug($request->nama_kategori);

            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('website-categories', 'public');
            }

            // Create the category
            $category = CategoryThemas::create([
                'name' => $request->nama_kategori,
                'slug' => $slug,
                'image' => $imagePath,
                'is_active' => filter_var($request->get('is_active', true), FILTER_VALIDATE_BOOLEAN),
                'type' => 'website'
            ]);

            // Create synchronized theme with same data
            $theme = JenisThemas::create([
                'category_id' => $category->id,
                'name' => $request->nama_kategori,
                'slug' => $slug,
                'image' => $imagePath,
                'price' => '0', // Default values for theme-specific fields
                'preview' => '', // Default empty preview
                'url_thema' => '', // Default empty URL
                'is_active' => filter_var($request->get('is_active', true), FILTER_VALIDATE_BOOLEAN),
                'sort_order' => 0
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Website category and theme created successfully',
                'data' => [
                    'id' => $category->id,
                    'nama_kategori' => $category->name,
                    'slug' => $category->slug,
                    'image' => $category->image ? asset('storage/' . $category->image) : null,
                    'is_active' => $category->is_active,
                    'theme_id' => $theme->id
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Website category creation failed', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to create website category.'
            ], 500);
        }
    }

    /**
     * Display the specified website invitation category
     */
    public function show($id)
    {
        try {
            $theme = $this->resolveWebsiteTheme($id);
            $definition = self::PRIMARY_WEBSITE_THEMES[$theme->slug] ?? [
                'name' => $theme->name,
                'category' => $theme->category?->slug,
                'package' => $this->packageCodeForCategorySlug($theme->category?->slug),
                'sort_order' => $theme->sort_order,
            ];

            return response()->json([
                'status' => true,
                'data' => $this->websiteThemePayload($theme->slug, $definition, $theme)
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Website category not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Website category show failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve website category.'
            ], 500);
        }
    }

    /**
     * Update the specified website invitation category and its synchronized theme
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'master_theme_id' => 'sometimes|nullable|integer|exists:jenis_themas,id',
            ]);

            $theme = $request->filled('master_theme_id')
                ? JenisThemas::with('category')->findOrFail((int) $request->master_theme_id)
                : $this->resolveWebsiteTheme($id);

            $request->validate([
                'nama_kategori' => ['sometimes', 'required', 'string'],
                'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('jenis_themas', 'slug')->ignore($theme->id)],
                'image' => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'preview_image' => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'urutan' => ['sometimes', 'integer'],
                'is_active' => ['sometimes'],
                'status' => ['sometimes'],
            ]);

            DB::beginTransaction();

            $category = $theme->category;
            if (! $category) {
                throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(CategoryThemas::class);
            }

            // Handle image upload
            $imageFile = $request->file('preview_image') ?: $request->file('image');
            $imageUpload = null;
            if ($imageFile) {
                $imageUpload = $this->storeThemePreviewImage($imageFile, $theme->slug ?: Str::slug($theme->name));
                Log::info('[THEME_PREVIEW_UPLOAD]', [
                    'id' => $theme->id,
                    'slug' => $theme->slug,
                    'original_name' => $imageFile->getClientOriginalName(),
                    'size' => $imageFile->getSize(),
                    'path' => $imageUpload['path'],
                    'url' => $imageUpload['url'],
                ]);
            }

            $themePayload = [];

            if ($request->has('nama_kategori')) {
                $themePayload['name'] = $request->input('nama_kategori');
            }

            if ($request->has('slug')) {
                $themePayload['slug'] = $request->input('slug') ?: Str::slug($themePayload['name'] ?? $theme->name);
            }

            if ($imageUpload) {
                $themePayload['image'] = $imageUpload['url'];
                $themePayload['preview'] = $imageUpload['url'];
                $themePayload['preview_image'] = $imageUpload['url'];
                $themePayload['thumbnail_image'] = $imageUpload['url'];
            }

            if ($request->has('is_active') || $request->has('status')) {
                $themePayload['is_active'] = $this->resolveBooleanStatus($request, $theme->is_active);
            }

            if ($request->has('urutan')) {
                $themePayload['sort_order'] = (int) $request->urutan;
            }

            if ($themePayload !== []) {
                $theme->update($themePayload);
            }

            if ($imageUpload && $category->image !== $imageUpload['url']) {
                $category->update(['image' => $imageUpload['url']]);
            }

            DB::commit();
            $theme->refresh();
            $theme->load('category');

            return response()->json([
                'status' => true,
                'message' => 'Preview tema berhasil diperbarui.',
                'data' => $this->websiteThemePayload(
                    $theme->slug,
                    self::PRIMARY_WEBSITE_THEMES[$theme->slug] ?? [
                        'name' => $theme->name,
                        'category' => $theme->category?->slug,
                        'package' => $this->packageCodeForCategorySlug($theme->category?->slug),
                        'sort_order' => $theme->sort_order,
                    ],
                    $theme
                )
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Website category not found.'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Website category update failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to update website category.'
            ], 500);
        }
    }

    public function updatePreview(Request $request, $id)
    {
        try {
            $request->validate([
                'preview_image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ]);

            $theme = $this->resolveWebsiteTheme($id);
            $file = $request->file('preview_image');
            $upload = $this->storeThemePreviewImage($file, $theme->slug ?: $theme->name);

            $theme->forceFill([
                'image' => $upload['url'],
                'preview' => $upload['url'],
                'preview_image' => $upload['url'],
                'thumbnail_image' => $upload['url'],
            ])->save();

            if ($theme->category && $theme->category->image !== $upload['url']) {
                $theme->category->update(['image' => $upload['url']]);
            }

            Log::info('[THEME_PREVIEW_UPLOAD]', [
                'id' => $theme->id,
                'slug' => $theme->slug,
                'size' => $file->getSize(),
                'path' => $upload['path'],
                'url' => $upload['url'],
            ]);

            $theme->refresh();
            $theme->load('category');

            return response()->json([
                'status' => true,
                'message' => 'Preview tema berhasil diperbarui.',
                'data' => $this->previewPayload($theme),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('[THEME_PREVIEW_UPLOAD_FAILED]', [
                'id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Gagal memperbarui preview tema.',
            ], 500);
        }
    }

    /**
     * Remove the specified website invitation category and its synchronized theme
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $category = CategoryThemas::where('type', 'website')->findOrFail($id);

            // Delete image file if exists
            if ($category->image && Storage::disk('public')->exists($category->image)) {
                Storage::disk('public')->delete($category->image);
            }

            // Delete synchronized theme
            JenisThemas::where('category_id', $category->id)->delete();

            // Delete category
            $category->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Website category and theme deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Website category not found.'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Website category deletion failed', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete website category.'
            ], 500);
        }
    }

    /**
     * Toggle activation status for website invitation category and its theme
     */
    public function toggleActivation(Request $request, $id)
    {
        try {
            $request->validate([
                'is_active' => 'required|boolean'
            ]);

            DB::beginTransaction();

            $theme = $this->resolveWebsiteTheme($id);
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            $theme->update(['is_active' => $isActive]);

            DB::commit();

            $status = $isActive ? 'activated' : 'deactivated';

            return response()->json([
                'status' => true,
                'message' => "Website category {$status} successfully",
                'data' => $this->websiteThemePayload(
                    $theme->slug,
                    self::PRIMARY_WEBSITE_THEMES[$theme->slug] ?? [
                        'name' => $theme->name,
                        'category' => $theme->category?->slug,
                        'package' => $this->packageCodeForCategorySlug($theme->category?->slug),
                        'sort_order' => $theme->sort_order,
                    ],
                    $theme->fresh('category')
                )
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Website category not found.'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Website category activation toggle failed', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to update website category status.'
            ], 500);
        }
    }

    private function websiteThemePayload(string $slug, array $definition, ?JenisThemas $theme): array
    {
        $category = $theme?->category;
        $categorySlug = $category?->slug;
        $packageCode = $definition['package'] ?? $this->packageCodeForCategorySlug($categorySlug);
        $previewImage = $this->assetUrl(
            $theme?->getRawOriginal('preview_image')
                ?: $theme?->getRawOriginal('image')
                ?: $theme?->getRawOriginal('preview')
        );
        $thumbnailImage = $this->assetUrl(
            $theme?->getRawOriginal('thumbnail_image')
                ?: $theme?->getRawOriginal('preview_image')
                ?: $theme?->getRawOriginal('image')
                ?: $theme?->getRawOriginal('preview')
        );
        $isConnected = $theme !== null
            && $category !== null
            && $theme->slug === $slug
            && ($definition['category'] ?? $categorySlug) === $categorySlug;

        return [
            'id' => $theme?->id,
            'name' => $theme?->name ?? $definition['name'],
            'nama_kategori' => $theme?->name ?? $definition['name'],
            'slug' => $slug,
            'theme_slug' => $theme?->slug,
            'urutan' => (int) ($theme?->sort_order ?? $definition['sort_order'] ?? 0),
            'is_active' => (bool) ($theme?->is_active ?? false),
            'preview_image' => $previewImage,
            'thumbnail_image' => $thumbnailImage,
            'preview' => $previewImage,
            'image' => $previewImage,
            'master_theme_id' => $theme?->id,
            'master_theme_slug' => $theme?->slug,
            'category_user_id' => $category?->id,
            'category_user_slug' => $categorySlug,
            'category_slug' => $categorySlug,
            'package_required' => $packageCode,
            'package_code' => $packageCode,
            'package_required_detail' => $this->packagePayload($packageCode),
            'is_connected' => $isConnected,
            'status_terhubung' => $isConnected,
            'created_at' => $theme?->created_at,
            'updated_at' => $theme?->updated_at,
        ];
    }

    private function resolveWebsiteTheme($id): JenisThemas
    {
        $theme = JenisThemas::with('category')
            ->whereHas('category', fn ($query) => $query->where('type', 'website'))
            ->find($id);

        if ($theme) {
            return $theme;
        }

        $category = CategoryThemas::where('type', 'website')->findOrFail($id);

        return JenisThemas::with('category')
            ->where('category_id', $category->id)
            ->ordered()
            ->firstOrFail();
    }

    private function resolveBooleanStatus(Request $request, bool $fallback): bool
    {
        if ($request->has('is_active')) {
            return filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->has('status')) {
            $status = $request->input('status');

            if (is_string($status) && in_array(strtolower($status), ['active', 'inactive'], true)) {
                return strtolower($status) === 'active';
            }

            return filter_var($status, FILTER_VALIDATE_BOOLEAN);
        }

        return $fallback;
    }

    private function packageCodeForCategorySlug(?string $categorySlug): ?string
    {
        return match ($categorySlug) {
            'minimalis', 'floral' => 'ruby',
            'modern' => 'sapphire',
            'elegant', 'luxury' => 'diamond',
            default => null,
        };
    }

    private function packagePayload(?string $packageCode): ?array
    {
        if (! $packageCode) {
            return null;
        }

        $package = PaketUndangan::where('code', $packageCode)->first();

        return [
            'id' => $package?->id,
            'code' => $packageCode,
            'name' => PaketUndangan::displayLabelFromCode($packageCode),
        ];
    }

    private function assetUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/' . ltrim(preg_replace('#^/?storage/#', '', $path), '/'));
    }

    private function previewPayload(JenisThemas $theme): array
    {
        return [
            'id' => $theme->id,
            'name' => $theme->name,
            'nama_kategori' => $theme->name,
            'slug' => $theme->slug,
            'theme_slug' => $theme->slug,
            'image' => $theme->getRawOriginal('image'),
            'preview' => $theme->getRawOriginal('preview'),
            'preview_image' => $theme->getRawOriginal('preview_image'),
            'thumbnail_image' => $theme->getRawOriginal('thumbnail_image'),
            'updated_at' => $theme->updated_at,
        ];
    }

    /**
     * @return array{path:string,url:string}
     */
    private function storeThemePreviewImage(\Illuminate\Http\UploadedFile $file, ?string $slug): array
    {
        $safeSlug = Str::slug($slug ?: 'theme-preview') ?: 'theme-preview';
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $fileName = $safeSlug . '-' . now()->format('YmdHis') . '-' . Str::lower(Str::random(6)) . '.' . $extension;

        $path = $file->storeAs('theme-images/previews', $fileName, 'public');
        Storage::disk('public')->setVisibility($path, 'public');

        $url = Storage::disk('public')->url($path);
        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = rtrim((string) config('app.url'), '/') . '/' . ltrim($url, '/');
        }

        return ['path' => $path, 'url' => $url];
    }

    /**
     * Get website invitation categories statistics
     */
    public function statistics()
    {
        try {
            $stats = [
                'total_categories' => CategoryThemas::where('type', 'website')->count(),
                'active_categories' => CategoryThemas::where('type', 'website')->where('is_active', true)->count(),
                'inactive_categories' => CategoryThemas::where('type', 'website')->where('is_active', false)->count(),
                'categories_with_images' => CategoryThemas::where('type', 'website')->whereNotNull('image')->count(),
                'synchronized_themes' => JenisThemas::whereHas('category', function($q) {
                    $q->where('type', 'website');
                })->count()
            ];

            return response()->json([
                'status' => true,
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            Log::error('Website category statistics failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve statistics.'
            ], 500);
        }
    }
}
