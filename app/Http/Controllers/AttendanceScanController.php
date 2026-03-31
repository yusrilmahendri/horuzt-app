<?php

namespace App\Http\Controllers;

use App\Models\AttendanceScan;
use App\Models\Acara;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AttendanceScanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Process QR scan and mark attendance
     * POST /v1/user/attendance-scans
     */
    public function scan(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'acara_id' => 'required|integer|exists:acaras,id',
                'guest_identifier' => 'nullable|string|max:255',
                'guest_name' => 'required_without:guest_identifier|string|max:255',
                'scan_type' => 'required|in:qr_code,manual',
                'notes' => 'nullable|string|max:500',
            ]);

            $userId = auth()->id();

            // Verify the acara belongs to the authenticated user
            $acara = Acara::where('id', $validated['acara_id'])
                          ->where('user_id', $userId)
                          ->firstOrFail();

            // Check for duplicate scan (same guest, same event)
            if (!empty($validated['guest_identifier'])) {
                $existingScan = AttendanceScan::where('acara_id', $validated['acara_id'])
                                             ->where('guest_identifier', $validated['guest_identifier'])
                                             ->first();

                if ($existingScan) {
                    return response()->json([
                        'message' => 'Tamu ini sudah di-scan sebelumnya',
                        'data' => [
                            'id' => $existingScan->id,
                            'guest_name' => $existingScan->guest_name,
                            'scanned_at' => $existingScan->scanned_at->format('H:i:s'),
                        ]
                    ], 409); // 409 Conflict
                }
            }

            // Create attendance scan record
            $scan = AttendanceScan::create([
                'user_id' => $userId,
                'acara_id' => $validated['acara_id'],
                'guest_name' => $validated['guest_name'],
                'guest_identifier' => $validated['guest_identifier'] ?? null,
                'scan_type' => $validated['scan_type'],
                'scanned_at' => now(),
                'scanned_by' => $userId,
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json([
                'message' => 'Kehadiran berhasil dicatat',
                'data' => [
                    'id' => $scan->id,
                    'guest_name' => $scan->guest_name,
                    'acara_type' => $acara->jenis_acara,
                    'scan_type' => $scan->scan_type,
                    'scanned_at' => $scan->scanned_at->format('H:i:s'),
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal memproses scan',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get attendance scans for user's events
     * GET /v1/user/attendance-scans
     */
    public function index(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $acaraId = $request->query('acara_id');
        $scanType = $request->query('scan_type');
        $date = $request->query('date');

        $query = AttendanceScan::with(['acara', 'scannedBy'])
                              ->where('user_id', $userId)
                              ->orderBy('scanned_at', 'desc');

        if ($acaraId) {
            $query->where('acara_id', $acaraId);
        }

        if ($scanType) {
            $query->where('scan_type', $scanType);
        }

        if ($date) {
            $query->whereDate('scanned_at', $date);
        }

        $scans = $query->paginate(50);

        return response()->json([
            'message' => 'Data scan kehadiran berhasil diambil',
            'data' => $scans,
        ], 200);
    }

    /**
     * Get attendance statistics
     * GET /v1/user/attendance-scans/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $acaraId = $request->query('acara_id');

        $query = AttendanceScan::where('user_id', $userId);

        if ($acaraId) {
            $query->where('acara_id', $acaraId);
        }

        $totalScans = (clone $query)->count();
        $qrScans = (clone $query)->where('scan_type', 'qr_code')->count();
        $manualScans = (clone $query)->where('scan_type', 'manual')->count();
        $todayScans = (clone $query)->whereDate('scanned_at', today())->count();

        // Get breakdown by acara type
        $byAcaraType = AttendanceScan::where('attendance_scans.user_id', $userId)
                                     ->join('acaras', 'attendance_scans.acara_id', '=', 'acaras.id')
                                     ->selectRaw('acaras.jenis_acara, COUNT(*) as count')
                                     ->groupBy('acaras.jenis_acara')
                                     ->pluck('count', 'acaras.jenis_acara');

        return response()->json([
            'message' => 'Statistik kehadiran berhasil diambil',
            'data' => [
                'total_scans' => $totalScans,
                'qr_scans' => $qrScans,
                'manual_scans' => $manualScans,
                'today_scans' => $todayScans,
                'by_acara_type' => $byAcaraType,
            ],
        ], 200);
    }

    /**
     * Delete a scan record
     * DELETE /v1/user/attendance-scans/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $userId = auth()->id();

            $scan = AttendanceScan::where('id', $id)
                                  ->where('user_id', $userId)
                                  ->firstOrFail();

            $scan->delete();

            return response()->json([
                'message' => 'Data scan berhasil dihapus',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Data scan tidak ditemukan',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menghapus data scan',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
