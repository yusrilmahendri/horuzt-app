<?php

namespace App\Services;

use App\Models\MidtransTransaction;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;
use Throwable;

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

        if (! $this->config) {
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
                'gross_amount' => $params['transaction_details']['gross_amount'] ?? null,
                'item_count' => count($params['item_details'] ?? []),
                'customer_fields' => array_keys($params['customer_details'] ?? []),
                'is_production' => $this->isProduction(),
                'configuration_source' => $this->config ? 'database' : 'environment',
            ]);

            $snapToken = Snap::getSnapToken($params);

            Log::info('Midtrans Snap token created successfully', [
                'order_id' => $params['transaction_details']['order_id'] ?? null,
            ]);

            return $snapToken;

        } catch (Throwable $e) {
            $error = self::errorContext($e);

            Log::error('Failed to create Midtrans Snap token', [
                'midtrans_status' => $error['status'],
                'midtrans_message' => $error['message'],
                'exception' => $e::class,
                'user_id' => $this->userId,
                'order_id' => $params['transaction_details']['order_id'] ?? null,
                'gross_amount' => $params['transaction_details']['gross_amount'] ?? null,
                'is_production' => $this->isProduction(),
                'configuration_source' => $this->config ? 'database' : 'environment',
            ]);

            throw new \RuntimeException(
                'Failed to generate payment token. Please try again later.',
                $error['status'] ?? 0,
                $e
            );
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

        $usesSandboxServerKey = str_starts_with($serverKey, 'SB-');
        $usesSandboxClientKey = str_starts_with($clientKey, 'SB-');
        if (
            $this->isProduction() === $usesSandboxServerKey
            || $this->isProduction() === $usesSandboxClientKey
        ) {
            throw new \RuntimeException('Midtrans mode and API Key environment do not match.');
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

    /**
     * Extract an actionable Midtrans status/message without logging credentials
     * or the full API response payload.
     *
     * @return array{status: int|null, message: string}
     */
    public static function errorContext(Throwable $exception): array
    {
        $status = is_int($exception->getCode()) && $exception->getCode() >= 100 && $exception->getCode() <= 599
            ? $exception->getCode()
            : null;
        $rawMessage = $exception->getMessage();
        $message = $rawMessage;

        if ($status === null && preg_match('/HTTP status code:\s*(\d{3})/i', $rawMessage, $matches)) {
            $status = (int) $matches[1];
        }

        if (preg_match('/API response:\s*(\{.*\})\s*$/s', $rawMessage, $matches)) {
            $response = json_decode($matches[1], true);

            if (is_array($response)) {
                $status = isset($response['status_code']) && is_numeric($response['status_code'])
                    ? (int) $response['status_code']
                    : $status;

                if (! empty($response['status_message']) && is_string($response['status_message'])) {
                    $message = $response['status_message'];
                } elseif (! empty($response['error_messages']) && is_array($response['error_messages'])) {
                    $message = implode('; ', array_filter($response['error_messages'], 'is_string'));
                } else {
                    $message = 'Midtrans API returned an error without a status message.';
                }
            } else {
                $message = 'Midtrans API returned an unreadable error response.';
            }
        } elseif (str_contains($rawMessage, 'API response:')) {
            $message = 'Midtrans API returned an unreadable error response.';
        }

        $message = preg_replace(
            [
                '/\b(?:SB-)?Mid-(?:server|client)-[A-Za-z0-9_-]+\b/i',
                '/\b(?:server_key|serverKey|client_key|clientKey)\b\s*[:=]\s*[^\s,}]+/i',
                '/\bAuthorization\s*:\s*Basic\s+[A-Za-z0-9+\/=]+/i',
                '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
            ],
            ['[redacted-key]', '[redacted-key]', '[redacted-authorization]', '[redacted-email]'],
            $message
        );

        return [
            'status' => $status,
            'message' => mb_substr(trim((string) $message), 0, 1000),
        ];
    }

    public function verifySignature(string $orderId, string $statusCode, string $grossAmount, ?string $signatureKey): bool
    {
        if ($signatureKey === null || $signatureKey === '') {
            return false;
        }

        $serverKey = $this->getServerKey();

        $calculatedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

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
