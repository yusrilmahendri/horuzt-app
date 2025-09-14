<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoryThemas;
use App\Models\JenisThemas;
use App\Models\ResultThemas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ThemeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->only(['selectTheme', 'getSelectedTheme']);
    }

    /**
     * Get all active categories with their themes for user selection
     */
    public function getCategories(Request $request)
    {
        try {
            $type = $request->get('type', 'website'); // Default to website themes

            if (!in_array($type, ['website', 'video'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid theme type. Must be website or video.'
                ], 400);
            }

            $categories = CategoryThemas::with(['jenisThemas' => function($query) {
                $query->active()->ordered()
                    ->select('id', 'category_id', 'name', 'price', 'preview', 'preview_image', 'thumbnail_image', 'image', 'demo_url', 'features', 'sort_order', 'description');
            }])
            ->where('type', $type)
            ->where('is_active', true)
            ->ordered()
            ->select('id', 'name', 'slug', 'type', 'description', 'icon', 'sort_order')
            ->get();

            // Filter out categories with no active themes
            $categories = $categories->filter(function($category) {
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
            $category = CategoryThemas::where('is_active', true)->findOrFail($categoryId);

            $themes = JenisThemas::with('category:id,name,type,slug')
                ->where('category_id', $categoryId)
                ->active()
                ->ordered()
                ->select('id', 'category_id', 'name', 'price', 'preview', 'preview_image', 'thumbnail_image', 'image', 'demo_url', 'features', 'description', 'url_thema', 'sort_order')
                ->get();

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
                'data' => $theme
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

            // Verify theme is active and available
            $theme = JenisThemas::with(['category' => function($query) {
                $query->where('is_active', true);
            }])
            ->where('id', $request->theme_id)
            ->where('is_active', true)
            ->first();

            if (!$theme || !$theme->category) {
                return response()->json([
                    'status' => false,
                    'message' => 'Selected theme is not available.'
                ], 400);
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
                    $query->with('category:id,name,type');
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
            ->select('id', 'category_id', 'name', 'price', 'preview', 'preview_image', 'thumbnail_image', 'image', 'demo_url', 'features', 'description', 'url_thema', 'sort_order')
            ->get();

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
                             ->select('id', 'category_id', 'name', 'price', 'preview', 'preview_image', 'thumbnail_image', 'image', 'demo_url', 'features', 'description', 'url_thema', 'sort_order')
                             ->get();

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

            if (!in_array($type, ['website', 'video'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid theme type.'
                ], 400);
            }

            $popularThemes = JenisThemas::with(['category:id,name,type,slug'])
                ->withCount('resultThemas')
                ->whereHas('category', function($query) use ($type) {
                    $query->where('type', $type)->where('is_active', true);
                })
                ->where('is_active', true)
                ->orderBy('result_themas_count', 'desc')
                ->orderBy('name', 'asc')
                ->limit($limit)
                ->select('id', 'category_id', 'name', 'price', 'preview', 'preview_image', 'thumbnail_image', 'image', 'demo_url', 'features', 'url_thema', 'sort_order')
                ->get();

            return response()->json([
                'status' => true,
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
}
