<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Models\Qoute;

class QouteController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }  

    public function store(Request $request)
    {
        // Validasi input untuk memastikan data sesuai
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'qoute' => 'required|string|max:500',
        ]);
    
        // Ambil ID pengguna yang sedang login
        $userId = Auth::id();
    
        // Buat instance baru untuk Qoute
        $qoute = new Qoute();
        $qoute->user_id = $userId;
        $qoute->name = $validatedData['name'];
        $qoute->qoute = $validatedData['qoute'];
    
        // Simpan data ke database
        if ($qoute->save()) {
            return response()->json([
                'message' => 'Qoute berhasil disimpan!',
                'data' => $qoute,
            ], 201); 
        } else {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan qoute.',
            ], 500); 
        }
    }
    
    
    
}
