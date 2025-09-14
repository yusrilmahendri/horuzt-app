<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJenisThemaRequest;
use App\Models\JenisThemas;
use App\Models\CategoryThemas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Intervention\Image\Facades\Image;

class JenisThemaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }

    /**
     * Display a listing of jenis themes with filtering and pagination
     */
    public function index(Request $request)
    {
        try {
            $query = JenisThemas::with(['category:id,name,type,is_active']);

            // Filter by category
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Filter by category type
            if ($request->has('type') && in_array($request->type, ['website', 'video'])) {
                $query->whereHas('category', function($q) use ($request) {
                    $q->where('type', $request->type);
                });
            }

            // Filter by status
            if ($request->has('status')) {
                $isActive = $request->status === 'active';
                $query->where('is_active', $isActive);
            }

            // Search by name
            if ($request->has('search') && !empty($request->search)) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            // Order results
            $query->ordered();

            // Pagination
            $perPage = $request->get('per_page', 15);
            $themes = $query->paginate($perPage);

            return response()->json([
                'status' => true,
                'data' => $themes,
                'summary' => [
                    'total_themes' => JenisThemas::count(),
                    'active_themes' => JenisThemas::where('is_active', true)->count(),
                    'website_themes' => JenisThemas::withActiveCategory()->whereHas('category', function($q) {
                        $q->where('type', 'website');
                    })->count(),
                    'video_themes' => JenisThemas::withActiveCategory()->whereHas('category', function($q) {
                        $q->where('type', 'video');
                    })->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Jenis themes index failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve themes.'
            ], 500);
        }
    }

    /**
     * Store a newly created jenis theme
     */
    public function store(StoreJenisThemaRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            
            // Handle image upload
            if ($request->hasFile('preview_image')) {
                $data['preview_image'] = $this->handleImageUpload($request->file('preview_image'), 'preview');
                $data['thumbnail_image'] = $this->handleImageUpload($request->file('preview_image'), 'thumbnail');
            }

            $jenisThema = JenisThemas::create($data);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Theme created successfully',
                'data' => $jenisThema->load('category')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Jenis theme creation failed', [
                'error' => $e->getMessage(),
                'data' => $request->except(['preview_image'])
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to create theme.'
            ], 500);
        }
    }

    /**
     * Display the specified jenis theme
     */
    public function show($id)
    {
        try {
            $jenisThema = JenisThemas::with('category')->findOrFail($id);

            return response()->json([
                'status' => true,
                'data' => $jenisThema
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Theme not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Jenis theme show failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve theme.'
            ], 500);
        }
    }

    /**
     * Update the specified jenis theme
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'category_id' => 'required|integer|exists:category_themas,id',
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'preview' => 'required|string|max:500',
                'url_thema' => 'required|url|max:500',
                'is_active' => 'boolean',
                'description' => 'nullable|string|max:1000',
                'demo_url' => 'nullable|url|max:500',
                'sort_order' => 'integer|min:0',
                'features' => 'nullable|array',
                'features.*' => 'string|max:255'
            ]);

            DB::beginTransaction();

            $jenisThema = JenisThemas::findOrFail($id);
            
            $data = $request->validated();
            if (!isset($data['is_active'])) {
                $data['is_active'] = $request->boolean('is_active', $jenisThema->is_active);
            }

            $jenisThema->update($data);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Theme updated successfully',
                'data' => $jenisThema->load('category')
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Theme not found.'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Jenis theme update failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to update theme.'
            ], 500);
        }
    }

    /**
     * Toggle theme activation status
     */
    public function toggleActivation(Request $request, $id)
    {
        try {
            $request->validate([
                'is_active' => 'required|boolean'
            ]);

            DB::beginTransaction();

            $jenisThema = JenisThemas::findOrFail($id);
            $jenisThema->update(['is_active' => $request->is_active]);

            DB::commit();

            $status = $request->is_active ? 'activated' : 'deactivated';
            
            return response()->json([
                'status' => true,
                'message' => "Theme {$status} successfully",
                'data' => $jenisThema->fresh(['category'])
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Theme not found.'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Theme activation toggle failed', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to update theme status.'
            ], 500);
        }
    }

    /**
     * Remove the specified jenis theme
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $jenisThema = JenisThemas::findOrFail($id);
            
            // Check if theme is being used by users
            if ($jenisThema->resultThemas()->count() > 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot delete theme that is being used by users.'
                ], 400);
            }

            // Delete associated images
            if ($jenisThema->preview_image) {
                $this->deleteImage($jenisThema->preview_image);
            }
            if ($jenisThema->thumbnail_image) {
                $this->deleteImage($jenisThema->thumbnail_image);
            }

            $jenisThema->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Theme deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Theme not found.'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Theme deletion failed', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete theme.'
            ], 500);
        }
    }

    /**
     * Bulk update theme sort orders
     */
    public function updateSortOrder(Request $request)
    {
        try {
            $request->validate([
                'themes' => 'required|array',
                'themes.*.id' => 'required|integer|exists:jenis_themas,id',
                'themes.*.sort_order' => 'required|integer|min:0'
            ]);

            DB::beginTransaction();

            foreach ($request->themes as $themeData) {
                JenisThemas::where('id', $themeData['id'])
                    ->update(['sort_order' => $themeData['sort_order']]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Sort order updated successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Theme sort order update failed', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to update sort order.'
            ], 500);
        }
    }

    /**
     * Get available categories for theme creation
     */
    public function getCategories(Request $request)
    {
        try {
            $query = CategoryThemas::select('id', 'name', 'type', 'is_active');

            // Filter by type if specified
            if ($request->has('type') && in_array($request->type, ['website', 'video'])) {
                $query->where('type', $request->type);
            }

            // Only active categories by default
            if (!$request->has('include_inactive')) {
                $query->where('is_active', true);
            }

            $categories = $query->ordered()->get();

            return response()->json([
                'status' => true,
                'data' => $categories
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get categories failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve categories.'
            ], 500);
        }
    }

    /**
     * Bulk toggle activation for multiple themes
     */
    public function bulkToggleActivation(Request $request)
    {
        try {
            $request->validate([
                'theme_ids' => 'required|array',
                'theme_ids.*' => 'integer|exists:jenis_themas,id',
                'is_active' => 'required|boolean'
            ]);

            DB::beginTransaction();

            $updated = JenisThemas::whereIn('id', $request->theme_ids)
                ->update(['is_active' => $request->is_active]);

            DB::commit();

            $status = $request->is_active ? 'activated' : 'deactivated';
            
            return response()->json([
                'status' => true,
                'message' => "{$updated} themes {$status} successfully",
                'data' => [
                    'updated_count' => $updated,
                    'theme_ids' => $request->theme_ids
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk theme activation failed', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to update themes status.'
            ], 500);
        }
    }

    /**
     * Handle image upload and processing
     */
    private function handleImageUpload($file, $type = 'preview')
    {
        try {
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            
            if ($type === 'thumbnail') {
                // Create thumbnail (300x200)
                $image = Image::make($file)
                    ->fit(300, 200, function ($constraint) {
                        $constraint->aspectRatio();
                    })
                    ->encode('jpg', 85);
                
                $path = 'theme-images/thumbnails/' . $filename;
            } else {
                // Create preview image (800x600)
                $image = Image::make($file)
                    ->fit(800, 600, function ($constraint) {
                        $constraint->aspectRatio();
                    })
                    ->encode('jpg', 90);
                
                $path = 'theme-images/previews/' . $filename;
            }
            
            Storage::disk('public')->put($path, $image);
            
            return Storage::disk('public')->url($path);
            
        } catch (\Exception $e) {
            Log::error('Image upload failed', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Delete image file
     */
    private function deleteImage($imageUrl)
    {
        try {
            if ($imageUrl) {
                $path = str_replace(Storage::disk('public')->url(''), '', $imageUrl);
                Storage::disk('public')->delete($path);
            }
        } catch (\Exception $e) {
            Log::error('Image deletion failed', [
                'image_url' => $imageUrl,
                'error' => $e->getMessage()
            ]);
        }
    }
}