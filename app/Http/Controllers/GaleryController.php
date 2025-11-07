<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Galery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GaleryController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }

    public function store(Request $request){
        $validateData = $request->validate([
            'photo' => 'required|file|mimes:jpg,png,jpeg|max:5222',
            'url_video' => 'nullable|url',
            'nama_foto' => 'required|string|max:255',
        ]);

        $userId = Auth::id();
        $photoPath = $request->file('photo')->store('photos', 'public');

        $galery = new Galery();
        $galery->photo = $photoPath;
        $galery->url_video = $validateData['url_video'];
        $galery->nama_foto = $validateData['nama_foto'];
        $galery->user_id = $userId;
        $galery->status = 1;

        if ($galery->save()) {
            return response()->json([
                'message' => 'Galery berhasil disimpan!',
                'data' => [
                    'id' => $galery->id,
                    'user_id' => $galery->user_id,
                    'photo' => $galery->photo,
                    'photo_url' => $galery->photo_url,
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
        $userId = Auth::id();
        $query = Galery::where('user_id', $userId);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Pagination (default 10)
        $perPage = $request->input('per_page', 10);
        $galleries = $query->orderByDesc('id')->paginate($perPage);

        // Transform data to ensure photo_url is included
        $galleries->getCollection()->transform(function ($gallery) {
            return [
                'id' => $gallery->id,
                'user_id' => $gallery->user_id,
                'photo' => $gallery->photo,
                'photo_url' => $gallery->photo_url,
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
     * Public endpoint - List galery by user_id for wedding invitation display
     * Query params: user_id (required), status (optional), per_page (optional)
     */
    public function publicIndex(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'status' => 'nullable|in:0,1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $userId = $validated['user_id'];
        $query = Galery::where('user_id', $userId);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Pagination (default 10)
        $perPage = $request->input('per_page', 10);
        $galleries = $query->orderByDesc('id')->paginate($perPage);

        // Transform data to ensure photo_url is included
        $galleries->getCollection()->transform(function ($gallery) {
            return [
                'id' => $gallery->id,
                'user_id' => $gallery->user_id,
                'photo' => $gallery->photo,
                'photo_url' => $gallery->photo_url,
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
        $galery = Galery::find($id);
        if (!$galery) {
            return response()->json([
                'message' => 'Galery tidak ditemukan.'
            ], 404);
        }

        // Hapus file foto dari storage jika ada
        if ($galery->photo && Storage::disk('public')->exists($galery->photo)) {
            Storage::disk('public')->delete($galery->photo);
        }

        $galery->delete();

        return response()->json([
            'message' => 'Galery berhasil dihapus.'
        ]);
    }
}
