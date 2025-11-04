<?php

namespace App\Services;

use App\Models\MidtransTransaction;
use App\Models\PaymentLog;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;

class MidtransService
{
    protected $userId;
    protected $config;

    public function __construct(?int $userId = null)
    {
        $this->userId = $userId;
        $this->loadConfiguration();
    }

    protected function loadConfiguration(): void
    {
        if ($this->userId) {
            $this->config = MidtransTransaction::byUser($this->userId)
                ->active()
                ->latest()
                ->first();
        }

        if (!$this->config) {
            Log::info('Using global Midtrans configuration from .env');
        }
    }

    public function createTransaction(array $params): string
    {
        try {
            $this->setMidtransConfig();

            Log::info('Creating Midtrans Snap token', [
                'user_id' => $this->userId,
                'order_id' => $params['transaction_details']['order_id'] ?? null,
                'is_production' => $this->isProduction(),
            ]);

            $snapToken = Snap::getSnapToken($params);

            Log::info('Midtrans Snap token created successfully', [
                'order_id' => $params['transaction_details']['order_id'] ?? null,
            ]);

            return $snapToken;

        } catch (\Exception $e) {
            Log::error('Failed to create Midtrans Snap token', [
                'error' => $e->getMessage(),
                'user_id' => $this->userId,
                'params' => $params,
            ]);

            throw new \RuntimeException('Failed to generate payment token. Please try again later.');
        }
    }

    public function configureMidtrans(): void
    {
        $this->setMidtransConfig();
    }

    protected function setMidtransConfig(): void
    {
        $serverKey = $this->getServerKey();
        $clientKey = $this->getClientKey();

        if (empty($serverKey) || empty($clientKey)) {
            throw new \RuntimeException('Midtrans configuration is missing. Please contact support.');
        }

        Config::$serverKey = $serverKey;
        Config::$isProduction = $this->isProduction();
        Config::$isSanitized = config('midtrans.is_sanitized', true);
        Config::$is3ds = config('midtrans.is_3ds', true);
    }

    public function getServerKey(): string
    {
        return $this->config?->server_key ?? config('midtrans.server_key', '');
    }

    public function getClientKey(): string
    {
        return $this->config?->client_key ?? config('midtrans.client_key', '');
    }

    public function isProduction(): bool
    {
        if ($this->config) {
            return $this->config->metode_production === 'production';
        }

        return config('midtrans.is_production', false);
    }

    public function verifySignature(string $orderId, string $statusCode, string $grossAmount, string $signatureKey): bool
    {
        $serverKey = $this->getServerKey();

        $calculatedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        $isValid = hash_equals($calculatedSignature, $signatureKey);

        Log::info('Signature verification', [
            'order_id' => $orderId,
            'is_valid' => $isValid,
        ]);

        return $isValid;
    }

    public function getPaymentStatusFromTransactionStatus(string $transactionStatus): string
    {
        return match ($transactionStatus) {
            'capture', 'settlement' => 'paid',
            'deny', 'cancel', 'expire' => 'failed',
            'refund' => 'refunded',
            default => 'pending',
        };
    }
}

