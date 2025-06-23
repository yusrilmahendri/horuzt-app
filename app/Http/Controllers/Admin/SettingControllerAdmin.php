<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagihanTransaction\TagihanTransactionCollection;
use App\Models\MetodeTransaction;
use App\Models\MidtransTransaction;
use App\Models\PaketUndangan;
use App\Models\TransactionTagihan;
use App\Models\TripayTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingControllerAdmin extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function masterTagihan()
    {
        $user = Auth::user();

        $query = MetodeTransaction::query();

        if ($user->hasRole('admin')) {

            $query->where('name', '!=', 'Trial');
        } else {

            $query->where('name', '!=', 'Tripay');
        }

        $data = $query->get();

        return new TagihanTransactionCollection($data);
    }

    public function storeMethodTransaction(Request $request)
    {

        $request->validate([
            'metodeTransactions_id' => 'required|exists:metode_transactions,id',
        ]);


        $transaction = TransactionTagihan::create([
            'user_id'               => Auth::id(),
            'metodeTransactions_id' => $request->metodeTransactions_id,
        ]);


        return response()->json([
            'message' => 'Metode transaksi berhasil dibuat!',
            'data'    => $transaction,
        ], 201);
    }

    public function storeMidtrans(Request $request)
    {


        $request->validate([
            'url'                    => 'required|url',
            'server_key'             => 'required|string',
            'client_key'             => 'required|string',
            'metode_production'      => 'required|string',
            'methode_pembayaran'    => 'required|string',
            'id_methode_pembayaran' => 'required|string',
        ]);


        $midtrans = MidtransTransaction::create([
            'user_id'                => Auth::id(),
            'method_transaction'     => $request->metodeTransactions_id,
            'url'                    => $request->url,
            'server_key'             => $request->server_key,
            'client_key'             => $request->client_key,
            'metode_production'      => $request->metode_production,
            'methode_pembayaran'    => $request->methode_pembayaran,
            'id_methode_pembayaran' => $request->id_methode_pembayaran,
        ]);

        if ($midtrans) {
            return response()->json([
                'message' => 'Setting Pembayaran Midtrans berhasil disimpan',
                'data'    => $midtrans,
            ], 201);
        } else {
            return response()->json([
                'message' => 'Setting Pembayaran Midtrans tidak berhasil disimpan',
                'data'    => $midtrans,
            ], 500);
        }
    }

    public function storeTripay(Request $request)
    {


        $request->validate([
            'url_tripay'             => 'required|url',
            'private_key'            => 'required|string',
            'api_key'                => 'required|string',
            'kode_merchant'          => 'required|string',
            'methode_pembayaran'    => 'required|string',
            'id_methode_pembayaran' => 'required|string',
        ]);


        $midtrans = TripayTransaction::create([
            'user_id'                => Auth::id(),
            'method_transaction'     => $request->metodeTransactions_id,
            'url_tripay'             => $request->url_tripay,
            'private_key'            => $request->private_key,
            'api_key'                => $request->api_key,
            'kode_merchant'          => $request->kode_merchant,
            'methode_pembayaran'    => $request->methode_pembayaran,
            'id_methode_pembayaran' => $request->id_methode_pembayaran,
        ]);

        if ($midtrans) {
            return response()->json([
                'message' => 'Setting Pembayaran Tripay berhasil disimpan',
                'data'    => $midtrans,
            ], 201);
        } else {
            return response()->json([
                'message' => 'Setting Pembayaran Tripay tidak berhasil disimpan',
                'data'    => $midtrans,
            ], 500);
        }
    }

    public function indexPaket()
    {
        $pakets = PaketUndangan::all();
        return response()->json([
            'message' => 'Data paket undangan yang tersedia saat ini.!',
            'data'    => $pakets,
        ], 200);
    }

    public function updatePaket(Request $request, $id)
    {


        $paket = PaketUndangan::find($id);


        if (! $paket) {
            return response()->json([
                'message' => 'Paket tidak ditemukan',
            ], 404);
        }


        $request->validate([
            'name_paket'       => 'required|string',
            'price'            => 'required|numeric',
            'masa_aktif'       => 'required|integer',
            'halaman_buku'     => 'boolean',
            'kirim_wa'         => 'boolean',
            'bebas_pilih_tema' => 'boolean',
            'kirim_hadiah'     => 'boolean',
            'import_data'      => 'boolean',
        ]);


        $paket->update([
            'name_paket'       => $request->name_paket,
            'price'            => $request->price,
            'masa_aktif'       => $request->masa_aktif,
            'halaman_buku'     => $request->halaman_buku,
            'kirim_wa'         => $request->kirim_wa,
            'bebas_pilih_tema' => $request->bebas_pilih_tema,
            'kirim_hadiah'     => $request->kirim_hadiah,
            'import_data'      => $request->import_data,
        ]);

        return response()->json([
            'message' => 'Paket berhasil diperbarui',
            'data'    => $paket,
        ], 200);
    }

}
