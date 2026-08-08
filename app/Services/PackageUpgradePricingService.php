<?php

namespace App\Services;

use App\Models\PaketUndangan;

class PackageUpgradePricingService
{
    public const UPGRADE_DISCOUNT_PERCENTAGE = 40;

    public function calculate(PaketUndangan $targetPackage, string $changeType): array
    {
        $originalPrice = round((float) $targetPackage->price, 2);
        $discountPercentage = $changeType === 'upgrade'
            ? self::UPGRADE_DISCOUNT_PERCENTAGE
            : 0;
        $discountAmount = round($originalPrice * ($discountPercentage / 100), 2);
        $payableAmount = round($originalPrice - $discountAmount, 2);

        return [
            'original_price' => $originalPrice,
            'discount_percentage' => $discountPercentage,
            'discount_amount' => $discountAmount,
            'payable_amount' => $payableAmount,
            'amount' => $payableAmount,
        ];
    }

    public function fromInvoiceSnapshot(array $snapshot, mixed $fallbackAmount): array
    {
        $amount = round((float) $fallbackAmount, 2);

        return [
            'original_price' => isset($snapshot['original_price'])
                ? round((float) $snapshot['original_price'], 2)
                : $amount,
            'discount_percentage' => isset($snapshot['discount_percentage'])
                ? (int) $snapshot['discount_percentage']
                : 0,
            'discount_amount' => isset($snapshot['discount_amount'])
                ? round((float) $snapshot['discount_amount'], 2)
                : 0.0,
            'payable_amount' => isset($snapshot['payable_amount'])
                ? round((float) $snapshot['payable_amount'], 2)
                : $amount,
            'amount' => $amount,
        ];
    }
}
