<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BukuTamu;
use App\Http\Resources\Bukutamu\BukuTamuCollection;

class BukuTamuController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }  

    public function index(){
        $data = BukuTamu::get();
        $user = auth()->user();
        $data = BukuTamu::where('user_id', $user->id)->get();
        return new BukuTamuCollection($data);
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
