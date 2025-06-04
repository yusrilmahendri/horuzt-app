<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\Bukutamu\PengunjungCollection;
use App\Models\Ucapan;


class PengunjungController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $user = auth()->user();


        $query = Ucapan::where('user_id', $user->id);


        if ($request->has('search') && $request->search) {
            $query->where('nama', 'like', '%' . $request->search . '%');
        }


        $limit = $request->get('limit', 10);


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
