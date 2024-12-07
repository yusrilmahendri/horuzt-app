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
        // Ensure each input is treated as an array
        $kodeBank = $request->input('kode_bank', []);
        $nomorRekening = $request->input('nomor_rekening', []);
        $namaPemilik = $request->input('nama_pemilik', []);
        $photoRek = $request->file('photo_rek', []);
    
        // Check if all arrays have the same length
        $count = count($kodeBank);
        if (count($nomorRekening) !== $count || count($namaPemilik) !== $count || count($photoRek) !== $count) {
            return response()->json([
                'message' => 'Mismatch in the number of bank accounts data.',
            ], 400);
        }
    
        $userId = Auth::id();
        $savedRekenings = [];
    
        // Loop through the data and save each bank account
        for ($i = 0; $i < $count; $i++) {
            if (empty($kodeBank[$i]) || empty($nomorRekening[$i]) || empty($namaPemilik[$i]) || !isset($photoRek[$i])) {
                return response()->json([
                    'message' => 'Some required fields are missing for index ' . $i,
                ], 400);
            }
    
            // Create and save a new Rekening instance
            $rekening = new Rekening();
            $rekening->user_id = $userId;
            $rekening->kode_bank = $kodeBank[$i];
            $rekening->nomor_rekening = $nomorRekening[$i];
            $rekening->nama_pemilik = $namaPemilik[$i];
    
            // Handle the file upload
            if ($photoRek[$i]->isValid()) {
                // Store the image and get its path
                $photoPath = $photoRek[$i]->store('photos', 'public');
                $rekening->photo_rek = $photoPath;
            } else {
                return response()->json([
                    'message' => 'Invalid photo_rek file for index ' . $i,
                ], 400);
            }
    
            // Save the Rekening to the database
            $rekening->save();
    
            // Add saved rekening to the response array
            $savedRekenings[] = [
                'kode_bank' => $rekening->kode_bank,
                'nomor_rekening' => $rekening->nomor_rekening,
                'nama_pemilik' => $rekening->nama_pemilik,
                'photo_rek' => $rekening->photo_rek,
            ];
        }
    
        // Return the saved bank account data as response
        return response()->json([
            'data' => $savedRekenings,
            'user_id' => $rekening->user_id,
            'message' => 'Rekenings have been successfully added!',
        ], 201);
    }
     
}
