<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Invitation;
use App\Models\JenisThemas;
use App\Models\Mempelai;
use App\Models\PaketUndangan;
use App\Models\PaymentLog;
use App\Services\AccountStatusService;
use App\Services\MidtransService;
use App\Services\PackageThemeAccessService;
use App\Services\PaymentMethodResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PackageUpgradeController extends Controller
{
    public function __construct(
        private PackageThemeAccessService $themeAccess,
        private AccountStatusService $accountStatus,
        private PaymentMethodResolver $paymentMethodResolver
    )
    {
        $this->middleware('auth:sanctum');
    }

    private function hasIsTrialColumn(): bool
    {
        return Schema::hasColumn('invitations', 'is_trial');
    }

    /**
     * Admin changes user package
     * POST /v1/admin/change-package
     */
    public function changePackage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'paket_undangan_id' => 'required|exists:paket_undangans,id',
            'extend_from_now' => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $user = User::findOrFail($validated['user_id']);
            $newPackage = PaketUndangan::findOrFail($validated['paket_undangan_id']);
            $invitation = Invitation::where('user_id', $user->id)->firstOrFail();
            $mempelai = Mempelai::where('user_id', $user->id)->first();

            $extendFromNow = $request->input('extend_from_now', false);
            $baseDate = $extendFromNow ? now() : ($invitation->domain_expires_at ?? now());
            $newExpiryAt = $baseDate->copy()->addDays($newPackage->masa_aktif);

            $previousPackageId = $invitation->paket_undangan_id;
            $previousFeatures = $invitation->package_features_snapshot ?? [];

            $updateData = [
                'paket_undangan_id' => $newPackage->id,
                'payment_status' => 'paid',
                'domain_expires_at' => $newExpiryAt,
                'package_price_snapshot' => $newPackage->price,
                'package_duration_snapshot' => $newPackage->masa_aktif,
                'package_features_snapshot' => [
                    'code' => $newPackage->code,
                    'jenis_paket' => PaketUndangan::jenisPaketFromCode($newPackage->code, $newPackage->jenis_paket),
                    'name_paket' => PaketUndangan::displayLabelFromCode($newPackage->code, $newPackage->name_paket),
                    'halaman_buku' => $newPackage->halaman_buku,
                    'kirim_wa' => $newPackage->kirim_wa,
                    'bebas_pilih_tema' => $newPackage->bebas_pilih_tema,
                    'kirim_hadiah' => $newPackage->kirim_hadiah,
                    'import_data' => $newPackage->import_data,
                    'upgraded_at' => now()->toISOString(),
                    'upgraded_by' => 'admin',
                    'previous_package_id' => $previousPackageId,
                    'previous_package_name' => $previousFeatures['name_paket'] ?? $previousFeatures['previous_package_name'] ?? $previousFeatures['name_paket'] ?? null
                ]
            ];

            if ($this->hasIsTrialColumn()) {
                $updateData['is_trial'] = false;
            }

            $invitation->update($updateData);

            if ($mempelai) {
                $mempelai->update([
                    'status' => 'Sudah Bayar',
                    'kd_status' => 'SB'
                ]);
            }

            return response()->json([
                'message' => 'Package berhasil diubah',
                'data' => [
                    'user_id' => $user->id,
                    'new_package' => $newPackage->name_paket,
                    'new_package_id' => $newPackage->id,
                    'domain_expires_at' => $newExpiryAt->format('Y-m-d H:i:s'),
                    'active_days' => $newPackage->masa_aktif
                ]
            ], 200);
        });
    }

    /**
     * Admin manually activates or changes a user's package without creating a new invoice.
     * POST /v1/admin/users/{user}/upgrade-package
     */
    public function upgradeUserPackage(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'package_code' => ['required', 'string', 'in:trial,ruby,sapphire,diamond'],
            'expired_at' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($request, $user, $validated) {
            $package = PaketUndangan::query()
                ->where('code', $validated['package_code'])
                ->first();

            if (! $package) {
                return response()->json([
                    'status' => false,
                    'message' => 'Paket tidak ditemukan.',
                ], 404);
            }

            $invitation = $this->manualUpgradeInvitationFor($user);

            if (! $invitation) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invoice pengguna tidak ditemukan.',
                ], 404);
            }

            $expiredAt = Carbon::parse($validated['expired_at'])->endOfDay();
            $previousSnapshot = is_array($invitation->package_features_snapshot)
                ? $invitation->package_features_snapshot
                : [];

            $updateData = [
                'paket_undangan_id' => $package->id,
                'payment_status' => 'paid',
                'domain_expires_at' => $expiredAt,
                'payment_confirmed_at' => now(),
                'package_features_snapshot' => array_merge($previousSnapshot, [
                    'code' => $package->code,
                    'jenis_paket' => PaketUndangan::jenisPaketFromCode($package->code, $package->jenis_paket),
                    'name_paket' => PaketUndangan::displayLabelFromCode($package->code, $package->name_paket),
                    'halaman_buku' => $package->halaman_buku,
                    'kirim_wa' => $package->kirim_wa,
                    'bebas_pilih_tema' => $package->bebas_pilih_tema,
                    'kirim_hadiah' => $package->kirim_hadiah,
                    'import_data' => $package->import_data,
                    'manual_upgrade' => true,
                    'manual_upgrade_note' => $validated['note'] ?? null,
                    'manual_upgraded_at' => now()->toISOString(),
                    'manual_upgraded_by' => $request->user()?->id,
                    'previous_package_id' => $invitation->paket_undangan_id,
                    'previous_package_code' => $invitation->paketUndangan?->code ?? ($previousSnapshot['code'] ?? null),
                    'previous_payment_status' => $invitation->payment_status,
                ]),
            ];

            foreach ([
                'package_price_snapshot' => $package->price,
                'package_duration_snapshot' => $package->masa_aktif,
            ] as $column => $value) {
                if (Schema::hasColumn('invitations', $column)) {
                    $updateData[$column] = $value;
                }
            }

            if ($this->hasIsTrialColumn()) {
                $updateData['is_trial'] = false;
            }

            $invitation->update($updateData);

            if (! $user->isAccountVerified()) {
                $user->forceFill([
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'verification_channel' => $user->verification_channel ?: 'email',
                ])->save();
            }

            Mempelai::where('user_id', $user->id)->update([
                'status' => 'Sudah Bayar',
                'kd_status' => 'SB',
            ]);

            $this->logManualPackageUpgrade($request, $user, $invitation->fresh(), $package, $validated['note'] ?? null);

            $summary = $this->accountStatus->summary($user->fresh());

            return response()->json([
                'status' => true,
                'message' => 'Paket pengguna berhasil diperbarui.',
                'data' => [
                    'user_id' => $user->id,
                    'package_code' => $summary['package_code'],
                    'package_name' => $summary['package_name'],
                    'account_status' => $summary['account_status'],
                    'payment_status' => 'confirmed',
                    'active_until' => $expiredAt->toDateString(),
                    'active_until_formatted' => $summary['active_until_formatted'],
                    'profile_status' => $summary,
                ],
            ], 200);
        });
    }

    /**
     * Get eligible packages for upgrade (excludes current)
     * GET /v1/user/eligible-packages
     */
    public function getEligiblePackages(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Prioritize paid invitation, fallback to latest invitation
        // This handles cases where there might be multiple invitation records
        $invitation = Invitation::where('user_id', $user->id)
            ->where(function ($query) {
                $query->where('payment_status', 'paid')
                      ->orWhere('payment_status', 'pending');
            })
            ->orderByRaw("CASE WHEN payment_status = 'paid' THEN 0 ELSE 1 END")
            ->orderBy('id', 'desc')
            ->first();

        $currentPackageId = $invitation?->paket_undangan_id;

        $query = PaketUndangan::query();

        if ($currentPackageId) {
            $query->where('id', '!=', $currentPackageId);
        }

        $packages = $query->orderBy('masa_aktif', 'asc')->get();

        $isTrial = true;
        $hasPendingUpgrade = false;

        if ($invitation && $this->hasIsTrialColumn()) {
            $isTrial = $invitation->is_trial ?? true;
        } elseif ($invitation) {
            $isTrial = $invitation->payment_status === 'pending';
        }

        // Check for pending upgrade
        $snapshot = $invitation?->package_features_snapshot ?? [];
        if (isset($snapshot['upgrade_initiated_at'])) {
            $initiatedAt = Carbon::parse($snapshot['upgrade_initiated_at']);
            if ($initiatedAt->diffInMinutes(now()) < 30 && $invitation->payment_status === 'pending') {
                $hasPendingUpgrade = true;
            }
        }

        return response()->json([
            'data' => $packages,
            'current_package_id' => $currentPackageId,
            'current_package_name' => $snapshot['name_paket'] ?? null,
            'current_package_name_display' => PaketUndangan::displayLabelFromCode(
                $snapshot['code'] ?? null,
                $snapshot['name_paket'] ?? null
            ),
            'current_package_tier' => PaketUndangan::tierCode(
                $snapshot['name_paket'] ?? null,
                $snapshot['code'] ?? null
            ),
            'invitation_id' => $invitation?->id,
            'payment_status' => $invitation?->payment_status,
            'is_trial' => $isTrial,
            'has_pending_upgrade' => $hasPendingUpgrade
        ], 200)->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    /**
     * GET /v1/user/packages
     */
    public function packages(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentPackage = $this->currentPackageFor($user);
        $lastPackage = $this->lastPackageFor($user);
        $currentOrder = $currentPackage ? $this->packageOrderValue($currentPackage) : null;
        $subscriptionStatus = $currentPackage ? 'active' : ($lastPackage ? 'expired' : 'none');

        $packages = $this->orderedPackages()->map(function (PaketUndangan $package) use ($user, $currentPackage, $lastPackage, $currentOrder, $subscriptionStatus) {
            $order = $this->packageOrderValue($package);
            $isCurrent = $currentPackage && (int) $currentPackage->id === (int) $package->id;
            $isLastPackage = $lastPackage && (int) $lastPackage->id === (int) $package->id;
            [$canSelect, $action, $disabledReason] = $this->packageSelectionState(
                $user,
                $package,
                $currentPackage,
                $currentOrder,
                $subscriptionStatus
            );

            return [
                'id' => $package->id,
                'name' => $this->packageName($package),
                'slug' => $this->packageSlug($package),
                'price' => $package->price,
                'features' => $this->packageFeatures($package),
                'image' => $this->packageImage($package),
                'order' => $order,
                'rank' => $order,
                'is_current' => $isCurrent,
                'is_last_package' => $isLastPackage,
                'subscription_status' => $subscriptionStatus,
                'can_select' => $canSelect,
                'action' => $action,
                'disabled_reason' => $disabledReason,
                'can_upgrade' => $subscriptionStatus === 'active' && $currentOrder !== null && $order > $currentOrder,
                'can_downgrade' => $subscriptionStatus === 'active' && $currentOrder !== null && $order < $currentOrder,
            ];
        })->values();

        return response()->json($packages);
    }

    /**
     * POST /v1/user/package-upgrade
     */
    public function packageUpgrade(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package_id' => ['required', 'integer', 'exists:paket_undangans,id'],
        ]);

        $user = $request->user();
        $targetPackage = PaketUndangan::findOrFail($validated['package_id']);
        $currentPackage = $this->currentPackageFor($user);
        $lastPackage = $this->lastPackageFor($user);
        $subscriptionStatus = $currentPackage ? 'active' : ($lastPackage ? 'expired' : 'none');

        if (! $currentPackage && ! $lastPackage) {
            return response()->json([
                'success' => false,
                'message' => 'Paket aktif tidak ditemukan.',
            ], 404);
        }

        [$canSelect, $action, $disabledReason] = $this->packageSelectionState(
            $user,
            $targetPackage,
            $currentPackage,
            $currentPackage ? $this->packageOrderValue($currentPackage) : null,
            $subscriptionStatus
        );

        $packageBefore = $currentPackage ?? $lastPackage;

        if (! $canSelect) {
            return response()->json([
                'success' => false,
                'message' => $disabledReason ?? 'Paket sedang aktif.',
                'package_before' => $packageBefore ? $this->publicPackagePayload($packageBefore) : null,
                'package_after' => $this->publicPackagePayload($targetPackage),
                'subscription_status' => $subscriptionStatus,
                'action' => $action,
            ], 422);
        }

        $paymentMethod = $this->paymentMethodResolver->activeMethod();

        if ($paymentMethod === PaymentMethodResolver::MANUAL) {
            return DB::transaction(function () use ($request, $user, $packageBefore, $targetPackage, $paymentMethod, $subscriptionStatus, $action) {
                $invoice = $this->createPackageUpgradeInvoice($user, $packageBefore, $targetPackage, $paymentMethod, $action);

                PaymentLog::create([
                    'user_id' => $user->id,
                    'invitation_id' => $invoice->id,
                    'order_id' => $invoice->kode_pemesanan,
                    'event_type' => 'token_request',
                    'transaction_status' => 'pending',
                    'payment_type' => $paymentMethod,
                    'gross_amount' => $invoice->package_price_snapshot,
                    'request_payload' => json_encode(['package_id' => $targetPackage->id]),
                    'response_payload' => json_encode([
                        'manual_payment' => $this->paymentMethodResolver->manualPaymentPayload(),
                    ]),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'notes' => 'Invoice manual upgrade paket dibuat.',
                ]);

                return response()->json($this->packageUpgradeResponse(
                    $packageBefore,
                    $targetPackage,
                    $paymentMethod,
                    $invoice->payment_status,
                    [
                        'invoice_id' => $invoice->id,
                        'invoice_code' => $invoice->kode_pemesanan,
                        'manual_payment' => $this->paymentMethodResolver->manualPaymentPayload(),
                        'subscription_status' => $subscriptionStatus,
                        'action' => $action,
                    ]
                ), 201);
            });
        }

        if (! $this->paymentMethodResolver->midtransConfigured()) {
            return response()->json([
                'success' => false,
                'code' => 'PAYMENT_METHOD_UNAVAILABLE',
                'message' => 'Metode pembayaran belum tersedia. Silakan hubungi admin.',
                'data' => $this->paymentMethodResolver->activePayload(),
            ], 409);
        }

        try {
            return DB::transaction(function () use ($request, $user, $packageBefore, $targetPackage, $paymentMethod, $subscriptionStatus, $action) {
                $invoice = $this->createPackageUpgradeInvoice($user, $packageBefore, $targetPackage, $paymentMethod, $action);
                $orderId = $invoice->kode_pemesanan;
                $grossAmount = (float) $targetPackage->price;
                $snapToken = $this->createMidtransSnapToken($user, $invoice, $targetPackage, $orderId, $grossAmount);
                $expiresAt = now()->addHours((int) config('midtrans.token_expiry_hours', 24));

                $invoice->update([
                    'order_id' => $orderId,
                    'payment_status' => 'pending',
                    'payment_method' => PaymentMethodResolver::MIDTRANS,
                ]);

                PaymentLog::create([
                    'user_id' => $user->id,
                    'invitation_id' => $invoice->id,
                    'order_id' => $orderId,
                    'event_type' => 'token_request',
                    'transaction_status' => 'pending',
                    'payment_type' => PaymentMethodResolver::MIDTRANS,
                    'gross_amount' => $grossAmount,
                    'request_payload' => json_encode(['package_id' => $targetPackage->id]),
                    'response_payload' => json_encode([
                        'snap_token' => $snapToken,
                        'expires_at' => $expiresAt->toIso8601String(),
                    ]),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'notes' => 'Transaksi Midtrans upgrade paket dibuat.',
                ]);

                return response()->json($this->packageUpgradeResponse(
                    $packageBefore,
                    $targetPackage,
                    PaymentMethodResolver::MIDTRANS,
                    $invoice->payment_status,
                    [
                        'invoice_id' => $invoice->id,
                        'invoice_code' => $invoice->kode_pemesanan,
                        'order_id' => $orderId,
                        'snap_token' => $snapToken,
                        'expires_at' => $expiresAt->toIso8601String(),
                        'subscription_status' => $subscriptionStatus,
                        'action' => $action,
                    ]
                ), 201);
            });
        } catch (\RuntimeException $e) {
            Log::error('Package upgrade Midtrans transaction failed', [
                'user_id' => $user->id,
                'target_package_id' => $targetPackage->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * User initiates package upgrade
     * POST /v1/user/upgrade-package
     *
     * FIXED: Now updates existing invitation instead of creating new one
     * to prevent duplicate records and dashboard 403 errors
     */
    public function initiateUpgrade(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paket_undangan_id' => 'nullable|required_without_all:target_package_id,target_package_code,target_package,theme_slug|exists:paket_undangans,id',
            'target_package_id' => 'nullable|required_without_all:paket_undangan_id,target_package_code,target_package,theme_slug|exists:paket_undangans,id',
            'target_package_code' => 'nullable|required_without_all:paket_undangan_id,target_package_id,target_package,theme_slug|string',
            'target_package' => 'nullable|required_without_all:paket_undangan_id,target_package_id,target_package_code,theme_slug|string|in:trial,ruby,sapphire,diamond',
            'theme_slug' => 'nullable|string|exists:jenis_themas,slug',
        ]);

        return DB::transaction(function () use ($validated) {
            $user = auth()->user();
            $accountSummary = $this->accountStatus->summary($user);

            if ($accountSummary['account_status'] !== AccountStatusService::STATUS_ACTIVE) {
                return response()->json([
                    'message' => 'Akun harus aktif sebelum upgrade paket.',
                    'data' => $accountSummary,
                ], 403);
            }

            $theme = isset($validated['theme_slug'])
                ? JenisThemas::query()->where('slug', $validated['theme_slug'])->first()
                : null;

            $targetPackageFromTheme = $theme
                ? $this->themeAccess->requiredPackageCodeForTheme($theme)
                : null;

            $targetIdentifier = $validated['target_package']
                ?? $validated['target_package_code']
                ?? $validated['target_package_id']
                ?? $validated['paket_undangan_id']
                ?? $targetPackageFromTheme;
            $newPackage = $this->themeAccess->packageFromCodeOrId($targetIdentifier);
            $currentPackage = $this->themeAccess->packageForUser($user);

            if (! $newPackage || ! $currentPackage) {
                return response()->json([
                    'message' => 'Paket tidak ditemukan.',
                ], 404);
            }

            if ($theme && $targetPackageFromTheme && $this->themeAccess->packageRank($newPackage) < $this->packageRankByCode($targetPackageFromTheme)) {
                return response()->json([
                    'message' => 'Target paket tidak sesuai dengan kebutuhan tema.',
                ], 422);
            }

            if (! $this->themeAccess->isHigherPackage($newPackage, $currentPackage)) {
                return response()->json([
                    'message' => $theme ? 'Paket Anda sudah mencakup tema ini.' : 'Paket tujuan harus lebih tinggi dari paket aktif saat ini.',
                    'data' => [
                        'current_package' => $this->packagePayload($currentPackage),
                        'target_package' => $this->packagePayload($newPackage),
                    ],
                ], 422);
            }

            $pendingUpgrade = $this->pendingUpgradeForUserAndTarget($user, $newPackage);
            if ($pendingUpgrade) {
                return response()->json([
                    'status' => true,
                    'message' => 'Invoice upgrade paket berhasil dibuat.',
                    'data' => $this->upgradeResponsePayload($pendingUpgrade, $newPackage),
                ], 200);
            }

            $activeInvitation = Invitation::where('user_id', $user->id)
                ->where('payment_status', 'paid')
                ->orderByDesc('id')
                ->first();

            if (! $activeInvitation) {
                return response()->json([
                    'message' => 'Paket aktif tidak ditemukan.',
                ], 404);
            }

            $newInvoiceNumber = '#UPG-' . now()->format('YmdHis') . '-' . $user->id . '-' . $newPackage->id;

            $createData = [
                'user_id' => $user->id,
                'paket_undangan_id' => $newPackage->id,
                'kode_pemesanan' => $newInvoiceNumber,
                'status' => $activeInvitation->status,
                'payment_status' => 'pending',
                'domain_expires_at' => null,
                'payment_confirmed_at' => null,
                'package_price_snapshot' => $newPackage->price,
                'package_duration_snapshot' => $newPackage->masa_aktif,
                'package_features_snapshot' => [
                    'invoice_type' => 'package_upgrade',
                    'code' => $newPackage->code,
                    'jenis_paket' => PaketUndangan::jenisPaketFromCode($newPackage->code, $newPackage->jenis_paket),
                    'name_paket' => PaketUndangan::displayLabelFromCode($newPackage->code, $newPackage->name_paket),
                    'halaman_buku' => $newPackage->halaman_buku,
                    'kirim_wa' => $newPackage->kirim_wa,
                    'bebas_pilih_tema' => $newPackage->bebas_pilih_tema,
                    'kirim_hadiah' => $newPackage->kirim_hadiah,
                    'import_data' => $newPackage->import_data,
                    'upgrade_initiated_at' => now()->toISOString(),
                    'theme_slug' => $validated['theme_slug'] ?? null,
                    'target_package' => $newPackage->code,
                    'target_package_code' => $newPackage->code,
                    'previous_invitation_id' => $activeInvitation->id,
                    'previous_package_id' => $currentPackage->id,
                    'previous_package_code' => $currentPackage->code,
                    'previous_package_name' => PaketUndangan::displayLabelFromCode($currentPackage->code, $currentPackage->name_paket),
                    'original_status' => $activeInvitation->status,
                    'original_payment_status' => $activeInvitation->payment_status,
                ]
            ];

            if ($this->hasIsTrialColumn()) {
                $createData['is_trial'] = false;
            }

            $invoice = Invitation::create($createData);

            return response()->json([
                'status' => true,
                'message' => 'Invoice upgrade paket berhasil dibuat.',
                'data' => $this->upgradeResponsePayload($invoice, $newPackage, $currentPackage),
            ], 201);
        });
    }

    private function pendingUpgradeForUserAndTarget(User $user, PaketUndangan $targetPackage): ?Invitation
    {
        return Invitation::with('paketUndangan')
            ->where('user_id', $user->id)
            ->where('payment_status', 'pending')
            ->where('paket_undangan_id', $targetPackage->id)
            ->where('package_features_snapshot->invoice_type', 'package_upgrade')
            ->orderByDesc('id')
            ->first();
    }

    private function manualUpgradeInvitationFor(User $user): ?Invitation
    {
        return Invitation::with('paketUndangan')
            ->where('user_id', $user->id)
            ->orderByRaw("
                CASE
                    WHEN LOWER(COALESCE(payment_status, status, '')) IN ('pending', 'belum selesai', 'unpaid', 'menunggu pembayaran') THEN 0
                    WHEN payment_status IN ('paid', 'confirmed') THEN 1
                    ELSE 2
                END
            ")
            ->orderByDesc('id')
            ->first();
    }

    private function logManualPackageUpgrade(
        Request $request,
        User $user,
        Invitation $invitation,
        PaketUndangan $package,
        ?string $note
    ): void {
        if (! Schema::hasTable('payment_logs')) {
            return;
        }

        PaymentLog::create([
            'user_id' => $user->id,
            'invitation_id' => $invitation->id,
            'order_id' => $invitation->kode_pemesanan,
            'event_type' => 'webhook_processed',
            'transaction_status' => 'settlement',
            'payment_type' => 'manual_admin_upgrade',
            'gross_amount' => $invitation->package_price_snapshot,
            'request_payload' => json_encode([
                'package_code' => $package->code,
                'expired_at' => $invitation->domain_expires_at?->toDateString(),
                'note' => $note,
            ]),
            'response_payload' => json_encode([
                'payment_status' => $invitation->payment_status,
                'payment_confirmed_at' => $invitation->payment_confirmed_at?->toISOString(),
                'domain_expires_at' => $invitation->domain_expires_at?->toDateString(),
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'notes' => $note ?: 'Upgrade manual oleh admin',
        ]);
    }

    private function upgradeResponsePayload(
        Invitation $invoice,
        PaketUndangan $targetPackage,
        ?PaketUndangan $currentPackage = null
    ): array {
        return [
            'invoice_id' => $invoice->id,
            'invoice_code' => $invoice->kode_pemesanan,
            'target_package' => $targetPackage->code,
            'target_package_detail' => $this->packagePayload($targetPackage),
            'current_package' => $currentPackage ? $this->packagePayload($currentPackage) : null,
            'theme_slug' => $invoice->package_features_snapshot['theme_slug'] ?? null,
            'payment_status' => $invoice->payment_status,
            'amount' => (float) ($invoice->package_price_snapshot ?? 0),
            'redirect_url' => '/dashboard/payment-pending',
            'invoice' => $this->invoicePayload($invoice),
        ];
    }

    private function packageRankByCode(string $code): int
    {
        $package = $this->themeAccess->packageFromCodeOrId($code);

        return $this->themeAccess->packageRank($package);
    }

    private function packagePayload(PaketUndangan $package): array
    {
        return [
            'id' => $package->id,
            'code' => $package->code,
            'name' => PaketUndangan::displayLabelFromCode($package->code, $package->name_paket),
            'price' => $package->price,
            'duration_days' => $package->masa_aktif,
        ];
    }

    private function orderedPackages()
    {
        $query = PaketUndangan::query();
        $orderColumn = $this->packageOrderColumn();

        if ($orderColumn) {
            return $query->orderBy($orderColumn)->orderBy('id')->get();
        }

        return $query->orderBy('price')->orderBy('id')->get();
    }

    private function currentPackageFor(User $user): ?PaketUndangan
    {
        return $this->activeSubscriptionFor($user)?->paketUndangan;
    }

    private function lastPackageFor(User $user): ?PaketUndangan
    {
        return $this->lastPaidSubscriptionFor($user)?->paketUndangan;
    }

    private function activeSubscriptionFor(User $user): ?Invitation
    {
        return Invitation::with('paketUndangan')
            ->where('user_id', $user->id)
            ->whereIn('payment_status', ['paid', 'confirmed'])
            ->whereNotNull('domain_expires_at')
            ->where('domain_expires_at', '>=', now())
            ->orderByDesc('domain_expires_at')
            ->orderByDesc('id')
            ->first();
    }

    private function lastPaidSubscriptionFor(User $user): ?Invitation
    {
        return Invitation::with('paketUndangan')
            ->where('user_id', $user->id)
            ->whereIn('payment_status', ['paid', 'confirmed'])
            ->orderByDesc('domain_expires_at')
            ->orderByDesc('id')
            ->first();
    }

    private function packageSelectionState(
        User $user,
        PaketUndangan $package,
        ?PaketUndangan $currentPackage,
        int|float|null $currentOrder,
        string $subscriptionStatus
    ): array {
        if ($subscriptionStatus !== 'active' || ! $currentPackage || $currentOrder === null) {
            $lastPackage = $this->lastPackageFor($user);
            $action = $lastPackage && (int) $lastPackage->id === (int) $package->id ? 'renew' : 'subscribe';

            return [true, $action, null];
        }

        $order = $this->packageOrderValue($package);

        if ((int) $currentPackage->id === (int) $package->id) {
            return [false, 'current', null];
        }

        if ($order < $currentOrder) {
            return [true, 'downgrade', null];
        }

        return [true, 'upgrade', null];
    }

    private function packageOrderValue(PaketUndangan $package): int|float
    {
        $orderColumn = $this->packageOrderColumn();

        if ($orderColumn && $package->getAttribute($orderColumn) !== null) {
            return (int) $package->getAttribute($orderColumn);
        }

        $ids = $this->orderedPackages()->pluck('id')->values();
        $index = $ids->search($package->id);

        return $index === false ? (float) $package->price : $index + 1;
    }

    private function packageOrderColumn(): ?string
    {
        foreach (['order', 'sort_order', 'display_order', 'position'] as $column) {
            if (Schema::hasColumn('paket_undangans', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function packageName(PaketUndangan $package): ?string
    {
        return $package->getRawOriginal('name_paket')
            ?? $package->getRawOriginal('jenis_paket')
            ?? $package->name_paket;
    }

    private function packageSlug(PaketUndangan $package): ?string
    {
        return $package->getAttribute('slug')
            ?? $package->getAttribute('code')
            ?? Str::slug((string) $this->packageName($package));
    }

    private function packageImage(PaketUndangan $package): ?string
    {
        foreach (['image', 'photo', 'thumbnail', 'icon', 'gambar'] as $column) {
            if (Schema::hasColumn('paket_undangans', $column)) {
                return $package->getAttribute($column);
            }
        }

        return null;
    }

    private function packageFeatures(PaketUndangan $package): array
    {
        $excluded = array_filter([
            'id',
            'code',
            'slug',
            'jenis_paket',
            'name_paket',
            'price',
            'masa_aktif',
            'created_at',
            'updated_at',
            $this->packageOrderColumn(),
        ]);

        return collect(Schema::getColumnListing('paket_undangans'))
            ->reject(fn (string $column) => in_array($column, $excluded, true))
            ->reject(fn (string $column) => in_array($column, ['image', 'photo', 'thumbnail', 'icon', 'gambar'], true))
            ->mapWithKeys(fn (string $column) => [$column => $package->getAttribute($column)])
            ->all();
    }

    private function publicPackagePayload(PaketUndangan $package): array
    {
        return [
            'id' => $package->id,
            'name' => $this->packageName($package),
            'slug' => $this->packageSlug($package),
            'price' => $package->price,
            'features' => $this->packageFeatures($package),
            'image' => $this->packageImage($package),
            'order' => $this->packageOrderValue($package),
            'rank' => $this->packageOrderValue($package),
        ];
    }

    private function packageUpgradeResponse(
        PaketUndangan $packageBefore,
        PaketUndangan $packageAfter,
        string $paymentMethod,
        string $paymentStatus,
        array $extra = []
    ): array {
        return array_merge([
            'success' => true,
            'package_before' => $this->publicPackagePayload($packageBefore),
            'package_after' => $this->publicPackagePayload($packageAfter),
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
        ], $extra);
    }

    private function createPackageUpgradeInvoice(
        User $user,
        ?PaketUndangan $currentPackage,
        PaketUndangan $targetPackage,
        string $paymentMethod,
        string $changeType
    ): Invitation {
        $baseInvitation = $this->activeSubscriptionFor($user)
            ?? $this->lastPaidSubscriptionFor($user);

        return Invitation::create([
            'user_id' => $user->id,
            'paket_undangan_id' => $targetPackage->id,
            'kode_pemesanan' => '#UPG-' . now()->format('YmdHis') . '-' . $user->id . '-' . $targetPackage->id,
            'status' => $baseInvitation?->status ?? 'pending',
            'payment_status' => 'pending',
            'payment_method' => $paymentMethod,
            'domain_expires_at' => null,
            'payment_confirmed_at' => null,
            'package_price_snapshot' => $targetPackage->price,
            'package_duration_snapshot' => $targetPackage->masa_aktif,
            'package_features_snapshot' => [
                'invoice_type' => 'package_upgrade',
                'package_id' => $targetPackage->id,
                'target_package_id' => $targetPackage->id,
                'change_type' => $changeType,
                'package_slug' => $this->packageSlug($targetPackage),
                'name_paket' => $this->packageName($targetPackage),
                'features' => $this->packageFeatures($targetPackage),
                'upgrade_initiated_at' => now()->toISOString(),
                'previous_invitation_id' => $baseInvitation?->id,
                'previous_package_id' => $currentPackage?->id,
                'previous_package_slug' => $currentPackage ? $this->packageSlug($currentPackage) : null,
                'previous_package_name' => $currentPackage ? $this->packageName($currentPackage) : null,
                'original_status' => $baseInvitation?->status,
                'original_payment_status' => $baseInvitation?->payment_status,
            ],
        ]);
    }

    private function createMidtransSnapToken(
        User $user,
        Invitation $invoice,
        PaketUndangan $targetPackage,
        string $orderId,
        float $grossAmount
    ): string {
        $customerName = trim((string) $user->name);
        if ($customerName === '') {
            $customerName = trim((string) strtok((string) $user->email, '@')) ?: 'Pelanggan';
        }

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => preg_split('/\s+/', $customerName, 2)[0] ?? $customerName,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
            ],
            'item_details' => [[
                'id' => 'paket-' . $targetPackage->id,
                'name' => $this->packageName($targetPackage) ?? 'Package',
                'price' => $grossAmount,
                'quantity' => 1,
            ]],
            'custom_field1' => $orderId,
            'custom_field2' => 'package_upgrade',
            'custom_field3' => (string) $invoice->id,
        ];

        $midtrans = app()->bound(MidtransService::class)
            ? app(MidtransService::class)
            : new MidtransService($user->id);

        return $midtrans->createTransaction($params);
    }

    private function invoicePayload(Invitation $invoice): array
    {
        $invoice->loadMissing('paketUndangan');

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->kode_pemesanan,
            'status' => $invoice->payment_status,
            'payment_status' => $invoice->payment_status,
            'amount' => $invoice->package_price_snapshot,
            'package_id' => $invoice->paket_undangan_id,
            'package_code' => $invoice->paketUndangan?->code,
            'package_name' => PaketUndangan::displayLabelFromCode(
                $invoice->paketUndangan?->code,
                $invoice->paketUndangan?->name_paket
            ),
            'created_at' => $invoice->created_at,
        ];
    }
}
