<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\PaketUndangan;
use App\Models\PaymentLog;
use App\Models\User;
use Carbon\Carbon;

class AccountStatusService
{
    public const STATUS_UNVERIFIED = 'unverified';
    public const STATUS_PAYMENT_SELECTION = 'verified_no_invoice';
    public const STATUS_ONBOARDING = self::STATUS_PAYMENT_SELECTION;
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';

    public function summary(User $user): array
    {
        $user = $user->fresh() ?? $user;
        $invitation = $this->resolveInvoiceForUser($user);
        $pendingInvoice = $this->resolvePendingInvoiceForUser($user);
        $package = $invitation?->paketUndangan;
        $snapshot = is_array($invitation?->package_features_snapshot)
            ? $invitation->package_features_snapshot
            : [];

        $paymentStatus = $this->normalizePaymentStatus($invitation?->payment_status ?? $invitation?->status);
        $pendingPaymentStatus = $this->normalizePaymentStatus($pendingInvoice?->payment_status ?? $pendingInvoice?->status);
        $isVerified = $user->isAccountVerified();
        $isProfileComplete = trim((string) $user->name) !== '';
        $hasInvoice = $invitation !== null;
        $hasPendingInvoice = $pendingInvoice !== null && $pendingPaymentStatus === 'pending';
        $selectedInvoiceIsPending = $hasInvoice && $paymentStatus === 'pending';
        $activeUntil = $invitation?->domain_expires_at;
        $hasActiveEntitlement = $invitation !== null
            && (in_array($paymentStatus, ['paid', 'confirmed'], true) || $invitation->payment_confirmed_at !== null);
        $isExpired = $hasInvoice && (
            $paymentStatus === 'expired'
            || ($hasActiveEntitlement && $activeUntil ? now()->greaterThan($activeUntil) : false)
        );
        $isPaymentConfirmed = $hasActiveEntitlement;

        $accountStatus = match (true) {
            ! $isVerified => self::STATUS_UNVERIFIED,
            ! $hasInvoice => self::STATUS_PAYMENT_SELECTION,
            $selectedInvoiceIsPending => self::STATUS_PENDING_PAYMENT,
            $isPaymentConfirmed && $isExpired => self::STATUS_EXPIRED,
            $isPaymentConfirmed => self::STATUS_ACTIVE,
            default => self::STATUS_PAYMENT_SELECTION,
        };

        $packageCode = $package?->code
            ?? $snapshot['code']
            ?? PaketUndangan::tierCode($snapshot['name_paket'] ?? $snapshot['jenis_paket'] ?? null);

        $packageName = PaketUndangan::displayLabelFromCode(
            $packageCode,
            $snapshot['name_paket'] ?? $package?->name_paket
        );

        $canUseFeatures = $accountStatus === self::STATUS_ACTIVE;
        $currentPackage = $canUseFeatures
            ? $this->packagePayload($package, $packageCode, $packageName)
            : null;
        $selectedPackage = $this->packagePayload($package, $packageCode, $packageName);

        return [
            'name' => $user->name,
            'is_verified' => $isVerified,
            'account_status' => $accountStatus,
            'payment_status' => $paymentStatus,
            'subscription_status' => $accountStatus,
            'has_invoice' => $hasInvoice,
            'has_pending_invoice' => $hasPendingInvoice,
            'invoice_id' => $invitation?->id,
            'invoice_code' => $invitation?->kode_pemesanan,
            'kode_pemesanan' => $invitation?->kode_pemesanan ?? $user->kode_pemesanan,
            'payment_requirement' => $this->paymentRequirement($accountStatus, $pendingInvoice),
            'pending_invoice' => $this->invoicePayload($pendingInvoice),
            'payment_options' => $this->paymentOptionsPayload($accountStatus, $pendingInvoice),
            'current_package' => $currentPackage,
            'active_package' => $currentPackage,
            'selected_package' => $canUseFeatures ? null : $selectedPackage,
            'target_package' => $canUseFeatures ? null : $selectedPackage,
            'package_name' => $canUseFeatures ? $packageName : null,
            'package_code' => $canUseFeatures ? $packageCode : null,
            'selected_package_name' => $canUseFeatures ? null : $packageName,
            'selected_package_code' => $canUseFeatures ? null : $packageCode,
            'active_until' => $canUseFeatures ? $activeUntil : null,
            'active_until_formatted' => $canUseFeatures ? $this->formatDate($activeUntil) : null,
            'expired_at_formatted' => $canUseFeatures ? $this->formatDate($activeUntil) : null,
            'tanggal_expired_formatted' => $canUseFeatures ? $this->formatDate($activeUntil) : null,
            'remaining_days' => $canUseFeatures && $activeUntil ? max(0, now()->diffInDays($activeUntil, false)) : null,
            'is_payment_confirmed' => $isPaymentConfirmed,
            'is_expired' => $isExpired,
            'is_profile_complete' => $isProfileComplete,
            'profile_incomplete' => ! $isProfileComplete,
            'profile_completion_required' => ! $isProfileComplete,
            'next_step' => $this->nextStep($accountStatus),
            'redirect_url' => $this->redirectUrl($accountStatus),
            'feature_access' => [
                'input_undangan' => $canUseFeatures,
                'mempelai' => $canUseFeatures,
                'acara' => $canUseFeatures,
                'gallery' => $canUseFeatures,
                'musik' => $canUseFeatures,
                'rekening' => $canUseFeatures,
                'cerita' => $canUseFeatures,
                'quote' => $canUseFeatures,
                'bagi_undangan' => $canUseFeatures,
            ],
        ];
    }

