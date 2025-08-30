<?php

namespace App\Http\Controllers;

use App\Http\Resources\Ucapan\UcapanCollection;
use App\Http\Resources\Ucapan\UcapanResource;
use App\Models\Ucapan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UcapanController extends Controller
{
    /**
     * Display a listing of ucapan (public endpoint)
     */
    public function index(): JsonResponse
    {
        try {
            $ucapans = Ucapan::orderBy('created_at', 'desc')->get();
            
            return response()->json(new UcapanCollection($ucapans), 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve ucapan data.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created ucapan (public endpoint)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'nama' => 'required|string|max:255',
                'kehadiran' => 'required|in:hadir,tidak_hadir,mungkin',
                'pesan' => 'required|string|max:1000',
            ], [
                'nama.required' => 'Nama wajib diisi.',
                'nama.max' => 'Nama tidak boleh lebih dari 255 karakter.',
                'kehadiran.required' => 'Status kehadiran wajib dipilih.',
                'kehadiran.in' => 'Status kehadiran tidak valid.',
                'pesan.required' => 'Ucapan wajib diisi.',
                'pesan.max' => 'Ucapan tidak boleh lebih dari 1000 karakter.',
            ]);

            $ucapan = Ucapan::create($validated);

            return response()->json([
                'message' => 'Ucapan berhasil disimpan!',
                'data' => new UcapanResource($ucapan)
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Data yang dikirim tidak valid.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menyimpan ucapan.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified ucapan (public endpoint)
     */
    public function show(string $id): JsonResponse
    {
        try {
            $ucapan = Ucapan::findOrFail($id);
            
            return response()->json([
                'data' => new UcapanResource($ucapan)
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Ucapan tidak ditemukan.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data ucapan.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified ucapan (public endpoint)
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $ucapan = Ucapan::findOrFail($id);
            $ucapan->delete();

            return response()->json([
                'message' => 'Ucapan berhasil dihapus.'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Ucapan tidak ditemukan.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menghapus ucapan.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get statistics about ucapan responses
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_ucapan' => Ucapan::count(),
                'hadir' => Ucapan::where('kehadiran', 'hadir')->count(),
                'tidak_hadir' => Ucapan::where('kehadiran', 'tidak_hadir')->count(),
                'mungkin' => Ucapan::where('kehadiran', 'mungkin')->count(),
            ];

            return response()->json([
                'data' => $stats
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil statistik ucapan.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}