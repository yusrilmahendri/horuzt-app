<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ucapan;
use App\Http\Resources\Bukutamu\UcapanCollection;

class UcapanController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }  

    public function index(Request $request)
    {
        $user = auth()->user();

        // Start with the query for the authenticated user
        $query = Ucapan::where('user_id', $user->id);

        // Apply search filter if the 'search' query parameter exists
        if ($request->has('search') && $request->search) {
            $query->where('nama', 'like', '%' . $request->search . '%');
        }

        // Get the limit from query parameters or default to 10
        $limit = $request->get('limit', 10); // Default limit is 10

        // Paginate the results
        $data = $query->paginate($limit);

        return new UcapanCollection($data);
    }
    

    public function deleteAll()
    {   
        $user = auth()->user();
        Ucapan::where('user_id', $user->id)->delete();
        return response()->json(['message' => 'Semua data telah dihapus.'], 200);
    }

    public function deleteById($id)
    {   
        $user = auth()->user();
        $ucapan = Ucapan::find($id);
    
        if ($ucapan) {
            // Check if the record belongs to the logged-in user
            if ($ucapan->user_id === $user->id) {
                $ucapan->delete();
                return response()->json(['message' => 'Data berhasil dihapus.'], 200);
            } else {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk menghapus data ini.'], 403);
            }
        } else {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        }
    }
}
