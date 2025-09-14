<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategoryThemas;
use App\Models\JenisThemas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoInvitationCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }

    /**
     * Display a listing of video invitation categories
     */
    public function index(Request $request)
    {
        try {
            $query = CategoryThemas::where('type', 'video');

            // Search by name
            if ($request->has('search') && !empty($request->search)) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            // Filter by status
            if ($request->has('status')) {
                $isActive = $request->status === 'active';
                $query->where('is_active', $isActive);
            }

            // Order results
            $query->ordered();

            // Pagination
            $perPage = $request->get('per_page', 15);
            $categories = $query->paginate($perPage);

            return response()->json([
                'status' => true,
                'data' => $categories->map(function($category) {
                    return [
                        'id' => $category->id,
                        'nama_kategori' => $category->name,
                        'slug' => $category->slug,
                        'image' => $category->image ? asset('storage/' . $category->image) : null,
                        'is_active' => $category->is_active,
                        'created_at' => $category->created_at,
                        'updated_at' => $category->updated_at
                    ];
                }),
                'meta' => [
                    'current_page' => $categories->currentPage(),
                    'from' => $categories->firstItem(),
                    'to' => $categories->lastItem(),
                    'per_page' => $categories->perPage(),
                    'total' => $categories->total()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Video category index failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve video categories.'
            ], 500);
        }
    }

    /**
     * Store a newly created video invitation category and its synchronized theme
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
                $imagePath = $request->file('image')->store('video-categories', 'public');
            }

            // Create the category
            $category = CategoryThemas::create([
                'name' => $request->nama_kategori,
                'slug' => $slug,
                'image' => $imagePath,
                'is_active' => filter_var($request->get('is_active', true), FILTER_VALIDATE_BOOLEAN),
                'type' => 'video'
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
                'message' => 'Video category and theme created successfully',
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
            Log::error('Video category creation failed', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to create video category.'
            ], 500);
        }
    }

    /**
     * Display the specified video invitation category
     */
    public function show($id)
    {
        try {
            $category = CategoryThemas::where('type', 'video')->findOrFail($id);

            return response()->json([
                'status' => true,
                'data' => [
                    'id' => $category->id,
                    'nama_kategori' => $category->name,
                    'slug' => $category->slug,
                    'image' => $category->image ? asset('storage/' . $category->image) : null,
                    'is_active' => $category->is_active,
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Video category not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Video category show failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve video category.'
            ], 500);
        }
    }

    /**
     * Update the specified video invitation category and its synchronized theme
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'nama_kategori' => 'required|string|max:255',
                'slug' => 'nullable|string|max:255|unique:category_themas,slug,' . $id,
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'is_active' => 'nullable|in:true,false,1,0'
            ]);

            DB::beginTransaction();

            $category = CategoryThemas::where('type', 'video')->findOrFail($id);

            $slug = $request->slug ?: Str::slug($request->nama_kategori);

            // Handle image upload
            $imagePath = $category->image;
            if ($request->hasFile('image')) {
                // Delete old image
                if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                    Storage::disk('public')->delete($imagePath);
                }
                $imagePath = $request->file('image')->store('video-categories', 'public');
            }

            // Update the category
            $category->update([
                'name' => $request->nama_kategori,
                'slug' => $slug,
                'image' => $imagePath,
                'is_active' => filter_var($request->get('is_active', true), FILTER_VALIDATE_BOOLEAN)
            ]);

            // Update synchronized theme with same data
            $theme = JenisThemas::where('category_id', $category->id)->first();
            if ($theme) {
                $theme->update([
                    'name' => $request->nama_kategori,
                    'slug' => $slug,
                    'image' => $imagePath,
                    'is_active' => filter_var($request->get('is_active', true), FILTER_VALIDATE_BOOLEAN)
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Video category and theme updated successfully',
                'data' => [
                    'id' => $category->id,
                    'nama_kategori' => $category->name,
                    'slug' => $category->slug,
                    'image' => $category->image ? asset('storage/' . $category->image) : null,
                    'is_active' => $category->is_active
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Video category not found.'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Video category update failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to update video category.'
            ], 500);
        }
    }

    /**
     * Remove the specified video invitation category and its synchronized theme
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $category = CategoryThemas::where('type', 'video')->findOrFail($id);

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
                'message' => 'Video category and theme deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Video category not found.'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Video category deletion failed', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete video category.'
            ], 500);
        }
    }

    /**
     * Toggle activation status for video invitation category and its theme
     */
    public function toggleActivation(Request $request, $id)
    {
        try {
            $request->validate([
                'is_active' => 'required|boolean'
            ]);

            DB::beginTransaction();

            $category = CategoryThemas::where('type', 'video')->findOrFail($id);
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            $category->update(['is_active' => $isActive]);

            // Update synchronized theme
            JenisThemas::where('category_id', $category->id)
                      ->update(['is_active' => $isActive]);

            DB::commit();

            $status = $isActive ? 'activated' : 'deactivated';

            return response()->json([
                'status' => true,
                'message' => "Video category {$status} successfully",
                'data' => [
                    'id' => $category->id,
                    'nama_kategori' => $category->name,
                    'slug' => $category->slug,
                    'image' => $category->image ? asset('storage/' . $category->image) : null,
                    'is_active' => $category->is_active
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Video category not found.'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Video category activation toggle failed', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to update video category status.'
            ], 500);
        }
    }

    /**
     * Get video invitation categories statistics
     */
    public function statistics()
    {
        try {
            $stats = [
                'total_categories' => CategoryThemas::where('type', 'video')->count(),
                'active_categories' => CategoryThemas::where('type', 'video')->where('is_active', true)->count(),
                'inactive_categories' => CategoryThemas::where('type', 'video')->where('is_active', false)->count(),
                'categories_with_images' => CategoryThemas::where('type', 'video')->whereNotNull('image')->count(),
                'synchronized_themes' => JenisThemas::whereHas('category', function($q) {
                    $q->where('type', 'video');
                })->count()
            ];

            return response()->json([
                'status' => true,
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            Log::error('Video category statistics failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve statistics.'
            ], 500);
        }
    }
}
