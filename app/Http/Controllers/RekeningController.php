<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Bank;
use App\Models\Rekening;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\Rekening\RekeningCollection;
use App\Http\Resources\Rekening\RekeningResource;
use Illuminate\Validation\ValidationException;

class RekeningController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }  

    public function index(){
        $userId = Auth::id();
        $rekenings = Rekening::where('user_id', $userId)->with('bank')->get();

        return new RekeningCollection($rekenings);
    }

 
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'kode_bank' => 'required|array',
                'kode_bank.*' => 'required|integer',
                'nomor_rekening' => 'required|array',
                'nomor_rekening.*' => 'required|string',
                'nama_pemilik' => 'required|array',
                'nama_pemilik.*' => 'required|string',
                'photo_rek' => 'required|array',
                'photo_rek.*' => 'required|file|mimes:jpeg,png,jpg|max:2048',
            ]);
    
            $count = count($validated['kode_bank']);
            $userId = Auth::id();
            $savedRekenings = [];
    
            for ($i = 0; $i < $count; $i++) {
                $rekening = new Rekening();
                $rekening->user_id = $userId;
                $rekening->kode_bank = $validated['kode_bank'][$i];
                $rekening->nomor_rekening = $validated['nomor_rekening'][$i];
                $rekening->nama_pemilik = $validated['nama_pemilik'][$i];
    
                if ($validated['photo_rek'][$i]->isValid()) {
                    $photoPath = $validated['photo_rek'][$i]->store('photos', 'public');
                    $rekening->photo_rek = $photoPath;
                }
    
                $rekening->save();
    
                $savedRekenings[] = [
                    'kode_bank' => $rekening->kode_bank,
                    'nomor_rekening' => $rekening->nomor_rekening,
                    'nama_pemilik' => $rekening->nama_pemilik,
                    'photo_rek' => asset('storage/' . $rekening->photo_rek),
                ];
            }
    
            return response()->json([
                'data' => $savedRekenings,
                'message' => 'Rekenings have been successfully added!',
            ], 201);
    
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed!',
            ], 422);
        }
    }
    
    
    public function update(Request $request)
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'rekenings' => 'required|array',
                'rekenings.*.id' => 'required|integer|exists:rekenings,id',
                'rekenings.*.kode_bank' => 'required|integer',
                'rekenings.*.nomor_rekening' => 'required|string',
                'rekenings.*.nama_pemilik' => 'required|string',
                'rekenings.*.photo_rek' => 'nullable|file|mimes:jpeg,png,jpg|max:2048',
            ]);
    
            $userId = Auth::id();
            $updatedRekenings = [];
    
            foreach ($validated['rekenings'] as $index => $data) {
                $rekening = Rekening::where('id', $data['id'])
                    ->where('user_id', $userId)
                    ->first();
    
                if ($rekening) {
                    $rekening->kode_bank = $data['kode_bank'];
                    $rekening->nomor_rekening = $data['nomor_rekening'];
                    $rekening->nama_pemilik = $data['nama_pemilik'];
    
                    // Handle file upload
                    if (isset($data['photo_rek']) && $data['photo_rek']->isValid()) {
                        $photoPath = $data['photo_rek']->store('photos', 'public');
                        $rekening->photo_rek = $photoPath;
                    }
    
                    $rekening->save();
                    $updatedRekenings[] = new RekeningResource($rekening);
                } else {
                    // Add an error note if the record does not exist
                    return response()->json([
                        'message' => "Rekening ID {$data['id']} at index {$index} not found or does not belong to the user.",
                    ], 404);
                }
            }
    
            // Return success response
            return response()->json([
                'data' => count($updatedRekenings) == 1
                    ? $updatedRekenings[0]
                    : $updatedRekenings,
                'message' => 'Rekenings updated successfully!',
            ], 200);
    
        } catch (ValidationException $e) {
            // Handle validation errors and return the failed field names and messages
            return response()->json([
                'message' => 'Validation failed. Please check the form inputs.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Handle any other unexpected errors
            return response()->json([
                'message' => 'An error occurred while updating the records.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
