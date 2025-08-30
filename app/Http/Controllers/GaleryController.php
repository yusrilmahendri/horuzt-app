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
            'photo' => 'required|file|mimes:jpg,png,jpeg|max:2048',
            'url_video' => 'required|url',
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
                'data' => $galery,
            ], 201);
        } else {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan data galery.',
            ], 500);
        }
    }

    /**
     * List galery for user with optional query params: status, user_id, per_page
     */
    public function index(Request $request)
    {
        $query = Galery::query();

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by user_id if provided
        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Pagination (default 10)
        $perPage = $request->input('per_page', 10);
        $galleries = $query->orderByDesc('id')->paginate($perPage);

        return response()->json($galleries);
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
