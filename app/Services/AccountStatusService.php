<?php

namespace App\Services;

use App\Models\PaketUndangan;
use App\Models\User;

class AccountStatusService
{
    public const STATUS_UNVERIFIED = 'unverified';
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';

    public function summary(User $user): array
    {
        $user->loadMissing('invitationOne.paketUndangan');

        $invitation = $user->invitationOne;
        $package = $invitation?->paketUndangan;
        $snapshot = is_array($invitation?->package_features_snapshot)
            ? $invitation->package_features_snapshot
            : [];

        $paymentStatus = $invitation?->payment_status;
        $isVerified = $user->isAccountVerified();
        $activeUntil = $invitation?->domain_expires_at;
        $isExpired = $activeUntil ? now()->greaterThan($activeUntil) : false;
        $isPaymentConfirmed = $invitation
            ? ($paymentStatus === 'paid' || $paymentStatus === 'confirmed' || $invitation->payment_confirmed_at !== null)
            : false;

        $accountStatus = match (true) {
            ! $isVerified => self::STATUS_UNVERIFIED,
            $isExpired => self::STATUS_EXPIRED,
            ! $isPaymentConfirmed => self::STATUS_PENDING_PAYMENT,
            default => self::STATUS_ACTIVE,
        };

        $packageCode = $package?->code
            ?? $snapshot['code']
            ?? PaketUndangan::tierCode($snapshot['name_paket'] ?? $snapshot['jenis_paket'] ?? null);

        $packageName = PaketUndangan::displayLabelFromCode(
            $packageCode,
            $snapshot['name_paket'] ?? $package?->name_paket
        );

        $canInputInvitation = $accountStatus === self::STATUS_ACTIVE;

        return [
            'is_verified' => $isVerified,
            'account_status' => $accountStatus,
            'payment_status' => $paymentStatus,
            'package_name' => $packageName,
            'package_code' => $packageCode,
            'active_until' => $activeUntil,
            'remaining_days' => $activeUntil ? max(0, now()->diffInDays($activeUntil, false)) : null,
            'is_payment_confirmed' => $isPaymentConfirmed,
            'is_expired' => $isExpired,
            'feature_access' => [
                'input_undangan' => $canInputInvitation,
                'mempelai' => $canInputInvitation,
                'acara' => $canInputInvitation,
                'gallery' => $canInputInvitation,
                'musik' => $canInputInvitation,
                'rekening' => $canInputInvitation,
                'cerita' => $canInputInvitation,
                'quote' => $canInputInvitation,
                'bagi_undangan' => $canInputInvitation,
            ],
        ];
    }
}
