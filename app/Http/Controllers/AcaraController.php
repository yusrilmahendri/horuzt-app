<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Import Auth facade
use App\Models\Acara;
use App\Models\CountdownAcara;
use App\Http\Resources\Acara\AcaraResource;
use App\Http\Resources\Acara\AcaraCollection;

class AcaraController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(){
        $userId = Auth::id();
        
        $acaras = Acara::with('countdown')->where('user_id', $userId)->get();
        return new AcaraCollection(AcaraResource::collection($acaras));
    }


    public function storeCountDown(Request $request){
        $validateData = $this->validate($request, [
            'name_countdown' => 'required'
        ]);

        $userId = Auth::id();        
        $countDown = new CountdownAcara();
        $countDown->user_id = $userId; 
        $countDown->name_countdown = $validateData['name_countdown'];
        $countDown->save();

        if($countDown){
            return response()->json([
                'name_countdown' => $countDown,
                'message' => 'countdown have been successfully added!'
            ]);
        } else{
             return response()->json([
                    'message' => 'countdown have been failed added!',
            ], 400);
        }
    }


    public function store(Request $request)
    {
        
        $nameAcara = $request->input('nama_acara', []);
        $tglAcara = $request->input('tanggal_acara', []);
        $startAcara = $request->input('start_acara', []);
        $endAcara = $request->input('end_acara', []);
        $alamatAcara = $request->input('alamat', []);
        $linkAcara = $request->input('link_maps', []);

        $count = count($nameAcara);
        $userId = Auth::id(); // Get the authenticated user ID
        $savedAcara = [];
        $countDown = CountdownAcara::where('user_id', $userId)->latest('created_at')->first(); // Get the first record
        
        if (!$countDown) {
        return response()->json([
            'message' => 'No countdown is associated with the user. Please create a countdown first.',
        ], 400);
        }

        for ($i = 0; $i < $count; $i++) {
            if (
                empty($nameAcara[$i]) || empty($tglAcara[$i]) ||
                empty($startAcara[$i]) || empty($endAcara[$i]) ||
                empty($alamatAcara[$i]) || empty($linkAcara[$i])
            ) {
                return response()->json([
                    'message' => 'Some required fields are missing for index ' . $i,
                ], 400);
            }

            $acara = new Acara();
            $acara->countdown_id = $countDown->id;
            $acara->nama_acara = $nameAcara[$i];
            $acara->tanggal_acara = $tglAcara[$i];
            $acara->start_acara = $startAcara[$i];
            $acara->end_acara = $endAcara[$i];
            $acara->alamat = $alamatAcara[$i];
            $acara->link_maps = $linkAcara[$i];
            $acara->user_id = $userId; 
            $acara->save();

            $savedAcara[] = [
                'nama_acara' => $acara->nama_acara,
                'tanggal_acara' => $acara->tanggal_acara,
                'start_acara' => $acara->start_acara,
                'end_acara' => $acara->end_acara,
                'alamat' => $acara->alamat,
                'link_maps' => $acara->link_maps,
                'countdown_id' => $acara->countdown_id,
            ];
        }

        return response()->json([
            'data' => $savedAcara,
            'user_id' => $userId,
            'message' => 'Acara have been successfully added!',
        ], 201);
    }
}
