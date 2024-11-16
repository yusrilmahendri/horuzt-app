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
        $data = BukuTamu::paginate(5);
        return new BukuTamuCollection($data);
    }

    public function deleteAll()
    {
        BukuTamu::truncate();
        return response()->json(['message' => 'Semua data telah dihapus.'], 200);
    }

    public function deleteById($id)
    {
        $bukuTamu = BukuTamu::find($id);
        if ($bukuTamu) {
            $bukuTamu->delete();
            return response()->json(['message' => 'Data berhasil dihapus.'], 200);
        } else {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        }
    }

}
