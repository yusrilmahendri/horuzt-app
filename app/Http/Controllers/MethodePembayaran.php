<?php
namespace App\Http\Controllers;

use App\Models\MetodeTransaction;
use App\Models\MidtransTransaction;
use App\Models\PaketUndangan;
use App\Models\Rekening;
use App\Models\Setting;
use App\Models\TripayTransaction;
use Illuminate\Http\Request;

class MethodePembayaran extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $metodeTransactions = MetodeTransaction::all();
        return response()->json($metodeTransactions);
    }

public function getPaketUndangan(Request $request)
{
    $paketUndangan = PaketUndangan::query();

    if ($request->has('id')) {
        $paketUndangan->where('id', $request->id);
    }

    $result = $paketUndangan->get();

    if ($result->isEmpty()) {
        return response()->json([
            'message' => 'Paket Undangan tidak ditemukan',
            'data' => []
        ], 404);
    }

    return response()->json([
        'message' => 'Data Paket Undangan berhasil diambil',
        'data' => $result
    ], 200);
}


    public function getAllMethodeTransactions(Request $request)
    {

        $rekeningQuery = Rekening::query();

        $midtransQuery = MidtransTransaction::query();

        $tripayQuery  = TripayTransaction::query();
        $settingQuery = Setting::query();

        if ($request->has('id_methode_pembayaran')) {
            $rekeningQuery->where('id_methode_pembayaran', $request->id_methode_pembayaran);
            $midtransQuery->where('id_methode_pembayaran', $request->id_methode_pembayaran);
            $tripayQuery->where('id_methode_pembayaran', $request->id_methode_pembayaran);
        }

        if ($request->has('methode_pembayaran')) {
            $rekeningQuery->where('methode_pembayaran', $request->methode_pembayaran);
            $midtransQuery->where('methode_pembayaran', $request->methode_pembayaran);
            $tripayQuery->where('methode_pembayaran', $request->methode_pembayaran);
        }

        $rekeningData = $rekeningQuery->get();
        $midtransData = $midtransQuery->get();
        $tripayData   = $tripayQuery->get();

        $result = $rekeningData->merge($midtransData)->merge($tripayData);

        return response()->json([
            'message' => 'Data metode transaksi berhasil diambil',
            'data'    => $result,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(MetodeTransaction $metodeTransaction)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MetodeTransaction $metodeTransaction)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MetodeTransaction $metodeTransaction)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MetodeTransaction $metodeTransaction)
    {
        //
    }
}
