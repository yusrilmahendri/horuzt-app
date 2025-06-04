<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\Testimoni\TestimoniCollection;
use Illuminate\Support\Facades\Auth;
use App\Models\Testimoni;

class TestimoniController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $query = Testimoni::query();


        if (auth()->user()->role === 'admin') {
            $query->whereHas('user', function ($q) {
                $q->where('role', 'user');
            });
        }


        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('provinsi', 'LIKE', "%{$searchTerm}%")
                ->orWhere('ulasan', 'LIKE', "%{$searchTerm}%");
            });
        }


        $limit = $request->has('limit') && is_numeric($request->limit)
            ? $request->limit
            : 10;


        $data = $query->paginate($limit);


        return new TestimoniCollection($data);
    }


    public function store(Request $request){
        $validate = $this->validate($request, [
            'kota' => 'required|min:3',
            'provinsi' => 'required|min:3',
            'ulasan' => 'required|min:3',
        ]);


        $validate['user_id'] = Auth::id();

        $testimoni = new Testimoni($validate);
        if($testimoni->save()){
            return response()->json([
                'Message' => 'Terimakasih anda sudah mengisi ulasannya',
                'testimoni' => $testimoni
            ], 200);
        }else{
            return response()->json([
                'Message' => 'Ulasan anda gagal dikirimkan!',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {

        $validatedData = $request->validate([
            'status' => 'required|boolean',
        ]);


        $testimoni = Testimoni::find($id);


        if (!$testimoni) {
            return response()->json([
                'message' => 'Data tidak ditemukan.',
            ], 404);
        }


        $testimoni->status = $validatedData['status'];
        $testimoni->save();


        return response()->json([
            'message' => 'Status berhasil diperbarui.',
            'testimoni' => $testimoni,
        ], 200);
    }


    public function deleteAll()
    {

        $testimoniesCount = Testimoni::count();
        if ($testimoniesCount > 0) {

            Testimoni::truncate();

            return response()->json([
                'message' => 'Semua data berhasil dihapus.',
            ], 200);
        }

        return response()->json([
            'message' => 'Tidak ada data untuk dihapus.',
        ], 404);
    }


    public function deleteById($id)
    {
        $testimoni = Testimoni::find($id);
        if ($testimoni) {
            $testimoni->delete();

            return response()->json([
                'message' => 'Data berhasil dihapus.',
            ], 200);
        }

        return response()->json([
            'message' => 'Data tidak ditemukan.',
        ], 404);
    }
}
