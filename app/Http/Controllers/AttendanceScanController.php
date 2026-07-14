<?php

namespace App\Http\Controllers;

use App\Models\AttendanceScan;
use App\Models\Acara;
use App\Models\BukuTamu;
use App\Models\WeddingGuest;
use App\Services\DomainService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceScanController extends Controller
{
    public function __construct(private DomainService $domainService)
    {
        $this->middleware('auth:sanctum')->except(['scanFromUrl', 'publicList', 'publicExport']);
    }

    /**
     * Process QR scan from public invitation URL.
     * POST /v1/attendance/scan
     */
    public function scanFromUrl(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'url' => ['required', 'string', 'max:2048'],
                'acara_id' => ['nullable', 'integer'],
                'notes' => ['nullable', 'string', 'max:500'],
            ], [
                'url.required' => 'URL QR wajib diisi.',
            ]);

            $url = trim($validated['url']);
            $domain = $this->domainService->normalizeToSlug($url);
            $guestCode = $this->guestCodeFromUrl($url);

            if ($guestCode === '') {
                return response()->json([
                    'status' => false,
                    'code' => 'GUEST_CODE_REQUIRED',
                    'message' => 'Kode tamu pada parameter to wajib diisi.',
                ], 422);
            }

            $ownerUserId = $this->domainService->resolveOwnerUserIdByDomain($domain);
            if (! $ownerUserId) {
                return response()->json([
                    'status' => false,
                    'code' => 'INVITATION_NOT_FOUND',
                    'message' => 'Undangan tidak ditemukan.',
                ], 404);
            }

            $guest = $this->findGuestByCode((int) $ownerUserId, $domain, $guestCode);
            if (! $guest) {
                return response()->json([
                    'status' => false,
                    'code' => 'GUEST_NOT_FOUND',
                    'message' => 'Data tamu tidak ditemukan. Pastikan tamu sudah dibuat atau link undangan benar.',
                ], 404);
            }

            $acara = $this->resolveAttendanceAcara((int) $ownerUserId, $validated['acara_id'] ?? null);
            if (! $acara) {
                return response()->json([
                    'status' => false,
                    'code' => 'EVENT_NOT_FOUND',
                    'message' => 'Data acara undangan tidak ditemukan.',
                ], 422);
            }

            $identifiers = $this->guestIdentifiers($guest, $guestCode);
            $existingScan = AttendanceScan::query()
                ->where('user_id', (int) $ownerUserId)
                ->where('acara_id', $acara->id)
                ->whereIn('guest_identifier', $identifiers)
                ->orderBy('scanned_at')
                ->first();

            if ($existingScan) {
                $this->markGuestAndBookAsAttended($guest, $acara, $request, $existingScan->scanned_at);

                return response()->json([
                    'status' => true,
                    'message' => 'Tamu sudah pernah discan sebelumnya.',
                    'data' => $this->scanPayload($guest, $domain, $guestCode, $existingScan->scanned_at, true),
                ], 200);
            }

            $scan = AttendanceScan::create([
                'user_id' => (int) $ownerUserId,
                'acara_id' => $acara->id,
                'guest_name' => $guest->guest_name,
                'guest_identifier' => $this->primaryGuestIdentifier($guest, $guestCode),
                'scan_type' => 'qr_code',
                'scanned_at' => now(),
                'scanned_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->markGuestAndBookAsAttended($guest, $acara, $request, $scan->scanned_at);

            return response()->json([
                'status' => true,
                'message' => 'Kehadiran tamu berhasil dicatat.',
                'data' => $this->scanPayload($guest, $domain, $guestCode, $scan->scanned_at, false),
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal memproses scan kehadiran.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * List QR attendance scans from database.
     * GET /v1/attendance/list?domain=nova-yusril
     */
    public function publicList(Request $request): JsonResponse
    {
        [$ownerUserId, $domain, $error] = $this->resolveOwnerForAttendanceRequest($request);

        if ($error) {
            return $error;
        }

        $scans = AttendanceScan::query()
            ->where('user_id', $ownerUserId)
            ->orderByDesc('scanned_at')
            ->get()
            ->map(function (AttendanceScan $scan) use ($domain) {
                $guest = $this->findGuestByCode((int) $scan->user_id, $domain, (string) $scan->guest_identifier);
                $guestCode = $this->primaryGuestIdentifier($guest, (string) $scan->guest_identifier);

                return [
                    'id' => $scan->id,
                    'guest_name' => $scan->guest_name,
                    'guest_code' => $guestCode,
                    'domain' => $domain ?: ($guest?->domain ?? null),
                    'scanned_at' => $this->formatScanTime($scan->scanned_at),
                    'status' => 'hadir',
                    'scan_type' => $scan->scan_type,
                ];
            })
            ->values();

        return response()->json([
            'status' => true,
            'message' => 'Data tamu hadir berhasil diambil.',
            'data' => $scans,
            'total' => $scans->count(),
        ]);
    }

    /**
     * Export QR attendance scans from database as CSV payload.
     * GET /v1/attendance/export?domain=nova-yusril
     */
    public function publicExport(Request $request): JsonResponse
    {
        [$ownerUserId, $domain, $error] = $this->resolveOwnerForAttendanceRequest($request);

        if ($error) {
            return $error;
        }

        $rows = AttendanceScan::query()
            ->where('user_id', $ownerUserId)
            ->orderByDesc('scanned_at')
            ->get();

        $csv = "No,Nama Tamu,Kode Tamu,Domain,Status,Waktu Scan\n";
        foreach ($rows as $index => $scan) {
            $guest = $this->findGuestByCode((int) $scan->user_id, $domain, (string) $scan->guest_identifier);
            $csv .= implode(',', [
                $index + 1,
                '"'.str_replace('"', '""', $scan->guest_name).'"',
                $this->primaryGuestIdentifier($guest, (string) $scan->guest_identifier),
                $domain ?: ($guest?->domain ?? ''),
                'hadir',
                $this->formatScanTime($scan->scanned_at),
            ])."\n";
        }

        return response()->json([
            'status' => true,
            'message' => 'Data tamu hadir berhasil diekspor.',
            'data' => [
                'content' => base64_encode($csv),
                'filename' => 'attendance_scans_'.($domain ?: 'undangan').'_'.now()->format('Y-m-d').'.csv',
                'mime_type' => 'text/csv',
            ],
        ]);
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

    private function guestCodeFromUrl(string $url): string
    {
        $parts = parse_url($url);
        $query = [];

        if (is_array($parts) && isset($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        return Str::slug((string) ($query['to'] ?? ''), '-');
    }

    private function findGuestByCode(int $userId, string $domain, string $guestCode): ?WeddingGuest
    {
        $guestCode = Str::slug($guestCode, '-');

        if ($guestCode === '') {
            return null;
        }

        $query = WeddingGuest::query()->where('user_id', $userId);

        $guest = (clone $query)
            ->where(function ($query) use ($guestCode) {
                $query->where('guest_token', $guestCode);

                if (Schema::hasColumn('wedding_guests', 'guest_code')) {
                    $query->orWhereRaw('LOWER(guest_code) = ?', [$guestCode]);
                }
            })
            ->first();

        if ($guest) {
            return $guest;
        }

        return (clone $query)
            ->where(function ($query) use ($domain) {
                $query->whereRaw('LOWER(domain) = ?', [strtolower($domain)])
                    ->orWhereRaw('LOWER(domain) LIKE ?', ['%/'.$domain]);
            })
            ->get()
            ->first(fn (WeddingGuest $guest): bool => Str::slug((string) $guest->guest_name, '-') === $guestCode);
    }

    private function resolveAttendanceAcara(int $userId, null|int|string $acaraId): ?Acara
    {
        $query = Acara::query()->where('user_id', $userId);

        if ($acaraId) {
            return (clone $query)->whereKey((int) $acaraId)->first();
        }

        return $query
            ->orderBy('tanggal_acara')
            ->orderBy('id')
            ->first();
    }

    private function markGuestAndBookAsAttended(WeddingGuest $guest, Acara $acara, Request $request, $scannedAt): void
    {
        if (! $guest->attended) {
            $guest->attended = true;
            $guest->attended_at = $scannedAt;
            $guest->attended_acara_id = $acara->id;
            $guest->save();
        }

        BukuTamu::updateOrCreate(
            [
                'user_id' => $guest->user_id,
                'nama' => $guest->guest_name,
            ],
            [
                'status_kehadiran' => 'hadir',
                'jumlah_tamu' => 1,
                'is_approved' => true,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );
    }

    private function scanPayload(WeddingGuest $guest, string $domain, string $guestCode, $scannedAt, bool $alreadyScanned): array
    {
        return [
            'guest_name' => $guest->guest_name,
            'guest_code' => $this->primaryGuestIdentifier($guest, $guestCode),
            'domain' => $domain,
            'attendance_status' => 'hadir',
            'scanned_at' => $this->formatScanTime($scannedAt),
            'already_scanned' => $alreadyScanned,
        ];
    }

    private function guestIdentifiers(WeddingGuest $guest, string $fallback): array
    {
        return array_values(array_unique(array_filter([
            $this->primaryGuestIdentifier($guest, $fallback),
            Schema::hasColumn('wedding_guests', 'guest_code') ? $guest->guest_code : null,
            $guest->guest_token,
            Str::slug((string) $guest->guest_name, '-'),
            $fallback,
        ])));
    }

    private function primaryGuestIdentifier(?WeddingGuest $guest, string $fallback): string
    {
        if ($guest && Schema::hasColumn('wedding_guests', 'guest_code') && $guest->guest_code) {
            return (string) $guest->guest_code;
        }

        if ($guest && Str::slug((string) $guest->guest_name, '-') !== '') {
            return Str::slug((string) $guest->guest_name, '-');
        }

        return Str::slug($fallback, '-');
    }

    private function formatScanTime($date): ?string
    {
        return $date ? $date->format('d/m/Y H.i.s') : null;
    }

    /**
     * @return array{0:?int,1:string,2:?JsonResponse}
     */
    private function resolveOwnerForAttendanceRequest(Request $request): array
    {
        $domainInput = (string) ($request->query('domain') ?: $request->query('url') ?: '');
        $domain = $this->domainService->normalizeToSlug($domainInput);
        $ownerUserId = $domain !== ''
            ? $this->domainService->resolveOwnerUserIdByDomain($domain)
            : auth()->id();

        if (! $ownerUserId) {
            return [
                null,
                $domain,
                response()->json([
                    'status' => false,
                    'code' => $domain === '' ? 'DOMAIN_REQUIRED' : 'INVITATION_NOT_FOUND',
                    'message' => $domain === ''
                        ? 'Domain undangan wajib diisi.'
                        : 'Undangan tidak ditemukan.',
                ], $domain === '' ? 422 : 404),
            ];
        }

        return [(int) $ownerUserId, $domain, null];
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
