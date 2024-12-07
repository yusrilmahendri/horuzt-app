<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Bank;
use App\Models\Rekening;
use Illuminate\Support\Facades\Auth;

class RekeningController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }  

    public function store(Request $request)
    {
        // Validasi input rekening
        $rekeningRequest = $request->validate([
            'kode_bank' => 'required',
            'nomor_rekening' => 'required|min:3',  
            'nama_pemilik' => 'required|min:3',     
            'photo_rek' => 'required|image' // Ensure the photo is an image
        ]);
    
        $userId = Auth::id();
    
        // Membuat instance Rekening baru
        $rekening = new Rekening();
        $rekening->user_id = $userId;
        $rekening->nomor_rekening = $rekeningRequest['nomor_rekening'];  // Assign specific field
        $rekening->nama_pemilik = $rekeningRequest['nama_pemilik'];        // Assign specific field
        $rekening->kode_bank = $rekeningRequest['kode_bank'];              // Assign specific field
    
        // Menyimpan photo_rek ke folder yang sesuai
        $photoPath = $request->file('photo_rek')->store('photos', 'public');
        $rekening->photo_rek = $photoPath;
    
        // Menyimpan data rekening ke database
        $rekening->save();
    
        // Mengembalikan respons jika berhasil
        return response()->json([
           'data' => [
            'kode_bank' => $rekeningRequest['kode_bank'],
            'nomor_rekening' => $rekeningRequest['nomor_rekening'],
            'nama_pemilik' => $rekeningRequest['nama_pemilik'],
            'photo_rek' => $rekening->photo_rek // Return the correct file path here
           ],
            'message' => 'Rekening berhasil ditambahkan!',
        ], 201);
    }    
}
