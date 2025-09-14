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

    // ===== MIDTRANS MANAGEMENT =====

    /**
     * List all Midtrans configurations for authenticated admin
     * GET /v1/admin/midtrans
     */
    public function indexMidtrans()
    {
        try {
            $midtransConfigs = MidtransTransaction::where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'message' => 'Data konfigurasi Midtrans berhasil diambil',
                'data' => $midtransConfigs,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data konfigurasi Midtrans',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Show specific Midtrans configuration by ID
     * GET /v1/admin/midtrans/{id}
     */
    public function showMidtrans($id)
    {
        try {
            $midtrans = MidtransTransaction::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$midtrans) {
                return response()->json([
                    'message' => 'Konfigurasi Midtrans tidak ditemukan atau tidak memiliki akses',
                ], 404);
            }

            return response()->json([
                'message' => 'Data konfigurasi Midtrans berhasil diambil',
                'data' => $midtrans,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data konfigurasi Midtrans',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update Midtrans configuration by ID
     * PUT /v1/admin/midtrans/{id}
     */
    public function updateMidtrans(Request $request, $id)
    {
        try {
            $midtrans = MidtransTransaction::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$midtrans) {
                return response()->json([
                    'message' => 'Konfigurasi Midtrans tidak ditemukan atau tidak memiliki akses',
                ], 404);
            }

            $request->validate([
                'url'                    => 'required|url',
                'server_key'             => 'required|string',
                'client_key'             => 'required|string',
                'metode_production'      => 'required|string',
                'methode_pembayaran'    => 'required|string',
                'id_methode_pembayaran' => 'required|string',
            ]);

            $midtrans->update([
                'url'                    => $request->url,
                'server_key'             => $request->server_key,
                'client_key'             => $request->client_key,
                'metode_production'      => $request->metode_production,
                'methode_pembayaran'    => $request->methode_pembayaran,
                'id_methode_pembayaran' => $request->id_methode_pembayaran,
            ]);

            return response()->json([
                'message' => 'Konfigurasi Midtrans berhasil diperbarui',
                'data'    => $midtrans->fresh(),
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Data tidak valid',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal memperbarui konfigurasi Midtrans',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Delete Midtrans configuration by ID
     * DELETE /v1/admin/midtrans/{id}
     */
    public function destroyMidtrans($id)
    {
        try {
            $midtrans = MidtransTransaction::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$midtrans) {
                return response()->json([
                    'message' => 'Konfigurasi Midtrans tidak ditemukan atau tidak memiliki akses',
                ], 404);
            }

            $midtrans->delete();

            return response()->json([
                'message' => 'Konfigurasi Midtrans berhasil dihapus',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menghapus konfigurasi Midtrans',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    // ===== TRIPAY MANAGEMENT =====

    /**
     * List all Tripay configurations for authenticated admin
     * GET /v1/admin/tripay
     */
    public function indexTripay()
    {
        try {
            $tripayConfigs = TripayTransaction::where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'message' => 'Data konfigurasi Tripay berhasil diambil',
                'data' => $tripayConfigs,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data konfigurasi Tripay',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Show specific Tripay configuration by ID
     * GET /v1/admin/tripay/{id}
     */
    public function showTripay($id)
    {
        try {
            $tripay = TripayTransaction::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$tripay) {
                return response()->json([
                    'message' => 'Konfigurasi Tripay tidak ditemukan atau tidak memiliki akses',
                ], 404);
            }

            return response()->json([
                'message' => 'Data konfigurasi Tripay berhasil diambil',
                'data' => $tripay,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data konfigurasi Tripay',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update Tripay configuration by ID
     * PUT /v1/admin/tripay/{id}
     */
    public function updateTripay(Request $request, $id)
    {
        try {
            $tripay = TripayTransaction::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$tripay) {
                return response()->json([
                    'message' => 'Konfigurasi Tripay tidak ditemukan atau tidak memiliki akses',
                ], 404);
            }

            $request->validate([
                'url_tripay'             => 'required|url',
                'private_key'            => 'required|string',
                'api_key'                => 'required|string',
                'kode_merchant'          => 'required|string',
                'methode_pembayaran'    => 'required|string',
                'id_methode_pembayaran' => 'required|string',
            ]);

            $tripay->update([
                'url_tripay'             => $request->url_tripay,
                'private_key'            => $request->private_key,
                'api_key'                => $request->api_key,
                'kode_merchant'          => $request->kode_merchant,
                'methode_pembayaran'    => $request->methode_pembayaran,
                'id_methode_pembayaran' => $request->id_methode_pembayaran,
            ]);

            return response()->json([
                'message' => 'Konfigurasi Tripay berhasil diperbarui',
                'data'    => $tripay->fresh(),
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Data tidak valid',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal memperbarui konfigurasi Tripay',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Delete Tripay configuration by ID
     * DELETE /v1/admin/tripay/{id}
     */
    public function destroyTripay($id)
    {
        try {
            $tripay = TripayTransaction::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$tripay) {
                return response()->json([
                    'message' => 'Konfigurasi Tripay tidak ditemukan atau tidak memiliki akses',
                ], 404);
            }

            $tripay->delete();

            return response()->json([
                'message' => 'Konfigurasi Tripay berhasil dihapus',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menghapus konfigurasi Tripay',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

}
