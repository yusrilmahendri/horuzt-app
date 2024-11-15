<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\Testimoni\TestimoniCollection;
<<<<<<< HEAD
=======
use Illuminate\Support\Facades\Auth;
>>>>>>> 067dd6d37f3e90bdb30b98d8da65384f01ce9070
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
<<<<<<< HEAD
=======

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
    
>>>>>>> 067dd6d37f3e90bdb30b98d8da65384f01ce9070
}
