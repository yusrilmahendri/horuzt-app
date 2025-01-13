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

        // Check if the authenticated user is an admin
        if (auth()->user()->role === 'admin') {
            $query->whereHas('user', function ($q) {
                $q->where('role', 'user');
            });
        }

        // Apply search filter if provided
        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('provinsi', 'LIKE', "%{$searchTerm}%")
                ->orWhere('ulasan', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Set default limit to 10 if not provided
        $limit = $request->has('limit') && is_numeric($request->limit) 
            ? $request->limit 
            : 10;

        // Paginate the results with the calculated limit
        $data = $query->paginate($limit);

        // Return the paginated and filtered results
        return new TestimoniCollection($data);
    }


    public function store(Request $request){
        $validate = $this->validate($request, [
            'kota' => 'required|min:3',
            'provinsi' => 'required|min:3',
            'ulasan' => 'required|min:3',
        ]);
    
        // Add the authenticated user's ID to the validated data
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
        // Validate the 'status' field
        $validatedData = $request->validate([
            'status' => 'required|boolean', // Ensure 'status' is required and a boolean (1 or 0)
        ]);

        // Find the Testimoni by ID
        $testimoni = Testimoni::find($id);

        // Check if the Testimoni exists
        if (!$testimoni) {
            return response()->json([
                'message' => 'Data tidak ditemukan.',
            ], 404);
        }

        // Update the 'status' field
        $testimoni->status = $validatedData['status'];
        $testimoni->save();

        // Return success response
        return response()->json([
            'message' => 'Status berhasil diperbarui.',
            'testimoni' => $testimoni,
        ], 200);
    }


    public function deleteAll()
    {
        // Check if there are any records to delete
        $testimoniesCount = Testimoni::count();
        if ($testimoniesCount > 0) {
            // Delete all records
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
