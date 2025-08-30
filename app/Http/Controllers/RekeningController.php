<?php
namespace App\Http\Controllers;

use App\Http\Resources\Rekening\RekeningCollection;
use App\Http\Resources\Rekening\RekeningResource;
use App\Models\Bank;
use App\Models\MetodeTransaction;
use App\Models\Rekening;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RekeningController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index()
    {
        $userId    = Auth::id();
        $rekenings = Rekening::where('user_id', $userId)->with('bank')->get();

        return new RekeningCollection($rekenings);
    }

    public function store(Request $request)
    {
        try {
            $userId = Auth::id();
            // Cek jumlah rekening user sebelum menambah
            $existingCount = Rekening::where('user_id', $userId)->count();
            $requestedCount = is_array($request->input('kode_bank')) ? count($request->input('kode_bank')) : 0;
            if ($existingCount + $requestedCount > 2) {
                return response()->json([
                    'message' => 'User tidak boleh memiliki lebih dari 2 rekening.',
                ], 422);
            }

            $validated = $request->validate([
                'kode_bank'        => 'required|array',
                'kode_bank.*'      => 'required|string',
                'nomor_rekening'   => 'required|array',
                'nomor_rekening.*' => 'required|string',
                'nama_pemilik'     => 'required|array',
                'nama_pemilik.*'   => 'required|string',
                'photo_rek'        => 'nullable|array',
                'photo_rek.*'      => 'file|mimes:jpeg,png,jpg|max:2048',

            ]);

            $count               = count($validated['kode_bank']);
            $userId              = Auth::id();
            $userEmail           = Auth::user()->email;
            $idMethodePembayaran = MetodeTransaction::pluck('id')->first();
            $methodePembayaran   = MetodeTransaction::pluck('name')->first();
            $savedRekenings      = [];

            for ($i = 0; $i < $count; $i++) {
                $rekening                        = new Rekening();
                $rekening->user_id               = $userId;
                $rekening->email                 = $userEmail;
                $rekening->methode_pembayaran    = $methodePembayaran;
                $rekening->id_methode_pembayaran = $idMethodePembayaran;
                // Ambil nama bank sesuai kode_bank per rekening
                $namaBank = Bank::where('kode_bank', $validated['kode_bank'][$i])->pluck('name')->first();
                $rekening->nama_bank             = $namaBank;
                $rekening->kode_bank             = $validated['kode_bank'][$i];
                $rekening->nomor_rekening        = $validated['nomor_rekening'][$i];
                $rekening->nama_pemilik          = $validated['nama_pemilik'][$i];

                if (
                    isset($validated['photo_rek'][$i]) &&
                    $validated['photo_rek'][$i] &&
                    $validated['photo_rek'][$i]->isValid()
                ) {
                    $photoPath           = $validated['photo_rek'][$i]->store('photos', 'public');
                    $rekening->photo_rek = $photoPath;
                }
                $rekening->save();

                $savedRekenings[] = [
                    'kode_bank'      => $rekening->kode_bank,
                    'nomor_rekening' => $rekening->nomor_rekening,
                    'nama_pemilik'   => $rekening->nama_pemilik,
                    'photo_rek'      => $rekening->photo_rek ? asset('storage/' . $rekening->photo_rek) : null,
                ];
            }

            return response()->json([
                'data'    => $savedRekenings,
                'message' => 'Rekenings have been successfully added!',
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'errors'  => $e->errors(),
                'message' => 'Validation failed!',
            ], 422);
        }
    }

    public function update(Request $request)
    {
        try {

            $validated = $request->validate([
                'rekenings'                  => 'required|array',
                'rekenings.*.id'             => 'required|integer|exists:rekenings,id',
                'rekenings.*.kode_bank'      => 'required|integer',
                'rekenings.*.nomor_rekening' => 'required|string',
                'rekenings.*.nama_pemilik'   => 'required|string',
                'rekenings.*.photo_rek'      => 'nullable|file|mimes:jpeg,png,jpg|max:2048',
            ]);

            $userId           = Auth::id();
            $updatedRekenings = [];

            foreach ($validated['rekenings'] as $index => $data) {
                $rekening = Rekening::where('id', $data['id'])
                    ->where('user_id', $userId)
                    ->first();

                if ($rekening) {
                    $rekening->kode_bank      = $data['kode_bank'];
                    $rekening->nomor_rekening = $data['nomor_rekening'];
                    $rekening->nama_pemilik   = $data['nama_pemilik'];


                    if (isset($data['photo_rek']) && $data['photo_rek']->isValid()) {
                        $photoPath           = $data['photo_rek']->store('photos', 'public');
                        $rekening->photo_rek = $photoPath;
                    }
                    $rekening->save();
                    $updatedRekenings[] = new RekeningResource($rekening);
                } else {

                    return response()->json([
                        'message' => "Rekening ID {$data['id']} at index {$index} not found or does not belong to the user.",
                    ], 404);
                }
            }


            return response()->json([
                'data'    => count($updatedRekenings) == 1
                ? $updatedRekenings[0]
                : $updatedRekenings,
                'message' => 'Rekenings updated successfully!',
            ], 200);

        } catch (ValidationException $e) {

            return response()->json([
                'message' => 'Validation failed. Please check the form inputs.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'An error occurred while updating the records.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $userId = Auth::id();
        $rekening = Rekening::where('id', $id)->where('user_id', $userId)->first();

        if (!$rekening) {
            return response()->json([
                'message' => 'Rekening not found or does not belong to the user.',
            ], 404);
        }

        $rekening->delete();

        return response()->json([
            'message' => 'Rekening deleted successfully.',
        ], 200);
    }
}
