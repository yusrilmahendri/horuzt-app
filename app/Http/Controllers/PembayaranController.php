<?php
namespace App\Http\Controllers;

use App\Http\Resources\Pembayaran\PembayaranCollection;
use App\Http\Resources\User\UserResource;
use App\Models\KodePemesanan;
use App\Models\MidtransTransaction;
use App\Models\Pembayaran;
use App\Models\Rekening;
use App\Models\TransaksiRekening;
use Illuminate\Http\Request;

class PembayaranController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index()
    {
        $data = Pembayaran::paginate(5);
        return new PembayaranCollection($data);
    }

    public function getRekening(Request $request)
    {
        if ($request->metode_transaksi == 'Manual') {
            $rekening = Rekening::all();
            return response()->json($rekening);
        } else if ($request->metode_transaksi == 'Midtrans') {
            $midtrans = MidtransTransaction::all();
            return response()->json($midtrans);
        }
    }

    public function storeIdMethodTransaction(Request $request)
    {

        $transaction = TransaksiRekening::create([
            'id_user'               => $request->id_user,
            'id_rekening'           => $request->id_rekening,
            'id_methode_pembayaran' => $request->id_methode_pembayaran,
        ]);

        return response()->json([
            'message' => 'Metode transaksi berhasil dibuat!',
            'data'    => $transaction,
        ], 201);
    }

    public function getBankDetails(Request $request)
    {
        $transactions = TransaksiRekening::where('id_user', $request->id_user)
            ->where('id_rekening', $request->id_rekening)
            ->distinct()
            ->get(['id_rekening']);

        if ($transactions->isEmpty()) {
            return response()->json([
                'message' => 'Rekening tidak ditemukan!',
            ], 404);
        }
        $rekenings = Rekening::whereIn('id', $transactions->pluck('id_rekening'))->get();

        return response()->json($rekenings);
    }


    public function storeKodePesanan(Request $request)
    {

        $user = auth()->user();

        if ($user->kode_pemesanan !== $request->kode_pemesanan) {
            return response()->json([
            'message' => 'Kode pemesanan tidak valid untuk user ini.',
            ], 400);
        } else {
            $existingKodePemesanan = KodePemesanan::where('kode_pemesanan', $request->kode_pemesanan)->first();
            if ($existingKodePemesanan) {
            return response()->json([
                'message' => 'Kode pemesanan sudah ada.',
            ], 400);
            }
        }

        $kodePemesanan = KodePemesanan::create([
            'id_user'        => $request->id_user,
            'nama'           => $request->nama,
            'kode_pemesanan' => $request->kode_pemesanan,
            'keterangan'     => $request->keterangan,
        ]);

        return response()->json([
            'message' => 'Kode pemesanan berhasil dibuat!',
            'data'    => $kodePemesanan,
        ], 201);
    }

    public function getDashboard()
    {
        $user = auth()->user();
        return new UserResource($user);
    }

}
