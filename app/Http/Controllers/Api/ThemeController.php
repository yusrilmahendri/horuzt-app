<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoryThemas;
use App\Models\JenisThemas;
use App\Models\ResultThemas;
use App\Models\PaketUndangan;
use App\Services\AccountStatusService;
use App\Services\PackageThemeAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ThemeController extends Controller
{
    public function __construct(
        private PackageThemeAccessService $themeAccess,
        private AccountStatusService $accountStatus
    )
    {
        $this->middleware('auth:sanctum')->except([
            'getCategories',
            'getThemesByCategory',
            'getTheme',
            'getThemesByLayout',
            'searchThemes',
            'getDemoUrl',
            'getPopularThemes',
        ]);
    }

    /**
     * Get all active categories with their themes for user selection
     */
    public function getCategories(Request $request)
    {
        try {
            $type = $request->get('type', 'website'); // Default to website themes
            $package = $this->resolvePackageContext($request);
            $selectedThemeId = $this->selectedThemeId($request);

            if (!in_array($type, ['website', 'video'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid theme type. Must be website or video.'
                ], 400);
            }

            $categories = CategoryThemas::with(['jenisThemas' => function($query) {
                $query->active()
                    ->whereNotNull('slug')
                    ->where('slug', '!=', '')
                    ->ordered()
                    ->select(
                        'id',
                        'category_id',
                        'name',
                        'slug',
                        'price',
                        'preview',
                        'preview_image',
                        'thumbnail_image',
                        'image',
                        'demo_url',
                        'url_thema',
                        'features',
                        'sort_order',
                        'description',
                        'is_active'
                    );
            }])
            ->where('type', $type)
            ->where('is_active', true)
            ->ordered()
            ->select('id', 'name', 'slug', 'type', 'description', 'icon', 'sort_order', 'is_active')
            ->get();

            $categories = $categories->map(function ($category) use ($package, $selectedThemeId) {
                $category->setRelation('jenisThemas', $category->jenisThemas
                    ->map(fn ($theme) => $this->themeAccessPayload($theme, $package, $selectedThemeId))
                    ->values());

                return $category;
            })->filter(function($category) {
                return $category->jenisThemas->count() > 0;
            })->values();

            return response()->json([
                'status' => true,
                'data' => [
                    'type' => $type,
                    'categories' => $categories,
                    'total_categories' => $categories->count(),
                    'total_themes' => $categories->sum(function($cat) {
                        return $cat->jenisThemas->count();
                    })
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get categories for user failed', [
                'error' => $e->getMessage(),
                'type' => $request->get('type')
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve theme categories.'
            ], 500);
        }
    }

    /**
     * Get themes by category for user selection
     */
    public function getThemesByCategory(Request $request, $categoryId)
    {
        try {
            $package = $this->resolvePackageContext($request);
            $selectedThemeId = $this->selectedThemeId($request);

            $category = CategoryThemas::where('is_active', true)
                ->where('type', 'website')
                ->findOrFail($categoryId);

            $themes = JenisThemas::with('category:id,name,type,slug')
                ->where('category_id', $categoryId)
                ->active()
                ->ordered()
                ->select('id', 'category_id', 'name', 'slug', 'price', 'preview', 'preview_image', 'thumbnail_image', 'image', 'demo_url', 'features', 'description', 'url_thema', 'sort_order')
                ->get()
                ->map(fn ($theme) => $this->themeAccessPayload($theme, $package, $selectedThemeId));

            return response()->json([
                'status' => true,
                'data' => [
                    'category' => $category,
                    'themes' => $themes,
                    'total_themes' => $themes->count()
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found or inactive.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get themes by category failed', [
                'category_id' => $categoryId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve themes.'
            ], 500);
        }
    }

    /**
     * Get a specific theme details
     */
    public function getTheme($themeId)
    {
        try {
            $request = request();
            $package = $this->resolvePackageContext($request);
            $selectedThemeId = $this->selectedThemeId($request);
            $theme = JenisThemas::with(['category' => function($query) {
                $query->where('is_active', true);
            }])
            ->where('is_active', true)
            ->findOrFail($themeId);

            // Check if category is active
            if (!$theme->category) {
                return response()->json([
                    'status' => false,
                    'message' => 'Theme category is inactive.'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $this->themeAccessPayload($theme, $package, $selectedThemeId)
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Theme not found or inactive.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get theme details failed', [
                'theme_id' => $themeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve theme details.'
            ], 500);
        }
    }

    /**
     * Select a theme for the authenticated user
     */
    public function selectTheme(Request $request)
    {
        try {
            $request->validate([
                'theme_id' => 'required|integer|exists:jenis_themas,id'
            ]);

            $user = Auth::user();
            $accountSummary = $this->accountStatus->summary($user);

            if (! in_array($accountSummary['account_status'], [
                AccountStatusService::STATUS_ONBOARDING,
                AccountStatusService::STATUS_ACTIVE,
            ], true)) {
                return response()->json([
                    'status' => false,
                    'code' => match ($accountSummary['account_status']) {
                        AccountStatusService::STATUS_UNVERIFIED => 'ACCOUNT_NOT_VERIFIED',
                        AccountStatusService::STATUS_EXPIRED => 'ACCOUNT_EXPIRED',
                        default => 'PAYMENT_NOT_CONFIRMED',
                    },
                    'message' => match ($accountSummary['account_status']) {
                        AccountStatusService::STATUS_UNVERIFIED => 'Verifikasi akun terlebih dahulu.',
                        AccountStatusService::STATUS_EXPIRED => 'Masa aktif akun sudah berakhir.',
                        default => 'Pembayaran belum dikonfirmasi.',
                    },
                    'data' => $accountSummary,
                ], 403);
            }

            $package = $this->themeAccess->packageForUser($user);
            $accessibleCategorySlugs = $package
                ? $this->themeAccess->accessibleCategoriesForPackage($package)->pluck('slug')->values()->all()
                : [];

            // Verify theme is active and available
            $theme = JenisThemas::with(['category' => function($query) {
                $query->where('is_active', true)->where('type', 'website');
            }])
            ->where('id', $request->theme_id)
            ->where('is_active', true)
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->first();

            Log::info('Theme selection validation snapshot', [
                'user_id' => $user?->id,
                'email' => $user?->email,
                'package' => [
                    'id' => $package?->id,
                    'code' => $package?->code,
                    'name' => $package?->name_paket_display ?? $package?->name_paket,
                ],
                'requested_theme_id' => (int) $request->theme_id,
                'accessible_category_slugs' => $accessibleCategorySlugs,
                'resolved_theme' => [
                    'id' => $theme?->id,
                    'name' => $theme?->name,
                    'slug' => $theme?->slug,
                    'is_active' => $theme?->is_active,
                ],
                'resolved_theme_category' => [
                    'id' => $theme?->category?->id,
                    'slug' => $theme?->category?->slug,
                    'name' => $theme?->category?->name,
                    'type' => $theme?->category?->type,
                    'is_active' => $theme?->category?->is_active,
                ],
            ]);

            if (!$theme || !$theme->category) {
                Log::warning('[ThemeSelectUnavailable]', [
                    'user_id' => $user?->id,
                    'email' => $user?->email,
                    'requested_theme_id' => $request->theme_id,
                    'theme' => $theme ? [
                        'id' => $theme->id,
                        'name' => $theme->name,
                        'slug' => $theme->slug,
                        'category_id' => $theme->category_id,
                        'is_active' => $theme->is_active,
                    ] : null,
                    'category' => $theme?->category ? [
                        'id' => $theme->category->id,
                        'name' => $theme->category->name,
                        'slug' => $theme->category->slug,
                        'type' => $theme->category->type,
                        'is_active' => $theme->category->is_active,
                    ] : null,
                    'package' => $package ? [
                        'id' => $package->id,
                        'code' => $package->code ?? null,
                        'name' => $package->name ?? null,
                        'jenis_paket' => $package->jenis_paket ?? null,
                    ] : null,
                    'latest_invitations' => DB::table('invitations')
                        ->where('user_id', $user?->id)
                        ->orderByDesc('id')
                        ->limit(5)
                        ->get(),
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'Selected theme is not available.'
                ], 400);
            }

            $canAccessTheme = $this->themeAccess->canPackageAccessTheme($package, $theme);

            Log::info('Theme selection package access decision', [
                'user_id' => $user?->id,
                'package' => [
                    'id' => $package?->id,
                    'code' => $package?->code,
                    'name' => $package?->name_paket_display ?? $package?->name_paket,
                ],
                'theme' => [
                    'id' => $theme->id,
                    'name' => $theme->name,
                    'slug' => $theme->slug,
                ],
                'theme_category' => [
                    'id' => $theme->category?->id,
                    'slug' => $theme->category?->slug,
                    'name' => $theme->category?->name,
                ],
                'accessible_category_slugs' => $accessibleCategorySlugs,
                'can_package_access_theme' => $canAccessTheme,
            ]);

            if (! $canAccessTheme) {
                Log::warning('[ThemeSelectDenied]', [
                    'user_id' => $user?->id,
                    'email' => $user?->email,
                    'requested_theme_id' => $request->theme_id,
                    'theme' => $theme ? [
                        'id' => $theme->id,
                        'name' => $theme->name,
                        'slug' => $theme->slug,
                        'category_id' => $theme->category_id,
                        'is_active' => $theme->is_active,
                    ] : null,
                    'category' => $theme?->category ? [
                        'id' => $theme->category->id,
                        'name' => $theme->category->name,
                        'slug' => $theme->category->slug,
                        'type' => $theme->category->type,
                        'is_active' => $theme->category->is_active,
                    ] : null,
                    'package' => $package ? [
                        'id' => $package->id,
                        'code' => $package->code ?? null,
                        'name' => $package->name ?? null,
                        'jenis_paket' => $package->jenis_paket ?? null,
                    ] : null,
                    'latest_invitations' => DB::table('invitations')
                        ->where('user_id', $user?->id)
                        ->orderByDesc('id')
                        ->limit(5)
                        ->get(),
                    'package_access_categories' => $package
                        ? DB::table('paket_undangan_category_thema as pc')
                            ->join('category_themas as c', 'c.id', '=', 'pc.category_thema_id')
                            ->where('pc.paket_undangan_id', $package->id)
                            ->select('c.id', 'c.slug', 'c.name', 'c.type', 'c.is_active')
                            ->get()
                        : [],
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'Tema ini membutuhkan upgrade paket.',
                    'data' => [
                        'theme' => $this->themeAccessPayload($theme, $package, null),
                    ],
                ], 403);
            }

            DB::beginTransaction();

            // Remove existing theme selection for this user
            ResultThemas::where('user_id', $user->id)->delete();

            // Create new theme selection
            $resultThema = ResultThemas::create([
                'user_id' => $user->id,
                'jenis_id' => $request->theme_id,
                'thema_id' => null, // Set to null for new jenis_themas system
                'selected_at' => now()
            ]);

            DB::commit();

            Log::info('Theme selected successfully', [
                'user_id' => $user?->id,
                'package' => [
                    'id' => $package?->id,
                    'code' => $package?->code,
                    'name' => $package?->name_paket_display ?? $package?->name_paket,
                ],
                'theme' => [
                    'id' => $theme->id,
                    'name' => $theme->name,
                    'slug' => $theme->slug,
                ],
                'theme_category' => [
                    'id' => $theme->category?->id,
                    'slug' => $theme->category?->slug,
                    'name' => $theme->category?->name,
                ],
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Theme selected successfully',
                'data' => [
                    'theme' => $theme,
                    'selection' => $resultThema
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Theme selection failed', [
                'user_id' => Auth::id(),
                'theme_id' => $request->theme_id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to select theme.'
            ], 500);
        }
    }

    /**
     * Get the currently selected theme for the authenticated user
     */
    public function getSelectedTheme(Request $request)
    {
        try {
            $user = Auth::user();

            $selectedTheme = ResultThemas::with([
                'jenisThema' => function($query) {
                    $query->with('category:id,name,slug,type,is_active');
                }
            ])
            ->where('user_id', $user->id)
            ->latest('selected_at')
            ->first();

            if (!$selectedTheme) {
                return response()->json([
                    'status' => true,
                    'data' => null,
                    'message' => 'No theme selected.'
                ], 200);
            }

            $package = $this->themeAccess->packageForUser($user);
            $theme = $selectedTheme->jenisThema;
            $themeCategoryId = (int) ($theme?->category_id ?? 0);
            $pivotExists = $package && $themeCategoryId > 0
                ? DB::table('paket_undangan_category_thema')
                    ->where('paket_undangan_id', (int) $package->id)
                    ->where('category_thema_id', $themeCategoryId)
                    ->exists()
                : false;
            $canAccessTheme = $this->themeAccess->canPackageAccessTheme($package, $theme);

            Log::info('Selected theme access audit', [
                'user_id' => $user?->id,
                'selected_result_themas_id' => $selectedTheme->id,
                'jenis_id' => $selectedTheme->jenis_id,
                'theme_slug' => $theme?->slug,
                'theme_category_id' => $themeCategoryId,
                'theme_category_slug' => $theme?->category?->slug,
                'package_id' => (int) ($package?->id ?? 0),
                'package_code' => $package?->code,
                'pivot_exists' => $pivotExists,
                'can_package_access_theme' => $canAccessTheme,
            ]);

            if (! $selectedTheme->jenisThema
                || ! $selectedTheme->jenisThema->category
                || ! $canAccessTheme) {
                return response()->json([
                    'status' => true,
                    'data' => null,
                    'message' => 'Selected theme is no longer available for this package.'
                ], 200);
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'theme' => $selectedTheme->jenisThema,
                    'selected_at' => $selectedTheme->selected_at
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get selected theme failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve selected theme.'
            ], 500);
        }
    }

    /**
     * Get themes by layout type (Scroll, Slide, Mobile)
     */
    public function getThemesByLayout(Request $request)
    {
        try {
            $layout = $request->get('layout');
            $type = $request->get('type', 'website');
            $package = $this->resolvePackageContext($request);
            $selectedThemeId = $this->selectedThemeId($request);

            if (!$layout) {
                return response()->json([
                    'status' => false,
                    'message' => 'Layout parameter is required.'
                ], 400);
            }

            if (!in_array($type, ['website', 'video'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid theme type. Must be website or video.'
                ], 400);
            }

            // Search themes by layout in name or features
            $themes = JenisThemas::with(['category' => function($query) use ($type) {
                $query->where('type', $type)->where('is_active', true);
            }])
            ->whereHas('category', function($query) use ($type) {
                $query->where('type', $type)->where('is_active', true);
            })
            ->where('is_active', true)
            ->where(function($query) use ($layout) {
                $query->where('name', 'LIKE', '%' . $layout . '%')
                      ->orWhere('description', 'LIKE', '%' . $layout . '%')
                      ->orWhereJsonContains('features', $layout);
            })
            ->ordered()
            ->select('id', 'category_id', 'name', 'slug', 'price', 'preview', 'preview_image', 'thumbnail_image', 'image', 'demo_url', 'features', 'description', 'url_thema', 'sort_order')
            ->get()
            ->map(fn ($theme) => $this->themeAccessPayload($theme, $package, $selectedThemeId));

            return response()->json([
                'status' => true,
                'data' => [
                    'layout' => $layout,
                    'type' => $type,
                    'themes' => $themes,
                    'total' => $themes->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get themes by layout failed', [
                'layout' => $request->get('layout'),
                'type' => $request->get('type'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve themes by layout.'
            ], 500);
        }
    }

    /**
     * Search themes with advanced filters
     */
    public function searchThemes(Request $request)
    {
        try {
            $query = $request->get('query', '');
            $type = $request->get('type', 'website');
            $categoryId = $request->get('category_id');
            $priceMin = $request->get('price_min');
            $priceMax = $request->get('price_max');
            $layout = $request->get('layout');
            $limit = $request->get('limit', 20);
            $package = $this->resolvePackageContext($request);
            $selectedThemeId = $this->selectedThemeId($request);

            if (!in_array($type, ['website', 'video'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid theme type.'
                ], 400);
            }

            $themes = JenisThemas::with(['category' => function($categoryQuery) use ($type) {
                $categoryQuery->where('type', $type)->where('is_active', true);
            }])
            ->whereHas('category', function($categoryQuery) use ($type) {
                $categoryQuery->where('type', $type)->where('is_active', true);
            })
            ->where('is_active', true);

            // Text search
            if (!empty($query)) {
                $themes->where(function($searchQuery) use ($query) {
                    $searchQuery->where('name', 'LIKE', '%' . $query . '%')
                               ->orWhere('description', 'LIKE', '%' . $query . '%');
                });
            }

            // Category filter
            if ($categoryId) {
                $themes->where('category_id', $categoryId);
            }

            // Price range filter
            if ($priceMin !== null) {
                $themes->where('price', '>=', $priceMin);
            }
            if ($priceMax !== null) {
                $themes->where('price', '<=', $priceMax);
            }

            // Layout filter
            if ($layout) {
                $themes->where(function($layoutQuery) use ($layout) {
                    $layoutQuery->where('name', 'LIKE', '%' . $layout . '%')
                               ->orWhere('description', 'LIKE', '%' . $layout . '%')
                               ->orWhereJsonContains('features', $layout);
                });
            }

            $results = $themes->ordered()
                             ->limit($limit)
                             ->select('id', 'category_id', 'name', 'slug', 'price', 'preview', 'preview_image', 'thumbnail_image', 'image', 'demo_url', 'features', 'description', 'url_thema', 'sort_order')
                             ->get()
                             ->map(fn ($theme) => $this->themeAccessPayload($theme, $package, $selectedThemeId));

            return response()->json([
                'status' => true,
                'data' => [
                    'query' => $query,
                    'filters' => [
                        'type' => $type,
                        'category_id' => $categoryId,
                        'price_range' => [$priceMin, $priceMax],
                        'layout' => $layout
                    ],
                    'themes' => $results,
                    'total' => $results->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Search themes failed', [
                'query' => $request->get('query'),
                'filters' => $request->only(['type', 'category_id', 'price_min', 'price_max', 'layout']),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Search failed.'
            ], 500);
        }
    }

    /**
     * Get demo URL for a theme (public access)
     */
    public function getDemoUrl($themeId)
    {
        try {
            $theme = JenisThemas::where('is_active', true)
                               ->whereHas('category', function($query) {
                                   $query->where('is_active', true);
                               })
                               ->select('id', 'name', 'demo_url', 'url_thema')
                               ->findOrFail($themeId);

            $demoUrl = $theme->demo_url ?: $theme->url_thema;

            if (!$demoUrl) {
                return response()->json([
                    'status' => false,
                    'message' => 'Demo not available for this theme.'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'theme_id' => $theme->id,
                    'theme_name' => $theme->name,
                    'demo_url' => $demoUrl
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Theme not found or inactive.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get demo URL failed', [
                'theme_id' => $themeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve demo URL.'
            ], 500);
        }
    }

    /**
     * Get popular themes (most selected)
     */
    public function getPopularThemes(Request $request)
    {
        try {
            $type = $request->get('type', 'website');
            $limit = $request->get('limit', 10);
            $package = $this->resolvePackageContext($request);
            $selectedThemeId = $this->selectedThemeId($request);

            if (!in_array($type, ['website', 'video'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid theme type.'
                ], 400);
            }

            // select() must come before withCount() so the count alias is not
            // dropped from the final SELECT (which breaks orderBy result_themas_count).
            $popularThemes = JenisThemas::select(
                    'id',
                    'category_id',
                    'name',
                    'slug',
                    'price',
                    'preview',
                    'preview_image',
                    'thumbnail_image',
                    'image',
                    'demo_url',
                    'features',
                    'url_thema',
                    'sort_order'
                )
                ->with(['category:id,name,type,slug'])
                ->withCount('resultThemas')
                ->whereHas('category', function ($query) use ($type) {
                    $query->where('type', $type)->where('is_active', true);
                })
                ->where('is_active', true)
                ->orderByDesc('result_themas_count')
                ->orderBy('name', 'asc')
                ->limit((int) $limit)
                ->get()
                ->map(fn ($theme) => $this->themeAccessPayload($theme, $package, $selectedThemeId));

            return response()->json([
                'status' => true,
                'message' => 'Popular themes retrieved successfully.',
                'data' => [
                    'type' => $type,
                    'themes' => $popularThemes,
                    'total' => $popularThemes->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get popular themes failed', [
                'error' => $e->getMessage(),
                'type' => $request->get('type')
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve popular themes.'
            ], 500);
        }
    }

    private function themeAccessPayload(JenisThemas $theme, ?PaketUndangan $package, ?int $selectedThemeId): array
    {
        if (! $theme->relationLoaded('category')) {
            $theme->load('category');
        }

        $targetPackage = $this->themeAccess->minimumPackageForTheme($theme);
        $canUse = $package
            ? $this->themeAccess->canPackageAccessTheme($package, $theme)
            : false;

        return [
            'id' => $theme->id,
            'name' => $theme->name,
            'slug' => $theme->slug,
            'category' => $theme->category ? [
                'id' => $theme->category->id,
                'name' => $theme->category->name,
                'slug' => $theme->category->slug,
                'type' => $theme->category->type,
            ] : null,
            'package_required' => $this->packagePayload($targetPackage),
            'can_preview' => true,
            'can_use' => $canUse,
            'is_current_theme' => $selectedThemeId !== null && (int) $selectedThemeId === (int) $theme->id,
            'upgrade_required' => $package !== null && ! $canUse,
            'target_package' => $canUse ? null : $this->packagePayload($targetPackage),
            'price' => $theme->price,
            'preview' => $theme->preview,
            'preview_image' => $theme->preview_image,
            'thumbnail_image' => $theme->thumbnail_image,
            'image' => $theme->image,
            'demo_url' => $theme->demo_url,
            'url_thema' => $theme->url_thema,
            'features' => $theme->features,
            'description' => $theme->description,
            'sort_order' => $theme->sort_order,
        ];
    }

    private function packagePayload(?PaketUndangan $package): ?array
    {
        if (! $package) {
            return null;
        }

        return [
            'id' => $package->id,
            'code' => $package->code,
            'name' => PaketUndangan::displayLabelFromCode($package->code, $package->name_paket),
        ];
    }

    private function selectedThemeId(Request $request): ?int
    {
        if (! $request->user()) {
            return null;
        }

        $selected = ResultThemas::query()
            ->where('user_id', $request->user()->id)
            ->latest('selected_at')
            ->first();

        return $selected?->jenis_id ? (int) $selected->jenis_id : null;
    }

    private function resolvePackageContext(Request $request): ?PaketUndangan
    {
        if ($request->user()) {
            return $this->themeAccess->packageForUser($request->user());
        }

        $identifier = $request->query('package_code')
            ?? $request->query('package_id')
            ?? $request->query('package');

        return $this->themeAccess->packageFromCodeOrId($identifier);
    }

}
