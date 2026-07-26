<?php

namespace App\Services;

use App\Models\MetodeTransaction;
use App\Models\MidtransTransaction;
use App\Models\Rekening;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PaymentMethodResolver
{
    public const MANUAL = 'manual';
    public const MIDTRANS = 'midtrans';

    public function activeMethod(): string
    {
        return $this->manualConfigured() ? self::MANUAL : self::MIDTRANS;
    }

    public function normalizeMethod(?string $value): ?string
    {
        $key = Str::of((string) $value)->lower()->replace([' ', '-', '_'], '')->ascii()->toString();

        return match (true) {
            $key === self::MANUAL || str_contains($key, self::MANUAL) => self::MANUAL,
            $key === self::MIDTRANS || str_contains($key, self::MIDTRANS) => self::MIDTRANS,
            default => null,
        };
    }

    public function activePayload(): array
    {
        $method = $this->activeMethod();

        return match ($method) {
            self::MIDTRANS => [
                'payment_method' => self::MIDTRANS,
                'midtrans' => [
                    'enabled' => true,
                    'configured' => $this->midtransConfigured(),
                ],
                'manual_payment' => null,
            ],
            default => [
                'payment_method' => self::MANUAL,
                'manual_payment' => $this->manualPaymentPayload(),
                'midtrans' => [
                    'enabled' => false,
                ],
            ],
        };
    }

    public function manualPaymentPayload(): ?array
    {
        $account = $this->manualAccount();

        if (! $account instanceof Rekening) {
            return null;
        }

        return [
            'bank_name' => $account->nama_bank,
            'account_number' => $account->nomor_rekening,
            'account_name' => $account->nama_pemilik,
            'account_photo_url' => $account->photo_url,
        ];
    }

    public function manualConfigured(): bool
    {
        return $this->manualAccount() instanceof Rekening;
    }

    public function midtransConfigured(): bool
    {
        $hasEnvConfig = trim((string) config('midtrans.server_key')) !== ''
            && trim((string) config('midtrans.client_key')) !== '';

        if ($hasEnvConfig) {
            return true;
        }

        if (! Schema::hasTable('midtrans_transactions')) {
            return false;
        }

        return MidtransTransaction::query()->active()->exists();
    }

    public function manualAccount(): ?Rekening
    {
        if (! Schema::hasTable('rekenings')) {
            return null;
        }

        $query = Rekening::query()
            ->whereNotNull('nama_bank')
            ->whereNotNull('nomor_rekening')
            ->whereNotNull('nama_pemilik')
            ->whereRaw("TRIM(nama_bank) != ''")
            ->whereRaw("TRIM(nomor_rekening) != ''")
            ->whereRaw("TRIM(nama_pemilik) != ''");

        if (Schema::hasTable('model_has_roles') && Schema::hasTable('roles')) {
            $query->whereHas('user.roles', fn ($query) => $query->where('name', 'admin'));
        }

        return $query->latest('id')->first();
    }

    public function methodForTransactionPayload(string $method): array
    {
        return [
            'payment_method' => $method,
            'payment_method_source' => 'manual_payment_configuration',
        ];
    }

    public function methodModelFor(string $method): ?MetodeTransaction
    {
        if (! Schema::hasTable('metode_transactions')) {
            return null;
        }

        return MetodeTransaction::query()
            ->get()
            ->first(fn (MetodeTransaction $model): bool => $this->normalizeMethod($model->name) === $method);
    }
}
