<?php

namespace App\Http\Controllers;

use App\Models\AttendanceScan;
use App\Models\Acara;
use App\Models\BukuTamu;
use App\Models\WeddingGuest;
use App\Services\DomainService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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
                'url' => ['required_without_all:scanned_value,guest_token', 'string', 'max:2048'],
                'scanned_value' => ['required_without_all:url,guest_token', 'string', 'max:2048'],
                'guest_token' => ['required_without_all:url,scanned_value', 'string', 'max:255'],
                'acara_id' => ['nullable', 'integer'],
                'notes' => ['nullable', 'string', 'max:500'],
            ], [
                'url.required_without_all' => 'URL atau token tamu wajib diisi.',
                'scanned_value.required_without_all' => 'URL atau token tamu wajib diisi.',
                'guest_token.required_without_all' => 'URL atau token tamu wajib diisi.',
            ]);

            $scannedValue = trim((string) ($validated['guest_token'] ?? $validated['scanned_value'] ?? $validated['url']));
            $domain = $this->domainService->normalizeToSlug($scannedValue);
            $guestToken = $this->guestTokenFromScannedValue($scannedValue, $validated['guest_token'] ?? null);
            $legacyGuestCode = $this->guestCodeFromUrl($scannedValue);

            if ($guestToken === '' && $legacyGuestCode === '') {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'code' => 'GUEST_CODE_REQUIRED',
                    'message' => 'Token tamu atau parameter to wajib diisi.',
                ], 422);
            }

            if ($guestToken === '' && $legacyGuestCode !== '' && ! $this->domainService->resolveOwnerUserIdByDomain($domain)) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'code' => 'INVITATION_NOT_FOUND',
                    'message' => 'Undangan tidak ditemukan.',
                ], 404);
            }

            $guest = $this->resolveGuestFromScan($guestToken, $legacyGuestCode, $domain);

            if (! $guest) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'code' => 'GUEST_NOT_FOUND',
                    'message' => 'Data tamu tidak ditemukan. Pastikan tamu sudah dibuat atau link undangan benar.',
                ], 404);
            }

            $ownerUserId = (int) $guest->user_id;
            $domain = $domain !== '' ? $domain : $this->domainForGuest($guest);
            $acara = $this->resolveAttendanceAcara((int) $ownerUserId, $validated['acara_id'] ?? null);
            if (! $acara) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'code' => 'EVENT_NOT_FOUND',
                    'message' => 'Data acara undangan tidak ditemukan.',
                ], 422);
            }

            $result = $this->recordGuestAttendance($guest, $acara, $request, $legacyGuestCode ?: (string) $guest->guest_token, auth()->id());

            return response()->json([
                'status' => true,
                'success' => true,
                'code' => $result['already_scanned'] ? 'GUEST_ALREADY_CHECKED_IN' : 'GUEST_CHECKED_IN',
                'message' => $result['already_scanned']
                    ? 'Tamu ini sudah tercatat hadir.'
                    : 'Kehadiran tamu berhasil dicatat.',
                'data' => $this->scanPayload($guest->refresh(), $domain, $legacyGuestCode, $result['scanned_at'], $result['already_scanned']),
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
                'guest_token' => 'nullable|string|max:255',
                'scanned_value' => 'nullable|string|max:2048',
                'url' => 'nullable|string|max:2048',
                'guest_identifier' => 'nullable|string|max:255',
                'guest_name' => 'required_without_all:guest_identifier,guest_token,scanned_value,url|string|max:255',
                'scan_type' => 'required|in:qr_code,manual',
                'notes' => 'nullable|string|max:500',
            ]);

            $userId = auth()->id();

            // Verify the acara belongs to the authenticated user
            $acara = Acara::where('id', $validated['acara_id'])
                          ->where('user_id', $userId)
                          ->firstOrFail();

            $scannedValue = trim((string) ($validated['guest_token'] ?? $validated['scanned_value'] ?? $validated['url'] ?? $validated['guest_identifier'] ?? ''));
            $guestToken = $this->guestTokenFromScannedValue($scannedValue, $validated['guest_token'] ?? null);
            $legacyGuestCode = $this->guestCodeFromUrl($scannedValue) ?: Str::slug((string) ($validated['guest_identifier'] ?? ''), '-');

            if ($guestToken || $legacyGuestCode) {
                $domain = $this->domainService->normalizeToSlug($scannedValue);
                $guest = $this->resolveGuestFromScan($guestToken, $legacyGuestCode, $domain, (int) $userId);

                if (! $guest) {
                    return response()->json([
                        'status' => false,
                        'success' => false,
                        'code' => 'GUEST_NOT_FOUND',
                        'message' => 'Data tamu tidak ditemukan. Pastikan tamu sudah dibuat atau link undangan benar.',
                    ], 404);
                }

                $result = $this->recordGuestAttendance($guest, $acara, $request, $legacyGuestCode ?: (string) $guest->guest_token, (int) $userId);

                return response()->json([
                    'status' => true,
                    'success' => true,
                    'code' => $result['already_scanned'] ? 'GUEST_ALREADY_CHECKED_IN' : 'GUEST_CHECKED_IN',
                    'message' => $result['already_scanned']
                        ? 'Tamu ini sudah tercatat hadir.'
                        : 'Kehadiran tamu berhasil dicatat.',
                    'data' => $this->scanPayload($guest->refresh(), $this->domainForGuest($guest), $legacyGuestCode, $result['scanned_at'], $result['already_scanned']),
                ], 200);
            }

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

    private function guestTokenFromScannedValue(string $scannedValue, ?string $explicitToken = null): string
    {
        $explicitToken = trim((string) $explicitToken);
        if ($explicitToken !== '') {
            return $explicitToken;
        }

        $parts = parse_url($scannedValue);
        $query = [];

        if (is_array($parts) && isset($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        return trim((string) ($query['guest'] ?? $query['guest_token'] ?? ''));
    }

    private function resolveGuestFromScan(string $guestToken, string $legacyGuestCode, string $domain, ?int $ownerUserId = null): ?WeddingGuest
    {
        if ($guestToken !== '') {
            $query = WeddingGuest::query()->where('guest_token', $guestToken);

            if ($ownerUserId !== null) {
                $query->where('user_id', $ownerUserId);
            }

            if ($domain !== '') {
                $query->where(function ($query) use ($domain) {
                    $query->whereRaw('LOWER(domain) = ?', [strtolower($domain)])
                        ->orWhereRaw('LOWER(domain) LIKE ?', ['%/'.$domain]);
                });
            }

            return $query->first();
        }

        if ($legacyGuestCode === '' || $domain === '') {
            return null;
        }

        $resolvedOwnerId = $this->domainService->resolveOwnerUserIdByDomain($domain);
        if (! $resolvedOwnerId || ($ownerUserId !== null && (int) $resolvedOwnerId !== $ownerUserId)) {
            return null;
        }

        return $this->findUniqueLegacyGuestByCode((int) $resolvedOwnerId, $domain, $legacyGuestCode);
    }

    private function findUniqueLegacyGuestByCode(int $userId, string $domain, string $guestCode): ?WeddingGuest
    {
        $guestCode = Str::slug($guestCode, '-');

        $matches = WeddingGuest::query()
            ->where('user_id', $userId)
            ->where(function ($query) use ($domain) {
                $query->whereRaw('LOWER(domain) = ?', [strtolower($domain)])
                    ->orWhereRaw('LOWER(domain) LIKE ?', ['%/'.$domain]);
            })
            ->get()
            ->filter(function (WeddingGuest $guest) use ($guestCode): bool {
                if (Schema::hasColumn('wedding_guests', 'guest_code') && Str::lower((string) $guest->guest_code) === $guestCode) {
                    return true;
                }

                return Str::slug((string) $guest->guest_name, '-') === $guestCode;
            })
            ->values();

        return $matches->count() === 1 ? $matches->first() : null;
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

    /**
     * @return array{scanned_at:mixed,already_scanned:bool}
     */
    private function recordGuestAttendance(WeddingGuest $guest, Acara $acara, Request $request, string $fallbackIdentifier, ?int $scannerId): array
    {
        return DB::transaction(function () use ($guest, $acara, $request, $fallbackIdentifier, $scannerId): array {
            $guest = WeddingGuest::query()->lockForUpdate()->findOrFail($guest->id);
            $identifiers = $this->guestIdentifiers($guest, $fallbackIdentifier);

            $existingScan = AttendanceScan::query()
                ->where('user_id', (int) $guest->user_id)
                ->where('acara_id', $acara->id)
                ->whereIn('guest_identifier', $identifiers)
                ->orderBy('scanned_at')
                ->first();

            if ($existingScan || $guest->attended) {
                $scannedAt = $existingScan?->scanned_at ?: $guest->attended_at;
                $this->markGuestAndBookAsAttended($guest, $acara, $request, $scannedAt);

                return [
                    'scanned_at' => $scannedAt,
                    'already_scanned' => true,
                ];
            }

            $scan = AttendanceScan::create([
                'user_id' => (int) $guest->user_id,
                'acara_id' => $acara->id,
                'guest_name' => $guest->guest_name,
                'guest_identifier' => $this->primaryGuestIdentifier($guest, $fallbackIdentifier),
                'scan_type' => 'qr_code',
                'scanned_at' => now(),
                'scanned_by' => $scannerId,
                'notes' => $request->input('notes'),
            ]);

            $this->markGuestAndBookAsAttended($guest, $acara, $request, $scan->scanned_at);

            return [
                'scanned_at' => $scan->scanned_at,
                'already_scanned' => false,
            ];
        });
    }

    private function scanPayload(WeddingGuest $guest, string $domain, string $guestCode, $scannedAt, bool $alreadyScanned): array
    {
        return [
            'id' => $guest->id,
            'name' => $guest->guest_name,
            'guest_name' => $guest->guest_name,
            'guest_code' => $this->primaryGuestIdentifier($guest, $guestCode),
            'guest_token' => $guest->guest_token,
            'invitation_url' => $this->invitationUrlForGuest($guest, $domain),
            'domain' => $domain,
            'attendance_status' => 'present',
            'checked_in_at' => $this->formatScanTime($scannedAt),
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

    private function invitationUrlForGuest(WeddingGuest $guest, string $domain): string
    {
        $storedUrl = (string) $guest->invitation_url;
        if ($storedUrl !== '' && str_contains($storedUrl, 'guest=')) {
            return $storedUrl;
        }

        $frontendUrl = rtrim((string) config('app.frontend_url', 'https://www.sena-digital.com'), '/');
        $guestCode = $this->primaryGuestIdentifier($guest, (string) $guest->guest_token);

        return $frontendUrl.'/wedding/'.$domain.'?'.http_build_query([
            'guest' => $guest->guest_token,
            'to' => $guestCode,
        ]);
    }

    private function domainForGuest(WeddingGuest $guest): string
    {
        return $this->domainService->normalizeToSlug((string) $guest->domain);
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