    private function nextStep(string $accountStatus): string
    {
        return match ($accountStatus) {
            self::STATUS_UNVERIFIED => 'verify-account',
            self::STATUS_PAYMENT_SELECTION => 'select-package-payment-method',
            self::STATUS_PENDING_PAYMENT => 'payment-pending',
            self::STATUS_ACTIVE => 'dashboard',
            self::STATUS_EXPIRED => 'account-expired-renewal',
            default => 'select-package-payment-method',
        };
    }

    private function redirectUrl(string $accountStatus): string
    {
        return match ($accountStatus) {
            self::STATUS_UNVERIFIED => '/verify-account',
            self::STATUS_PAYMENT_SELECTION => '/pilih-paket',
            self::STATUS_PENDING_PAYMENT => '/dashboard/payment-pending',
            self::STATUS_ACTIVE => '/dashboard',
            self::STATUS_EXPIRED => '/account-expired/renewal',
            default => '/pilih-paket',
        };
    }

    private function formatDate($date): ?string
    {
        if (! $date) {
            return null;
        }

        return $date instanceof \DateTimeInterface
            ? $date->format('d/m/Y')
            : date('d/m/Y', strtotime((string) $date));
    }

    private function normalizePaymentStatus(?string $status): ?string
    {
        if ($status === null || trim($status) === '') {
            return null;
        }

        $normalized = strtolower(trim($status));

        return in_array($normalized, ['pending', 'belum selesai', 'unpaid', 'menunggu pembayaran'], true)
            ? 'pending'
            : $normalized;
    }

