<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\WeddingGuest;
use App\Models\Setting;
use App\Models\BukuTamu;
use App\Models\AttendanceScan;
use App\Services\DomainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use ZipArchive;

class GuestTrackingController extends Controller
{
    public function __construct(private DomainService $domainService)
    {
    }

    /**
     * Track guest visit when opening invitation
     * POST /v1/wedding-guests/track
     */
    public function track(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'guest_name' => 'required|string|max:255',
            'domain' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Find user by domain
            $setting = Setting::where('domain', $request->domain)->first();

            if (!$setting) {
                return response()->json([
                    'message' => 'Wedding not found for this domain',
                ], 404);
            }

            $userId = $setting->user_id;
            $guestName = $request->guest_name;

            // Check if guest already exists
            $guest = WeddingGuest::where('user_id', $userId)
                ->where('guest_name', $guestName)
                ->first();

            if (!$guest) {
                // Create new guest record
                $guestData = [
                    'user_id' => $userId,
                    'guest_name' => $guestName,
                    'guest_token' => WeddingGuest::generateUniqueToken($guestName, $request->domain),
                    'domain' => $request->domain,
                    'first_visit_at' => now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ];

                if (Schema::hasColumn('wedding_guests', 'guest_code')) {
                    $guestData['guest_code'] = $this->uniqueGuestCode($userId, $guestName);
                }

                $guest = WeddingGuest::create($guestData);
            } else {
                // Update visit info if already exists
                $guest->update([
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            return response()->json([
                'message' => 'Guest tracked successfully',
                'data' => [
                    'guest_token' => $guest->guest_token,
                    'guest_name' => $guest->guest_name,
                    'first_visit_at' => $guest->first_visit_at,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to track guest',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Verify guest QR token
     * GET /v1/wedding-guests/verify/{token}
     */
    public function verify(string $token): JsonResponse
    {
        try {
            $guest = WeddingGuest::where('guest_token', $token)->first();

            if (!$guest) {
                return response()->json([
                    'message' => 'Invalid guest token',
                ], 404);
            }

            return response()->json([
                'message' => 'Token verified successfully',
                'data' => [
                    'guest_id' => $guest->id,
                    'guest_name' => $guest->guest_name,
                    'domain' => $guest->domain,
                    'user_id' => $guest->user_id,
                    'attended' => $guest->attended,
                    'attended_at' => $guest->attended_at,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to verify token',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Confirm attendance from QR scan
     * POST /v1/wedding-guests/confirm-attendance
     * Requires authentication
     */
    public function confirmAttendance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'guest_token' => 'required|string',
            'acara_id' => 'required|integer',
            'scan_type' => 'required|in:qr_code,manual',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Find guest by token
            $guest = WeddingGuest::where('guest_token', $request->guest_token)->first();

            if (!$guest) {
                return response()->json([
                    'message' => 'Invalid guest token',
                ], 404);
            }

            // Ownership check: scanner must own the invitation being scanned
            if ($guest->user_id !== auth()->id()) {
                return response()->json([
                    'message' => 'Anda tidak memiliki akses untuk memindai QR undangan ini',
                ], 403);
            }

            // Check if already attended this event
            if ($guest->attended && $guest->attended_acara_id == $request->acara_id) {
                return response()->json([
                    'message' => 'Guest already marked as attended for this event',
                    'data' => [
                        'guest_name' => $guest->guest_name,
                        'attended_at' => $guest->attended_at,
                    ],
                ], 409);
            }

            // Mark guest as attended
            $guest->markAsAttended((int) $request->acara_id);

            // Create attendance scan record
            AttendanceScan::create([
                'user_id' => $guest->user_id,
                'acara_id' => $request->acara_id,
                'guest_name' => $guest->guest_name,
                'guest_identifier' => $guest->guest_token,
                'scan_type' => $request->scan_type,
                'scanned_at' => now(),
                'scanned_by' => auth()->id(),
                'notes' => $request->notes,
            ]);

            // Update or create buku_tamu record with 'hadir' status
            $bukuTamu = BukuTamu::where('user_id', $guest->user_id)
                ->where('nama', $guest->guest_name)
                ->first();

            if ($bukuTamu) {
                $bukuTamu->update([
                    'status_kehadiran' => 'hadir',
                    'is_approved' => true,
                ]);
            } else {
                // Create new buku_tamu record if not exists
                BukuTamu::create([
                    'user_id' => $guest->user_id,
                    'nama' => $guest->guest_name,
                    'status_kehadiran' => 'hadir',
                    'is_approved' => true,
                ]);
            }

            return response()->json([
                'message' => 'Attendance confirmed successfully',
                'data' => [
                    'guest_name' => $guest->guest_name,
                    'attended_at' => $guest->attended_at,
                    'acara_id' => $request->acara_id,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to confirm attendance',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get wedding guest list
     * GET /v1/wedding-guests
     * Requires authentication
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            $domain = $this->normalizeDomain((string) $request->query('domain', ''));

            if ($domain !== '') {
                $ownerUserId = $this->domainService->resolveOwnerUserIdByDomain($domain);

                if (! $ownerUserId) {
                    return response()->json([
                        'status' => false,
                        'code' => 'INVITATION_NOT_FOUND',
                        'message' => 'Undangan tidak ditemukan.',
                    ], 404);
                }

                if ((int) $ownerUserId !== (int) $userId) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Anda tidak memiliki akses ke data tamu undangan ini.',
                    ], 403);
                }
            }

            $guests = WeddingGuest::where('user_id', $userId)
                ->when($domain !== '', function ($query) use ($domain) {
                    $query->where(function ($query) use ($domain) {
                        $query->whereRaw('LOWER(domain) = ?', [$domain])
                            ->orWhereRaw('LOWER(domain) LIKE ?', ['%/'.$domain]);
                    });
                })
                ->orderBy('first_visit_at', 'desc')
                ->orderByDesc('id')
                ->get()
                ->map(fn (WeddingGuest $guest): array => $this->guestPayload($guest));

            return response()->json([
                'status' => true,
                'success' => true,
                'message' => 'Data tamu berhasil diambil.',
                'data' => $guests,
                'total' => $guests->count(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve guest list',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Create guest invitation link.
     * POST /v1/wedding-guests
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'guest_name' => ['required_without:name', 'string', 'max:255'],
            'name' => ['required_without:guest_name', 'string', 'max:255'],
        ], [
            'domain.required' => 'Domain undangan wajib diisi.',
            'guest_name.required' => 'Nama tamu wajib diisi.',
            'name.required' => 'Nama tamu wajib diisi.',
        ]);

        $domain = $this->normalizeDomain($validated['domain']);
        $ownerUserId = $this->domainService->resolveOwnerUserIdByDomain($domain);

        if (! $ownerUserId) {
            return response()->json([
                'status' => false,
                'code' => 'INVITATION_NOT_FOUND',
                'message' => 'Undangan tidak ditemukan.',
            ], 404);
        }

        if ((int) $ownerUserId !== (int) auth()->id()) {
            return response()->json([
                'status' => false,
                'message' => 'Anda tidak memiliki akses ke undangan ini.',
            ], 403);
        }

        $guestName = trim((string) ($validated['guest_name'] ?? $validated['name']));
        $guest = DB::transaction(fn (): WeddingGuest => $this->createGuestForOwner((int) $ownerUserId, $domain, $guestName, $request));

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => 'Link tamu berhasil dibuat.',
            'data' => $this->guestPayload($guest->refresh()),
        ], 201);
    }

    /**
     * Create guest invitation link from Dashboard -> Bagi Undangan.
     * POST /v1/user/invitation-guests
     */
    public function userStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required_without:guest_name', 'string', 'max:255'],
            'guest_name' => ['required_without:name', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Nama tamu wajib diisi.',
            'guest_name.required' => 'Nama tamu wajib diisi.',
        ]);

        $ownerUserId = (int) auth()->id();
        $domain = $this->normalizeDomain((string) ($validated['domain'] ?? ''));
        $domain = $domain !== '' ? $domain : $this->primaryDomainForUser($ownerUserId);

        if ($domain === '') {
            return response()->json([
                'status' => false,
                'success' => false,
                'code' => 'INVITATION_NOT_FOUND',
                'message' => 'Domain undangan aktif tidak ditemukan.',
            ], 404);
        }

        $resolvedOwnerId = $this->domainService->resolveOwnerUserIdByDomain($domain);
        if ($resolvedOwnerId && (int) $resolvedOwnerId !== $ownerUserId) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke undangan ini.',
            ], 403);
        }

        $guestName = trim((string) ($validated['name'] ?? $validated['guest_name']));
        $guest = DB::transaction(fn (): WeddingGuest => $this->createGuestForOwner($ownerUserId, $domain, $guestName, $request));

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => 'Tamu berhasil dibuat.',
            'data' => $this->guestPayload($guest->refresh()),
        ], 201);
    }

    /**
     * List guest invitation links from database.
     * GET /v1/user/invitation-guests
     */
    public function userIndex(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    /**
     * Import guest invitations through the dashboard alias.
     * POST /v1/user/invitation-guests/import
     */
    public function userImport(Request $request): JsonResponse
    {
        if (! $request->filled('domain')) {
            $request->merge(['domain' => $this->primaryDomainForUser((int) auth()->id())]);
        }

        return $this->import($request);
    }

    /**
     * Export guest invitations through the dashboard alias.
     * GET /v1/user/invitation-guests/export
     */
    public function userExport(Request $request): JsonResponse
    {
        return $this->export($request);
    }

    /**
     * List checked-in guests from database.
     * GET /v1/user/invitation-guests/attendance
     */
    public function attendance(Request $request): JsonResponse
    {
        $userId = (int) auth()->id();
        $domain = $this->normalizeDomain((string) $request->query('domain', ''));

        $guests = WeddingGuest::query()
            ->where('user_id', $userId)
            ->where('attended', true)
            ->whereNotNull('attended_at')
            ->when($domain !== '', function ($query) use ($domain) {
                $query->where(function ($query) use ($domain) {
                    $query->whereRaw('LOWER(domain) = ?', [$domain])
                        ->orWhereRaw('LOWER(domain) LIKE ?', ['%/'.$domain]);
                });
            })
            ->orderByDesc('attended_at')
            ->get()
            ->map(fn (WeddingGuest $guest): array => $this->guestPayload($guest))
            ->values();

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => 'Data tamu hadir berhasil diambil.',
            'data' => $guests,
            'total' => $guests->count(),
        ]);
    }

    /**
     * Delete guest invitation.
     * DELETE /v1/wedding-guests/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $guest = WeddingGuest::where('user_id', auth()->id())->whereKey($id)->first();

        if (! $guest) {
            return response()->json([
                'status' => false,
                'message' => 'Data tamu tidak ditemukan.',
            ], 404);
        }

        $guest->delete();

        return response()->json([
            'status' => true,
            'message' => 'Data tamu berhasil dihapus.',
        ]);
    }

    /**
     * Import guest invitations from a CSV-like file or guests array.
     * POST /v1/wedding-guests/import
     */
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'max:10240'],
            'guests' => ['nullable', 'array'],
            'guests.*.guest_name' => ['nullable', 'string', 'max:255'],
            'guests.*' => ['nullable'],
        ]);

        $domain = $this->normalizeDomain($validated['domain']);
        $ownerUserId = $this->domainService->resolveOwnerUserIdByDomain($domain);

        if (! $ownerUserId) {
            return response()->json([
                'status' => false,
                'code' => 'INVITATION_NOT_FOUND',
                'message' => 'Undangan tidak ditemukan.',
            ], 404);
        }

        if ((int) $ownerUserId !== (int) auth()->id()) {
            return response()->json([
                'status' => false,
                'message' => 'Anda tidak memiliki akses ke undangan ini.',
            ], 403);
        }

        $guestNames = collect($validated['guests'] ?? [])
            ->map(fn ($guest): string => is_array($guest)
                ? trim((string) ($guest['guest_name'] ?? $guest['name'] ?? ''))
                : trim((string) $guest))
            ->filter()
            ->values();

        if ($request->hasFile('file')) {
            $importedGuestNames = $this->guestNamesFromImportFile($request->file('file'));
            if ($importedGuestNames instanceof JsonResponse) {
                return $importedGuestNames;
            }

            $guestNames = $guestNames->merge($importedGuestNames);
        }

        if ($guestNames->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Data tamu import kosong.',
            ], 422);
        }

        $created = DB::transaction(fn () => $guestNames
            ->unique()
            ->map(fn (string $guestName): array => $this->guestPayload(
                $this->createGuestForOwner((int) $ownerUserId, $domain, $guestName, $request)
            ))
            ->values());

        return response()->json([
            'status' => true,
            'message' => 'Data tamu berhasil diimport.',
            'data' => $created,
            'total' => $created->count(),
        ], 201);
    }

    /**
     * Export guest invitations as CSV payload.
     * GET /v1/wedding-guests/export?domain=nova-yusril
     */
    public function export(Request $request): JsonResponse
    {
        $domain = $this->normalizeDomain((string) $request->query('domain', ''));
        $userId = (int) auth()->id();

        if ($domain !== '') {
            $ownerUserId = $this->domainService->resolveOwnerUserIdByDomain($domain);

            if (! $ownerUserId) {
                return response()->json([
                    'status' => false,
                    'code' => 'INVITATION_NOT_FOUND',
                    'message' => 'Undangan tidak ditemukan.',
                ], 404);
            }

            if ((int) $ownerUserId !== $userId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Anda tidak memiliki akses ke data tamu undangan ini.',
                ], 403);
            }
        }

        $guests = WeddingGuest::where('user_id', $userId)
            ->when($domain !== '', function ($query) use ($domain) {
                $query->where(function ($query) use ($domain) {
                    $query->whereRaw('LOWER(domain) = ?', [$domain])
                        ->orWhereRaw('LOWER(domain) LIKE ?', ['%/'.$domain]);
                });
            })
            ->orderBy('guest_name')
            ->get();

        $csv = "No,Nama Tamu,Kode Tamu,Domain,Link Undangan\n";
        foreach ($guests as $index => $guest) {
            $payload = $this->guestPayload($guest);
            $csv .= implode(',', [
                $index + 1,
                '"'.str_replace('"', '""', $payload['guest_name']).'"',
                $payload['guest_code'],
                $payload['domain'],
                $payload['invitation_url'],
            ])."\n";
        }

        return response()->json([
            'status' => true,
            'message' => 'Data tamu berhasil diekspor.',
            'data' => [
                'content' => base64_encode($csv),
                'filename' => 'wedding_guests_'.($domain ?: 'undangan').'_'.now()->format('Y-m-d').'.csv',
                'mime_type' => 'text/csv',
            ],
            'total' => $guests->count(),
        ]);
    }

    private function createGuestForOwner(int $ownerUserId, string $domain, string $guestName, Request $request): WeddingGuest
    {
        $guestCode = $this->uniqueGuestCode($ownerUserId, $guestName, $domain);
        $guestToken = WeddingGuest::generateUniqueToken($guestName, $domain);
        $invitationUrl = $this->invitationUrl($domain, $guestCode, $guestToken);
        $guestData = [
            'user_id' => $ownerUserId,
            'guest_name' => $guestName,
            'guest_token' => $guestToken,
            'domain' => $domain,
            'first_visit_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        if (Schema::hasColumn('wedding_guests', 'guest_code')) {
            $guestData['guest_code'] = $guestCode;
        }

        if (Schema::hasColumn('wedding_guests', 'invitation_url')) {
            $guestData['invitation_url'] = $invitationUrl;
        }

        return WeddingGuest::create($guestData);
    }

    private function guestPayload(WeddingGuest $guest): array
    {
        $guestCode = $this->guestCodeForPayload($guest);
        $domain = $this->normalizeDomain((string) $guest->domain);

        return [
            'id' => $guest->id,
            'name' => $guest->guest_name,
            'guest_name' => $guest->guest_name,
            'guest_code' => $guestCode,
            'guest_slug' => $guestCode,
            'guest_token' => $guest->guest_token,
            'domain' => $domain,
            'invitation_url' => $this->invitationUrlForPayload($guest, $domain, $guestCode),
            'attended' => (bool) $guest->attended,
            'attended_at' => $guest->attended_at,
            'attendance_status' => $guest->attended ? 'present' : 'not_present',
            'checked_in_at' => $guest->attended_at,
        ];
    }

    private function guestCodeForPayload(WeddingGuest $guest): string
    {
        if (Schema::hasColumn('wedding_guests', 'guest_code') && $guest->guest_code) {
            return (string) $guest->guest_code;
        }

        $slug = Str::slug((string) $guest->guest_name, '-');

        return $slug !== '' ? $slug : (string) $guest->guest_token;
    }

    private function invitationUrl(string $domain, string $guestCode, ?string $guestToken = null): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'https://www.sena-digital.com'), '/');
        $query = $guestToken
            ? http_build_query(['guest' => $guestToken, 'to' => $guestCode])
            : http_build_query(['to' => $guestCode]);

        return $frontendUrl.'/wedding/'.$domain.'?'.$query;
    }

    private function invitationUrlForPayload(WeddingGuest $guest, string $domain, string $guestCode): string
    {
        $storedUrl = (string) $guest->invitation_url;

        if ($storedUrl !== '' && str_contains($storedUrl, 'guest=')) {
            return $storedUrl;
        }

        return $this->invitationUrl($domain, $guestCode, (string) $guest->guest_token);
    }

    private function guestNamesFromImportFile($file): array|JsonResponse
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (in_array($extension, ['csv', 'txt'], true)) {
            $rows = array_map('str_getcsv', file($file->getRealPath()) ?: []);

            return $this->guestNamesFromRows($rows);
        }

        if ($extension === 'xlsx') {
            return $this->guestNamesFromXlsx((string) $file->getRealPath());
        }

        return response()->json([
            'status' => false,
            'message' => 'Format import belum didukung. Gunakan XLSX, CSV, atau kirim array guests.',
        ], 422);
    }

    private function guestNamesFromRows(array $rows): array
    {
        $guestNames = [];

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row[0] ?? ''));
            if ($index === 0 && in_array(Str::lower($name), ['guest_name', 'nama', 'nama tamu'], true)) {
                continue;
            }

            if ($name !== '') {
                $guestNames[] = $name;
            }
        }

        return $guestNames;
    }

    private function guestNamesFromXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = $this->xlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml') ?: '';
        $zip->close();

        if ($sheetXml === '') {
            return [];
        }

        $sheet = simplexml_load_string($sheetXml);
        if (! $sheet) {
            return [];
        }

        $rows = [];
        foreach ($sheet->sheetData->row ?? [] as $row) {
            $firstCell = $row->c[0] ?? null;
            if (! $firstCell) {
                continue;
            }

            $type = (string) ($firstCell['t'] ?? '');
            $value = (string) ($firstCell->v ?? '');
            if ($type === 's') {
                $value = $sharedStrings[(int) $value] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = (string) ($firstCell->is->t ?? '');
            }

            $rows[] = [$value];
        }

        return $this->guestNamesFromRows($rows);
    }

    private function xlsxSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml') ?: '';
        if ($xml === '') {
            return [];
        }

        $shared = simplexml_load_string($xml);
        if (! $shared) {
            return [];
        }

        $strings = [];
        foreach ($shared->si ?? [] as $si) {
            $parts = [];
            if (isset($si->t)) {
                $parts[] = (string) $si->t;
            }

            foreach ($si->r ?? [] as $run) {
                $parts[] = (string) ($run->t ?? '');
            }

            $strings[] = trim(implode('', $parts));
        }

        return $strings;
    }

    private function normalizeDomain(string $domain): string
    {
        return $this->domainService->normalizeToSlug($domain);
    }

    private function primaryDomainForUser(int $userId): string
    {
        $domain = Setting::query()
            ->where('user_id', $userId)
            ->whereNotNull('domain')
            ->orderByDesc('id')
            ->value('domain');

        return $this->normalizeDomain((string) $domain);
    }

    private function uniqueGuestCode(int $userId, string $guestName, ?string $domain = null): string
    {
        $baseCode = Str::slug($guestName, '-');
        $baseCode = $baseCode !== '' ? $baseCode : 'tamu';
        if (! Schema::hasColumn('wedding_guests', 'guest_code')) {
            return $baseCode;
        }

        $guestCode = $baseCode;
        $suffix = 2;

        while (WeddingGuest::query()
            ->where('user_id', $userId)
            ->when($domain, fn ($query) => $query->whereRaw('LOWER(domain) = ?', [strtolower($domain)]))
            ->where('guest_code', $guestCode)
            ->exists()
        ) {
            $guestCode = $baseCode.'-'.$suffix++;
        }

        return $guestCode;
    }
}
