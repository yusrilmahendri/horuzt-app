<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\Mempelai;
use App\Models\PackageUpgradeHistory;
use App\Models\PaketUndangan;
use Illuminate\Support\Facades\Schema;

class PackageUpgradeService
{
    public function activate(
        Invitation $invoice,
        string $paymentMethod,
        string $source,
        ?string $transactionId = null
    ): ?PackageUpgradeHistory {
        if ($invoice->payment_status === 'paid' && $invoice->payment_confirmed_at !== null) {
            if (Schema::hasColumn('users', 'package_id')) {
                $invoice->user?->forceFill(['package_id' => $invoice->paket_undangan_id])->save();
            }

            Mempelai::where('user_id', $invoice->user_id)->update([
                'status' => 'Sudah Bayar',
                'kd_status' => 'SB',
            ]);

            return $this->completeIfUpgrade($invoice->fresh(['user', 'paketUndangan']), $paymentMethod, 'paid', [
                'source' => $source,
                'idempotent' => true,
            ]);
        }

        $invoice->loadMissing(['user', 'paketUndangan']);
        $duration = (int) ($invoice->package_duration_snapshot ?? $invoice->paketUndangan?->masa_aktif ?? 0);
        $startedAt = now();
        $expiredAt = $duration > 0 ? $startedAt->copy()->addDays($duration) : null;

        $updateData = [
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_confirmed_at' => $invoice->payment_confirmed_at ?: $startedAt,
        ];

        if ($transactionId) {
            $updateData['midtrans_transaction_id'] = $transactionId;
        }

        if ($expiredAt) {
            $updateData['domain_expires_at'] = $expiredAt;
        }

        if (Schema::hasColumn('invitations', 'tanggal_mulai')) {
            $updateData['tanggal_mulai'] = $invoice->getAttribute('tanggal_mulai') ?: $startedAt;
        }

        if (Schema::hasColumn('invitations', 'tanggal_expired') && $expiredAt) {
            $updateData['tanggal_expired'] = $invoice->getAttribute('tanggal_expired') ?: $expiredAt;
        }

        $invoice->update($updateData);

        if (Schema::hasColumn('users', 'package_id')) {
            $invoice->user?->forceFill(['package_id' => $invoice->paket_undangan_id])->save();
        }

        Mempelai::where('user_id', $invoice->user_id)->update([
            'status' => 'Sudah Bayar',
            'kd_status' => 'SB',
        ]);

        return $this->completeIfUpgrade($invoice->fresh(['user', 'paketUndangan']), $paymentMethod, 'paid', [
            'source' => $source,
            'started_at' => $startedAt->toISOString(),
            'expired_at' => $expiredAt?->toISOString(),
        ]);
    }

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
