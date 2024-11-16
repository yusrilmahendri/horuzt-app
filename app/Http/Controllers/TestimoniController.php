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

    public function index(){
        $data = Testimoni::paginate(5);
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
}
