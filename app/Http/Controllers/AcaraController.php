<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Acara;

class AcaraController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }  

    public function store(Request $request){
        $nameAcara = $request->input('nama_acara', []);
        $tglAcara = $request->input('tanggal_acara', []);
        $startAcara = $request->input('start_acara', []);
        $endAcara = $request->input('end_acara', []);
        $alamatAcara = $request->input('alamat_acara', []);
        $linkAcara = $request->input('link_acara', []);

        $count = count($nameAcara);
        $userId = Auth::id();
        $savedAcara = [];

        for($i = 0; $i < $count; $i++){
            if(empty($nameAcara[$i]) || empty($tglAcara[$i]) || 
                empty($startAcara[$i]) || empty($endAcara[$i]) || 
                empty($alamatAcara[$i]) || empty($linkAcara[$i])){
                    return response()->json([
                        'message' => 'Some required fields are missing for index' . $i,
                    ], 400);
            }

            $acara = new Acara();
            $acara->nama_acara = $nameAcara;
            $acara->tanggal_acara = $tglAcara;
            $acara->start_acara = $startAcara;
            $acara->endAcara = $endAcara;
            $acara->alamat_acara = $alamatAcara;
            $acara->link_acara = $linkAcara;
            $acara->save();

            $savedAcara[] = [
                'nama_acara' => $acara->nama_acara,
                'tanggal_acara' => $acara->tanggal_acara,
                'start_acara' => $acara->start_acara,
                'end_acara' => $acara->end_acara,
                'alamat_acara' => $acara->alamat_acara,
                'link_acara' => $acara->link_acara,
            ];

            return response()->json([
                'data' => $savedAcara,
                'user_id' => $acara->user_id,
                'message' => 'Acara have been successfully added!',
            ], 201);
        }
    }
}
