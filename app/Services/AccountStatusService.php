<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\PaketUndangan;
use App\Models\User;

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
        $package = $invitation?->paketUndangan;
        $snapshot = is_array($invitation?->package_features_snapshot)
            ? $invitation->package_features_snapshot
            : [];

        $paymentStatus = $this->normalizePaymentStatus($invitation?->payment_status ?? $invitation?->status);
        $isVerified = $user->isAccountVerified();
        $isProfileComplete = trim((string) $user->name) !== '';
        $hasInvoice = $invitation !== null;
        $hasPendingInvoice = $hasInvoice && $paymentStatus === 'pending';
        $activeUntil = $invitation?->domain_expires_at;
        $isExpired = $hasInvoice && (
            $paymentStatus === 'expired'
            || ($activeUntil ? now()->greaterThan($activeUntil) : false)
        );
        $isPaymentConfirmed = $invitation
            ? (in_array($paymentStatus, ['paid', 'confirmed'], true) || $invitation->payment_confirmed_at !== null)
            : false;

        $accountStatus = match (true) {
            ! $isVerified => self::STATUS_UNVERIFIED,
            ! $hasInvoice => self::STATUS_PAYMENT_SELECTION,
            $hasPendingInvoice => self::STATUS_PENDING_PAYMENT,
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

        return [
            'name' => $user->name,
            'is_verified' => $isVerified,
            'account_status' => $accountStatus,
            'payment_status' => $paymentStatus,
            'has_invoice' => $hasInvoice,
            'has_pending_invoice' => $hasPendingInvoice,
            'invoice_id' => $invitation?->id,
            'invoice_code' => $invitation?->kode_pemesanan,
            'kode_pemesanan' => $invitation?->kode_pemesanan ?? $user->kode_pemesanan,
            'package_name' => $packageName,
            'package_code' => $packageCode,
            'active_until' => $activeUntil,
            'active_until_formatted' => $this->formatDate($activeUntil),
            'expired_at_formatted' => $this->formatDate($activeUntil),
            'tanggal_expired_formatted' => $this->formatDate($activeUntil),
            'remaining_days' => $activeUntil ? max(0, now()->diffInDays($activeUntil, false)) : null,
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
}
