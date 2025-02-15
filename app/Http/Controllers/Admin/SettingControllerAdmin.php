<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MetodeTransaction;
use App\Http\Resources\TagihanTransaction\TagihanTransactionCollection;
use App\Models\MidtransTransaction;
use Illuminate\Support\Facades\Auth;

class SettingControllerAdmin extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function masterTagihan(){
        $data = MetodeTransaction::get();
        return new TagihanTransactionCollection($data);
    }


    public function storeMidtrans(Request $request){
    
      // Validasi request
        $request->validate([
            'url' => 'required|url',
            'server_key' => 'required|string',
            'client_key' => 'required|string',
            'metode_production' => 'required|string',
        ]);

        // Simpan data ke database dengan user yang sedang login
        $midtrans = MidtransTransaction::create([
            'user_id' => Auth::id(), // Mengambil user yang sedang login
            'url' => $request->url,
            'server_key' => $request->server_key,
            'client_key' => $request->client_key,
            'metode_production' => $request->metode_production,
        ]);
        

        if($midtrans){
            return response()->json([
                'message' => 'Setting Pembayaran Midtrans berhasil disimpan',
                'data' => $midtrans
        ], 201);
        }
        else{
            return response()->json([
                'message' => 'Setting Pembayaran Midtrans tidak berhasil disimpan',
                'data' => $midtrans
                ], 500);
        }
    }
}
