<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MetodeTransaction;
use App\Http\Resources\TagihanTransaction\TagihanTransactionCollection;
use App\Models\MidtransTransaction;
use Illuminate\Support\Facades\Auth;
use App\Models\PaketUndangan;
use App\Models\TransactionTagihan;

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

    public function storeMethodTransaction(Request $request){
          // Validasi Input
        $request->validate([
            'metodeTransactions_id' => 'required|exists:metode_transactions,id',
        ]);

        // Simpan data ke database
        $transaction = TransactionTagihan::create([
            'user_id' => Auth::id(), // Ambil ID user yang sedang login
            'metodeTransactions_id' => $request->metodeTransactions_id,
        ]);

        // Return response sukses
        return response()->json([
            'message' => 'Metode transaksi berhasil dibuat!',
            'data' => $transaction
        ], 201);
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

    public function indexPaket(){
        $pakets = PaketUndangan::all(); // Ambil semua data paket undangan
        return response()->json([
            'message' => 'Data paket undangan yang tersedia saat ini.!',
            'data' => $pakets
        ], 200);
    }

    public function updatePaket(Request $request, $id)
    {
       
        // Cari paket berdasarkan ID
        $paket = PaketUndangan::find($id);

        // Jika tidak ditemukan, kembalikan response 404
        if (!$paket) {
            return response()->json([
                'message' => 'Paket tidak ditemukan'
            ], 404);
        }

        // Validasi input
        $request->validate([
            'name_paket' => 'required|string',
            'price' => 'required|numeric',
            'masa_aktif' => 'required|integer',
            'halaman_buku' => 'boolean',
            'kirim_wa' => 'boolean',
            'bebas_pilih_tema' => 'boolean',
            'kirim_hadiah' => 'boolean',
            'import_data' => 'boolean',
        ]);

        // Update data
        $paket->update([
            'name_paket' => $request->name_paket,
            'price' => $request->price,
            'masa_aktif' => $request->masa_aktif,
            'halaman_buku' => $request->halaman_buku,
            'kirim_wa' => $request->kirim_wa,
            'bebas_pilih_tema' => $request->bebas_pilih_tema,
            'kirim_hadiah' => $request->kirim_hadiah,
            'import_data' => $request->import_data,
        ]);

        return response()->json([
            'message' => 'Paket berhasil diperbarui',
            'data' => $paket
        ], 200);
    }

}
