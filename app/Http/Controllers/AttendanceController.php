<?php

namespace App\Http\Controllers;

use App\Models\Ucapan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    /**
     * Store attendance confirmation (public endpoint)
     * POST /v1/attendance
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'nama' => 'required|string|max:255',
                'kehadiran' => 'required|in:hadir,tidak_hadir,mungkin',
                'pesan' => 'required|string|max:1000',
            ], [
                'user_id.required' => 'User ID wajib diisi.',
                'user_id.exists' => 'User tidak ditemukan.',
                'nama.required' => 'Nama wajib diisi.',
                'nama.max' => 'Nama tidak boleh lebih dari 255 karakter.',
                'kehadiran.required' => 'Status kehadiran wajib dipilih.',
                'kehadiran.in' => 'Status kehadiran harus: hadir, tidak_hadir, atau mungkin.',
                'pesan.required' => 'Ucapan wajib diisi.',
                'pesan.max' => 'Ucapan tidak boleh lebih dari 1000 karakter.',
            ]);

            $attendance = Ucapan::create($validated);

            return response()->json([
                'message' => 'Konfirmasi kehadiran berhasil disimpan!',
                'data' => [
                    'id' => $attendance->id,
                    'user_id' => $attendance->user_id,
                    'nama' => $attendance->nama,
                    'kehadiran' => $attendance->kehadiran,
                    'pesan' => $attendance->pesan,
                    'created_at' => $attendance->created_at?->format('Y-m-d H:i:s'),
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Data yang dikirim tidak valid.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menyimpan konfirmasi kehadiran.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete single attendance record (public endpoint)
     * DELETE /v1/attendance/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $attendance = Ucapan::findOrFail($id);
            $attendance->delete();

            return response()->json([
                'message' => 'Konfirmasi kehadiran berhasil dihapus.'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Data konfirmasi kehadiran tidak ditemukan.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menghapus konfirmasi kehadiran.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete all attendance records for specific user (public endpoint)
     * DELETE /v1/attendance/user/{user_id}/all
     */
    public function destroyAllByUser(string $user_id): JsonResponse
    {
        try {
            // Verify user exists
            $user = User::findOrFail($user_id);
            
            $deletedCount = Ucapan::where('user_id', $user_id)->delete();

            return response()->json([
                'message' => "Berhasil menghapus {$deletedCount} konfirmasi kehadiran untuk wedding {$user->name}.",
                'deleted_count' => $deletedCount
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User tidak ditemukan.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menghapus konfirmasi kehadiran.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get attendance statistics for specific wedding (public endpoint)
     * GET /v1/attendance/stats/{user_id}
     */
    public function statistics(string $user_id): JsonResponse
    {
        try {
            // Verify user exists
            $user = User::findOrFail($user_id);

            $stats = [
                'user_id' => (int) $user_id,
                'wedding_owner' => $user->name,
                'total_responses' => Ucapan::where('user_id', $user_id)->count(),
                'hadir' => Ucapan::where('user_id', $user_id)->where('kehadiran', 'hadir')->count(),
                'tidak_hadir' => Ucapan::where('user_id', $user_id)->where('kehadiran', 'tidak_hadir')->count(),
                'mungkin' => Ucapan::where('user_id', $user_id)->where('kehadiran', 'mungkin')->count(),
                'response_rate' => [
                    'hadir_percentage' => 0,
                    'tidak_hadir_percentage' => 0,
                    'mungkin_percentage' => 0,
                ]
            ];

            // Calculate percentages
            if ($stats['total_responses'] > 0) {
                $stats['response_rate']['hadir_percentage'] = round(($stats['hadir'] / $stats['total_responses']) * 100, 2);
                $stats['response_rate']['tidak_hadir_percentage'] = round(($stats['tidak_hadir'] / $stats['total_responses']) * 100, 2);
                $stats['response_rate']['mungkin_percentage'] = round(($stats['mungkin'] / $stats['total_responses']) * 100, 2);
            }

            return response()->json([
                'data' => $stats
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User tidak ditemukan.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil statistik kehadiran.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all attendance records for specific wedding (public endpoint)
     * GET /v1/attendance/user/{user_id}
     */
    public function getByUser(string $user_id): JsonResponse
    {
        try {
            // Verify user exists
            $user = User::findOrFail($user_id);

            $attendances = Ucapan::where('user_id', $user_id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($attendance) {
                    return [
                        'id' => $attendance->id,
                        'nama' => $attendance->nama,
                        'kehadiran' => $attendance->kehadiran,
                        'pesan' => $attendance->pesan,
                        'created_at' => $attendance->created_at?->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json([
                'data' => [
                    'user_id' => (int) $user_id,
                    'wedding_owner' => $user->name,
                    'total_responses' => $attendances->count(),
                    'attendances' => $attendances
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User tidak ditemukan.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data kehadiran.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}