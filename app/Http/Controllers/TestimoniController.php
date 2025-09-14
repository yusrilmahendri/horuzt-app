<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\Testimoni\TestimoniCollection;
use App\Http\Resources\Testimoni\TestimoniResource;
use App\Http\Requests\StoreTestimoniRequest;
use App\Http\Requests\UpdateTestimoniStatusRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\Testimoni;
use Illuminate\Http\JsonResponse;

class TestimoniController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['public']);
    }

    /**
     * Display testimonials for admin with advanced filtering
     * Admin can see all testimonials with search and status filters
     */
    public function index(Request $request): TestimoniCollection
    {
        $query = Testimoni::with('user');

        // Search functionality
        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('provinsi', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('kota', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('ulasan', 'LIKE', "%{$searchTerm}%")
                  ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                      $userQuery->where('name', 'LIKE', "%{$searchTerm}%");
                  });
            });
        }

        // Status filter
        if ($request->has('status') && in_array($request->status, ['0', '1', 'published', 'unpublished'])) {
            $status = in_array($request->status, ['1', 'published']) ? 1 : 0;
            $query->where('status', $status);
        }

        // Sort by latest
        $query->latest();

        $limit = $request->has('limit') && is_numeric($request->limit)
            ? min((int)$request->limit, 100)
            : 10;

        $data = $query->paginate($limit);

        return new TestimoniCollection($data);
    }

    /**
     * Store a new testimonial (User endpoint)
     */
    public function store(StoreTestimoniRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['user_id'] = Auth::id();
        $validated['status'] = false; // Default to unpublished for moderation

        $testimoni = Testimoni::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Terima kasih! Ulasan Anda telah berhasil dikirim dan akan ditinjau oleh admin.',
            'data' => new TestimoniResource($testimoni->load('user'))
        ], 201);
    }

    /**
     * Update testimonial status (Admin only)
     */
    public function updateStatus(UpdateTestimoniStatusRequest $request, int $id): JsonResponse
    {
        $testimoni = Testimoni::find($id);

        if (!$testimoni) {
            return response()->json([
                'success' => false,
                'message' => 'Testimoni tidak ditemukan.'
            ], 404);
        }

        $testimoni->status = $request->validated()['status'];
        $testimoni->save();

        $statusText = $testimoni->status ? 'dipublikasikan' : 'disembunyikan';

        return response()->json([
            'success' => true,
            'message' => "Status testimoni berhasil diperbarui menjadi {$statusText}.",
            'data' => new TestimoniResource($testimoni->load('user'))
        ]);
    }

    /**
     * Delete all testimonials (Admin only)
     */
    public function deleteAll(): JsonResponse
    {
        $count = Testimoni::count();

        if ($count === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada testimoni untuk dihapus.'
            ], 404);
        }

        Testimoni::truncate();

        return response()->json([
            'success' => true,
            'message' => "Berhasil menghapus semua {$count} testimoni."
        ]);
    }

    /**
     * Delete specific testimonial (Admin only)
     */
    public function deleteById(int $id): JsonResponse
    {
        $testimoni = Testimoni::find($id);

        if (!$testimoni) {
            return response()->json([
                'success' => false,
                'message' => 'Testimoni tidak ditemukan.'
            ], 404);
        }

        $testimoni->delete();

        return response()->json([
            'success' => true,
            'message' => 'Testimoni berhasil dihapus.'
        ]);
    }

    /**
     * Bulk update testimonial status (Admin only)
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:testimonis,id',
            'status' => 'required|boolean'
        ]);

        $updated = Testimoni::whereIn('id', $request->ids)
            ->update(['status' => $request->status]);

        $statusText = $request->status ? 'dipublikasikan' : 'disembunyikan';

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengubah status {$updated} testimoni menjadi {$statusText}."
        ]);
    }

    /**
     * Get published testimonials for public display (Landing page)
     * No authentication required
     */
    public function public(Request $request): JsonResponse
    {
        $query = Testimoni::with('user')
            ->where('status', 1)
            ->latest();

        // Optional search for public testimonials
        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('provinsi', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('kota', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('ulasan', 'LIKE', "%{$searchTerm}%");
            });
        }

        $limit = $request->has('limit') && is_numeric($request->limit)
            ? min((int)$request->limit, 50)
            : 10;

        $testimonials = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'message' => 'Testimoni berhasil diambil.',
            'data' => TestimoniResource::collection($testimonials),
            'meta' => [
                'current_page' => $testimonials->currentPage(),
                'last_page' => $testimonials->lastPage(),
                'per_page' => $testimonials->perPage(),
                'total' => $testimonials->total(),
            ]
        ]);
    }

    /**
     * Get testimonials statistics (Admin only)
     */
    public function statistics(): JsonResponse
    {
        $total = Testimoni::count();
        $published = Testimoni::where('status', 1)->count();
        $unpublished = Testimoni::where('status', 0)->count();
        $recent = Testimoni::where('created_at', '>=', now()->subDays(7))->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_testimonials' => $total,
                'published' => $published,
                'unpublished' => $unpublished,
                'recent_week' => $recent,
                'publication_rate' => $total > 0 ? round(($published / $total) * 100, 2) : 0
            ]
        ]);
    }
}
