<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Import Auth facade
use App\Models\Acara;
use App\Models\CountdownAcara;
use App\Http\Resources\Acara\AcaraResource;
use App\Http\Resources\Acara\AcaraCollection;

class AcaraController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index()
    {
        $userId = Auth::id();
        
        $acaras = Acara::with('countdown')->where('user_id', $userId)->get();
        return new AcaraCollection(AcaraResource::collection($acaras));
    }

    public function storeCountDown(Request $request)
    {
        $validateData = $request->validate([
            'name_countdown' => 'required'
        ]);

        $userId = Auth::id();        
        $countDown = new CountdownAcara();
        $countDown->user_id = $userId; 
        $countDown->name_countdown = $validateData['name_countdown'];
        $countDown->save();

        if ($countDown) {
            return response()->json([
                'name_countdown' => $countDown,
                'message' => 'Countdown has been successfully added!'
            ]);
        } else {
            return response()->json([
                'message' => 'Failed to add countdown!',
            ], 400);
        }
    }

    public function store(Request $request)
    {
        $nameAcara = $request->input('nama_acara', []);
        $tglAcara = $request->input('tanggal_acara', []);
        $startAcara = $request->input('start_acara', []);
        $endAcara = $request->input('end_acara', []);
        $alamatAcara = $request->input('alamat', []);
        $linkAcara = $request->input('link_maps', []);

        $count = count($nameAcara);
        $userId = Auth::id();
        $savedAcara = [];
        $countDown = CountdownAcara::where('user_id', $userId)->latest('created_at')->first();

        if (!$countDown) {
            return response()->json([
                'message' => 'No countdown is associated with the user. Please create a countdown first.',
            ], 400);
        }

        for ($i = 0; $i < $count; $i++) {
            if (
                empty($nameAcara[$i]) || empty($tglAcara[$i]) ||
                empty($startAcara[$i]) || empty($endAcara[$i]) ||
                empty($alamatAcara[$i]) || empty($linkAcara[$i])
            ) {
                return response()->json([
                    'message' => 'Some required fields are missing for index ' . $i,
                ], 400);
            }

            $acara = new Acara();
            $acara->countdown_id = $countDown->id;
            $acara->nama_acara = $nameAcara[$i];
            $acara->tanggal_acara = $tglAcara[$i];
            $acara->start_acara = $startAcara[$i];
            $acara->end_acara = $endAcara[$i];
            $acara->alamat = $alamatAcara[$i];
            $acara->link_maps = $linkAcara[$i];
            $acara->user_id = $userId; 
            $acara->save();

            $savedAcara[] = [
                'nama_acara' => $acara->nama_acara,
                'tanggal_acara' => $acara->tanggal_acara,
                'start_acara' => $acara->start_acara,
                'end_acara' => $acara->end_acara,
                'alamat' => $acara->alamat,
                'link_maps' => $acara->link_maps,
                'countdown_id' => $acara->countdown_id,
            ];
        }

        return response()->json([
            'data' => $savedAcara,
            'user_id' => $userId,
            'message' => 'Acara has been successfully added!',
        ], 201);
    }

public function updateCountDown(Request $request, $id)
    {
        $countDown = CountdownAcara::find($id);
        if (!$countDown) {
            return response()->json(['message' => 'Countdown not found!'], 404);
        }

        $validateData = $request->validate([
            'name_countdown' => 'required|string|min:1',
        ]);

        $countDown->name_countdown = $validateData['name_countdown'];

        if ($countDown->save()) {
            return response()->json(['data' => $countDown, 'message' => 'Countdown updated successfully!'], 200);
        }
        return response()->json(['message' => 'Failed to update countdown!'], 400);
    }
 
    public function updateAcara(Request $request)
    {
        try {
            $validated = $request->validate([
                'data' => 'required|array',
                'data.*.id' => 'required|integer|exists:acaras,id', // Updated table name if needed
                'data.*.nama_acara' => 'required|string',
                'data.*.tanggal_acara' => 'required|date',
                'data.*.start_acara' => 'required|string',
                'data.*.end_acara' => 'required|string',
                'data.*.alamat' => 'required|string',
                'data.*.link_maps' => 'required|string',
            ]);

            $updatedRecords = [];

            foreach ($validated['data'] as $event) {
                $acara = Acara::find($event['id']);
                if ($acara) {
                    $acara->update($event);
                    $updatedRecords[] = $acara;
                }
            }

            return response()->json([
                'data' => $updatedRecords,
                'message' => 'Acara records have been successfully updated!',
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating the Acara records.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }





}
