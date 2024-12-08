<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cerita;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CeritaController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }  

    public function store(Request $request)
    {
        // Validasi input awal
        $title = $request->input('title', []);
        $leadCerita = $request->input('lead_cerita', []);
        $tglCerita = $request->input('tanggal_cerita', []);
    
        $count = count($title);
    
        // Validasi jumlah elemen pada input
        if (count($leadCerita) !== $count || count($tglCerita) !== $count) {
            return response()->json([
                'message' => 'Mismatch in the lead cerita data! All fields must have the same number of entries.',
            ], 400);
        }
    
        $userId = Auth::id();
        $savedCerita = [];
    
        for ($i = 0; $i < $count; $i++) {
            // Validasi per elemen untuk memastikan semua field terisi
            if (empty($title[$i]) || empty($leadCerita[$i]) || empty($tglCerita[$i])) {
                return response()->json([
                    'message' => 'Some required fields are missing for index ' . $i,
                ], 400);
            }
    
            // Buat entitas baru dan isi data
            $cerita = new Cerita();
            $cerita->user_id = $userId;
            $cerita->title = $title[$i];
            $cerita->lead_cerita = $leadCerita[$i];
            $cerita->tanggal_cerita = $tglCerita[$i];
            $cerita->save();
    
            // Tambahkan ke array untuk response
            $savedCerita[] = [
                'title' => $cerita->title,
                'lead_cerita' => $cerita->lead_cerita,
                'tanggal_cerita' => $cerita->tanggal_cerita,
            ];
        }
    
        // Response sukses
        return response()->json([
            'data' => $savedCerita,
            'user_id' => $userId,
            'message' => 'Cerita have been successfully added!',
        ]);
    }
    
    
}