    private function resolveInvoiceForUser(User $user): ?Invitation
    {
        return Invitation::with('paketUndangan')
            ->where('user_id', $user->id)
            ->orderByRaw("
                CASE
                    WHEN payment_status IN ('paid', 'confirmed') THEN 0
                    WHEN LOWER(COALESCE(payment_status, status, '')) IN ('pending', 'belum selesai', 'unpaid', 'menunggu pembayaran') THEN 1
                    WHEN payment_status = 'expired' THEN 2
                    ELSE 3
                END
            ")
            ->orderByDesc('id')
            ->first();
    }

    private function resolvePendingInvoiceForUser(User $user): ?Invitation
    {
        return Invitation::with('paketUndangan')
            ->where('user_id', $user->id)
            ->whereRaw("LOWER(COALESCE(payment_status, status, '')) IN ('pending', 'belum selesai', 'unpaid', 'menunggu pembayaran')")
            ->orderByDesc('id')
            ->first();
    }

    private function paymentRequirement(string $accountStatus, ?Invitation $pendingInvoice): ?string
    {
        if ($pendingInvoice) {
            $snapshot = is_array($pendingInvoice->package_features_snapshot)
                ? $pendingInvoice->package_features_snapshot
                : [];

            if (($snapshot['invoice_type'] ?? null) === 'package_upgrade') {
                return match ($snapshot['change_type'] ?? null) {
                    'renew' => 'renewal_payment',
                    'upgrade', 'downgrade' => 'upgrade_payment',
                    default => 'upgrade_payment',
                };
            }

            return 'initial_payment';
        }

        return match ($accountStatus) {
            self::STATUS_PAYMENT_SELECTION => 'initial_payment',
            self::STATUS_EXPIRED => 'renewal_payment',
            default => null,
        };
    }

    private function invoicePayload(?Invitation $invoice): ?array
    {
        if (! $invoice) {
            return null;
        }

        $invoice->loadMissing('paketUndangan');
        $snapshot = is_array($invoice->package_features_snapshot)
            ? $invoice->package_features_snapshot
            : [];
        $packageCode = $invoice->paketUndangan?->code
            ?? $snapshot['code']
            ?? PaketUndangan::tierCode($snapshot['name_paket'] ?? $snapshot['jenis_paket'] ?? null);
        $paymentMethod = $this->normalizePaymentMethod($invoice->payment_method);
        $midtransTransaction = $paymentMethod === 'midtrans'
            ? $this->activeMidtransTransactionFor($invoice)
            : null;
        $manualPayment = $paymentMethod === 'manual'
            ? app(PaymentMethodResolver::class)->manualPaymentPayload()
            : null;

        return [
            'id' => $invoice->id,
            'invoice_code' => $invoice->kode_pemesanan,
            'kode_pemesanan' => $invoice->kode_pemesanan,
            'order_id' => $invoice->order_id,
            'payment_status' => $this->normalizePaymentStatus($invoice->payment_status ?? $invoice->status),
            'payment_method' => $paymentMethod,
            'provider' => $paymentMethod,
            'payment_provider' => $paymentMethod,
            'is_payable' => $this->normalizePaymentStatus($invoice->payment_status ?? $invoice->status) === 'pending',
            'amount' => $invoice->package_price_snapshot,
            ...$this->pricingResponseFields($invoice),
            'pricing' => $this->pricingPayload($invoice),
            'invoice_type' => $snapshot['invoice_type'] ?? null,
            'change_type' => $snapshot['change_type'] ?? null,
            'resume' => $this->resumePayload($invoice, $paymentMethod, $midtransTransaction, $manualPayment),
            'midtrans' => $midtransTransaction,
            'manual_payment' => $manualPayment,
            'payment_options' => $paymentMethod ? null : app(PaymentMethodResolver::class)->activePayload(),
            'package' => $this->packagePayload(
                $invoice->paketUndangan,
                $packageCode,
                PaketUndangan::displayLabelFromCode(
                    $packageCode,
                    $snapshot['name_paket'] ?? $invoice->paketUndangan?->name_paket
                )
            ),
        ];
    }

    private function paymentOptionsPayload(string $accountStatus, ?Invitation $pendingInvoice): ?array
    {
        if (! in_array($accountStatus, [self::STATUS_PAYMENT_SELECTION, self::STATUS_PENDING_PAYMENT], true)) {
            return null;
        }

        if ($pendingInvoice && $this->normalizePaymentMethod($pendingInvoice->payment_method)) {
            return null;
        }

        return app(PaymentMethodResolver::class)->activePayload();
    }

    private function normalizePaymentMethod(?string $method): ?string
    {
        if ($method === null || trim($method) === '') {
            return null;
        }

        $normalized = strtolower(trim($method));

        return match (true) {
            str_contains($normalized, 'midtrans') => 'midtrans',
            str_contains($normalized, 'manual') => 'manual',
            default => $normalized,
        };
    }

    private function resumePayload(
        Invitation $invoice,
        ?string $paymentMethod,
        ?array $midtransTransaction,
        ?array $manualPayment
    ): array {
        return match ($paymentMethod) {
            'midtrans' => [
                'type' => 'midtrans_snap',
                'available' => $midtransTransaction !== null,
                'endpoint' => '/api/v1/midtrans/create-snap-token',
                'method' => 'POST',
                'payload' => [
                    'invitation_id' => $invoice->id,
                    'amount' => (float) $invoice->package_price_snapshot,
                ],
                'reuses_existing_order' => $midtransTransaction !== null,
            ],
            'manual' => [
                'type' => 'manual_payment',
                'available' => $manualPayment !== null,
                'endpoint' => '/api/v1/user/payment-config',
                'method' => 'GET',
            ],
            null => [
                'type' => 'select_payment_method',
                'available' => false,
                'endpoint' => '/api/v1/user/payment-config',
                'method' => 'GET',
            ],
            default => [
                'type' => 'external_redirect',
                'available' => false,
                'provider' => $paymentMethod,
            ],
        };
    }

    private function pricingPayload(Invitation $invoice): array
    {
        $snapshot = is_array($invoice->package_features_snapshot)
            ? $invoice->package_features_snapshot
            : [];

        return app(PackageUpgradePricingService::class)
            ->fromInvoiceSnapshot($snapshot, $invoice->package_price_snapshot);
    }

    private function pricingResponseFields(Invitation $invoice): array
    {
        $pricing = $this->pricingPayload($invoice);

        return [
            'original_price' => $pricing['original_price'],
            'discount_percentage' => $pricing['discount_percentage'],
            'discount_amount' => $pricing['discount_amount'],
            'payable_amount' => $pricing['payable_amount'],
            'upgrade_price' => $pricing['payable_amount'],
            'amount' => $pricing['amount'],
        ];
    }

    private function activeMidtransTransactionFor(Invitation $invoice): ?array
    {
        if (! $invoice->order_id) {
            return null;
        }

        $log = PaymentLog::query()
            ->where('invitation_id', $invoice->id)
            ->where('order_id', $invoice->order_id)
            ->where('event_type', 'token_request')
            ->latest('id')
            ->first();

        if (! $log || in_array(strtolower((string) $log->transaction_status), ['capture', 'settlement', 'deny', 'cancel', 'expire', 'refund'], true)) {
            return null;
        }

        $payload = json_decode((string) $log->response_payload, true);
        $snapToken = is_array($payload) ? trim((string) ($payload['snap_token'] ?? '')) : '';
        if ($snapToken === '') {
            return null;
        }

        $expiresAt = ! empty($payload['expires_at'])
            ? Carbon::parse($payload['expires_at'])
            : $log->created_at->copy()->addHours((int) config('midtrans.token_expiry_hours', 24));

        if ($expiresAt->isPast()) {
            return null;
        }

        return [
            'order_id' => $log->order_id,
            'snap_token' => $snapToken,
            'redirect_url' => null,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    private function packagePayload(?PaketUndangan $package, ?string $code, ?string $name): ?array
    {
        if (! $package && ! $code && ! $name) {
            return null;
        }

        return [
            'id' => $package?->id,
            'code' => $code,
            'name' => $name,
        ];
    }
}
