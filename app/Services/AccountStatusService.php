<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\PaketUndangan;
use App\Models\User;

class AccountStatusService
{
    public const STATUS_UNVERIFIED = 'unverified';
    public const STATUS_ONBOARDING = 'onboarding';
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';

    public function summary(User $user): array
    {
        $invitation = $this->resolveInvoiceForUser($user);
        $package = $invitation?->paketUndangan;
        $snapshot = is_array($invitation?->package_features_snapshot)
            ? $invitation->package_features_snapshot
            : [];

        $paymentStatus = $invitation?->payment_status;
        $isVerified = $user->isAccountVerified();
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
            ! $hasInvoice => self::STATUS_ONBOARDING,
            $hasPendingInvoice => self::STATUS_PENDING_PAYMENT,
            $isPaymentConfirmed && $isExpired => self::STATUS_EXPIRED,
            $isPaymentConfirmed => self::STATUS_ACTIVE,
            default => self::STATUS_ONBOARDING,
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
            'remaining_days' => $activeUntil ? max(0, now()->diffInDays($activeUntil, false)) : null,
            'is_payment_confirmed' => $isPaymentConfirmed,
            'is_expired' => $isExpired,
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

    private function resolveInvoiceForUser(User $user): ?Invitation
    {
        return Invitation::with('paketUndangan')
            ->where('user_id', $user->id)
            ->orderByRaw("
                CASE
                    WHEN payment_status IN ('paid', 'confirmed') THEN 0
                    WHEN payment_status = 'pending' THEN 1
                    WHEN payment_status = 'expired' THEN 2
                    ELSE 3
                END
            ")
            ->orderByDesc('id')
            ->first();
    }
}
