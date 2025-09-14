<?php
namespace App\Http\Controllers;

use App\Http\Resources\CategoryThemas\CategoryCollection;
use App\Models\CategoryThemas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index()
    {
        $data = CategoryThemas::select('id', 'name', 'slug')->get();
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    $exists = CategoryThemas::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($value))])->exists();
                    if ($exists) {
                        $fail('The ' . $attribute . ' has already been taken.');
                    }
                },
            ],
            'slug' => 'required|string|max:255|unique:category_themas,slug',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = CategoryThemas::create([
            'name' => $request->name,
            'slug' => $request->slug,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Category created successfully',
            'data'    => $category,
        ], 201);
    }

    public function update(Request $request)
    {
        $id = $request->input('id');

        if (! $id) {
            return response()->json([
                'status'  => false,
                'message' => 'Category ID is required',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'id'   => 'required|integer|exists:category_themas,id',
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($id) {
                    $exists = CategoryThemas::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($value))])
                        ->where('id', '!=', $id)
                        ->exists();
                    if ($exists) {
                        $fail('The ' . $attribute . ' has already been taken.');
                    }
                },
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('category_themas', 'slug')->ignore($id),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $category       = CategoryThemas::find($id);
        $category->name = $request->name;
        $category->slug = $request->slug;
        $category->save();

        return response()->json([
            'status'  => true,
            'message' => 'Category updated successfully',
            'data'    => $category,
        ]);
    }

    public function destroy(Request $request)
    {
        $id = $request->input('id');

        if (! $id) {
            return response()->json([
                'status'  => false,
                'message' => 'Category ID is required',
            ], 400);
        }

        $category = CategoryThemas::find($id);
        if (! $category) {
            return response()->json([
                'status'  => false,
                'message' => 'Category not found',
            ], 404);
        }

        $category->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Category deleted successfully',
        ]);
    }

    public function destroyAll(Request $request)
    {
        $confirm = $request->query('confirm');

        if ($confirm !== 'yes') {
            return response()->json([
                'status'  => false,
                'message' => 'Confirmation required to delete all categories. Add ?confirm=yes to the URL',
            ], 400);
        }

        try {
            // First, delete all related records to avoid foreign key constraints
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            CategoryThemas::query()->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            return response()->json([
                'status'  => true,
                'message' => 'All categories deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to delete all categories: ' . $e->getMessage(),
            ], 500);
        }
    }

}
