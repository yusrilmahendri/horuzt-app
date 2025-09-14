<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\CategoryThemas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CategoryThemaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }

    /**
     * Display a listing of categories with filtering and pagination
     */
    public function index(Request $request)
    {
        try {
            $query = CategoryThemas::with(['jenisThemas' => function($q) {
                $q->select('id', 'category_id', 'name', 'is_active')->ordered();
            }]);

            // Filter by type
            if ($request->has('type') && in_array($request->type, ['website', 'video'])) {
                $query->where('type', $request->type);
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
            $categories = $query->paginate($perPage);

            return response()->json([
                'status' => true,
                'data' => $categories,
                'summary' => [
                    'total_categories' => CategoryThemas::count(),
                    'active_categories' => CategoryThemas::where('is_active', true)->count(),
                    'website_categories' => CategoryThemas::where('type', 'website')->count(),
                    'video_categories' => CategoryThemas::where('type', 'video')->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Category index failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve categories.'
            ], 500);
        }
    }

    /**
     * Store a newly created category
     */
    public function store(StoreCategoryRequest $request)
    {
        try {
            DB::beginTransaction();

            $category = CategoryThemas::create($request->validated());

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Category created successfully',
                'data' => $category->load('jenisThemas')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Category creation failed', [
                'error' => $e->getMessage(),
                'data' => $request->validated()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to create category.'
            ], 500);
        }
    }

    /**
     * Display the specified category
     */
    public function show($id)
    {
        try {
            $category = CategoryThemas::with(['jenisThemas' => function($q) {
                $q->ordered();
            }])->findOrFail($id);

            return response()->json([
                'status' => true,
                'data' => $category
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Category show failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve category.'
            ], 500);
        }
    }

    /**
     * Update the specified category
     */
    public function update(UpdateCategoryRequest $request, $id = null)
    {
        try {
            $categoryId = $id ?? $request->input('id');
            
            DB::beginTransaction();

            $category = CategoryThemas::findOrFail($categoryId);
            $category->update($request->validated());

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Category updated successfully',
                'data' => $category->load('jenisThemas')
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Category not found.'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Category update failed', [
                'id' => $categoryId ?? 'unknown',
                'error' => $e->getMessage(),
                'data' => $request->validated()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to update category.'
            ], 500);
        }
    }

    /**
     * Toggle category activation status
     */
    public function toggleActivation(Request $request, $id)
    {
        try {
            $request->validate([
                'is_active' => 'required|boolean'
            ]);

            DB::beginTransaction();

            $category = CategoryThemas::findOrFail($id);
            $category->update(['is_active' => $request->is_active]);

            // If deactivating category, also deactivate all its themes
            if (!$request->is_active) {
                $category->jenisThemas()->update(['is_active' => false]);
            }

            DB::commit();

            $status = $request->is_active ? 'activated' : 'deactivated';
            
            return response()->json([
                'status' => true,
                'message' => "Category {$status} successfully",
                'data' => $category->fresh(['jenisThemas'])
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Category not found.'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Category activation toggle failed', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to update category status.'
            ], 500);
        }
    }

    /**
     * Remove the specified category
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $category = CategoryThemas::findOrFail($id);
            
            // Check if category has themes
            if ($category->jenisThemas()->count() > 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot delete category that has themes. Please delete themes first.'
                ], 400);
            }

            $category->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Category deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Category not found.'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Category deletion failed', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete category.'
            ], 500);
        }
    }

    /**
     * Bulk update category sort orders
     */
    public function updateSortOrder(Request $request)
    {
        try {
            $request->validate([
                'categories' => 'required|array',
                'categories.*.id' => 'required|integer|exists:category_themas,id',
                'categories.*.sort_order' => 'required|integer|min:0'
            ]);

            DB::beginTransaction();

            foreach ($request->categories as $categoryData) {
                CategoryThemas::where('id', $categoryData['id'])
                    ->update(['sort_order' => $categoryData['sort_order']]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Sort order updated successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sort order update failed', [
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
     * Bulk toggle activation for multiple categories
     */
    public function bulkToggleActivation(Request $request)
    {
        try {
            $request->validate([
                'category_ids' => 'required|array',
                'category_ids.*' => 'integer|exists:category_themas,id',
                'is_active' => 'required|boolean'
            ]);

            DB::beginTransaction();

            $updated = CategoryThemas::whereIn('id', $request->category_ids)
                ->update(['is_active' => $request->is_active]);

            // If deactivating categories, also deactivate all their themes
            if (!$request->is_active) {
                JenisThemas::whereIn('category_id', $request->category_ids)
                    ->update(['is_active' => false]);
            }

            DB::commit();

            $status = $request->is_active ? 'activated' : 'deactivated';
            
            return response()->json([
                'status' => true,
                'message' => "{$updated} categories {$status} successfully",
                'data' => [
                    'updated_count' => $updated,
                    'category_ids' => $request->category_ids
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk category activation failed', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to update categories status.'
            ], 500);
        }
    }

    /**
     * Get categories statistics
     */
    public function statistics()
    {
        try {
            $stats = [
                'total_categories' => CategoryThemas::count(),
                'active_categories' => CategoryThemas::where('is_active', true)->count(),
                'inactive_categories' => CategoryThemas::where('is_active', false)->count(),
                'website_categories' => CategoryThemas::where('type', 'website')->count(),
                'video_categories' => CategoryThemas::where('type', 'video')->count(),
                'categories_with_themes' => CategoryThemas::has('jenisThemas')->count(),
                'empty_categories' => CategoryThemas::doesntHave('jenisThemas')->count(),
            ];

            return response()->json([
                'status' => true,
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            Log::error('Category statistics failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve statistics.'
            ], 500);
        }
    }
}