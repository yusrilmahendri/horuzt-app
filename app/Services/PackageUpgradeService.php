<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\Mempelai;
use App\Models\PackageUpgradeHistory;
use App\Models\PaketUndangan;
use Illuminate\Support\Facades\Schema;

class PackageUpgradeService
{
    public function completeIfUpgrade(
        Invitation $invoice,
        string $paymentMethod,
        string $paymentStatus = 'paid',
        array $metadata = []
    ): ?PackageUpgradeHistory {
        $snapshot = is_array($invoice->package_features_snapshot)
            ? $invoice->package_features_snapshot
            : [];

        if (($snapshot['invoice_type'] ?? null) !== 'package_upgrade') {
            return null;
        }

        $packageBeforeId = isset($snapshot['previous_package_id'])
            ? (int) $snapshot['previous_package_id']
            : null;

        $packageAfterId = (int) $invoice->paket_undangan_id;

        if (Schema::hasColumn('users', 'package_id')) {
            $invoice->user?->forceFill(['package_id' => $packageAfterId])->save();
        }

        Mempelai::where('user_id', $invoice->user_id)->update([
            'status' => 'Sudah Bayar',
            'kd_status' => 'SB',
        ]);

        return PackageUpgradeHistory::firstOrCreate(
            [
                'invitation_id' => $invoice->id,
                'payment_status' => $paymentStatus,
            ],
            [
                'user_id' => $invoice->user_id,
                'package_before_id' => $packageBeforeId,
                'package_after_id' => $packageAfterId,
                'payment_method' => $paymentMethod,
                'amount' => $invoice->package_price_snapshot,
                'metadata' => array_filter(array_merge([
                    'invoice_code' => $invoice->kode_pemesanan,
                    'order_id' => $invoice->order_id,
                    'package_before' => $packageBeforeId
                        ? $this->packageSnapshot(PaketUndangan::find($packageBeforeId))
                        : null,
                    'package_after' => $this->packageSnapshot($invoice->paketUndangan),
                ], $metadata), fn ($value) => $value !== null),
            ]
        );
    }

    private function packageSnapshot(?PaketUndangan $package): ?array
    {
        if (! $package) {
            return null;
        }

        return [
            'id' => $package->id,
            'name' => $package->getRawOriginal('name_paket') ?? $package->name_paket,
            'slug' => $package->getAttribute('slug') ?? $package->getAttribute('code'),
            'price' => $package->price,
        ];
    }
}
