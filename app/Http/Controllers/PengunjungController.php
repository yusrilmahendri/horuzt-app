<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BukuTamu;
use App\Http\Resources\Bukutamu\PengunjungCollection;


class PengunjungController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }  

    public function index(Request $request)
    {
        $user = auth()->user();

        // Start with the query for the authenticated user
        $query = BukuTamu::where('user_id', $user->id);

        // Apply search filter if the 'search' query parameter exists
        if ($request->has('search') && $request->search) {
            $query->where('nama', 'like', '%' . $request->search . '%');
        }

        // Get the limit from query parameters or default to 10
        $limit = $request->get('limit', 10); // Default limit is 10

        // Paginate the results
        $data = $query->paginate($limit);

        return new PengunjungCollection($data);
    }

    public function deleteAll()
    {   
        $user = auth()->user();
        BukuTamu::where('user_id', $user->id)->delete();
        return response()->json(['message' => 'Semua data telah dihapus.'], 200);
    }

    public function deleteById($id)
    {
        $user = auth()->user();
        $bukuTamu = BukuTamu::find($id);
    
        if ($bukuTamu) {
            // Check if the record belongs to the logged-in user
            if ($bukuTamu->user_id === $user->id) {
                $bukuTamu->delete();
                return response()->json(['message' => 'Data berhasil dihapus.'], 200);
            } else {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk menghapus data ini.'], 403);
            }
        } else {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        }
    }
}
