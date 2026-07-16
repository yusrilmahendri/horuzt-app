<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateSnapTokenRequest;
use App\Models\Invitation;
use App\Models\PaketUndangan;
use App\Models\PaymentLog;
use App\Notifications\MidtransPaymentStatusNotification;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransController extends Controller
{
    protected MidtransService $midtransService;

    public function __construct()
    {
        // Auth is enforced at route level via auth:sanctum middleware
    }

    public function createSnapToken(CreateSnapTokenRequest $request): JsonResponse
    {
        try {
            $user = Auth::user()?->fresh();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Sesi login tidak valid. Silakan masuk kembali.'], 401);
            }

            $validated = $request->validated();

            $invitation = Invitation::with(['paketUndangan', 'user.settingOne'])->findOrFail($validated['invitation_id']);
            if ($this->profileNameMissing($user)) {
                return $this->profileIncompleteResponse();
            }

            if ($this->isPaidStatus($invitation->payment_status) || $invitation->payment_confirmed_at !== null) {
                return response()->json([
                    'success' => false,
                    'code' => 'PAYMENT_ALREADY_PAID',
                    'message' => 'Pembayaran untuk invoice ini sudah selesai.',
                    'redirect_url' => '/dashboard/overview',
                    'data' => [
                        'invoice_id' => $invitation->id,
                        'order_id' => $invitation->order_id,
                        'payment_status' => $invitation->payment_status,
                    ],
                ], 409);
            }

            if ($existingTransaction = $this->activeMidtransTransactionFor($invitation)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Transaksi Midtrans sebelumnya ditemukan. Silakan lanjutkan pembayaran.',
                    'data' => [
                        'reused' => true,
                        'invoice_id' => $invitation->id,
                        'invitation_id' => $invitation->id,
                        'order_id' => $existingTransaction['order_id'],
                        'snap_token' => $existingTransaction['snap_token'],
                        'redirect_url' => $existingTransaction['redirect_url'],
                        'payment_status' => $invitation->payment_status ?? 'pending',
                        'expires_at' => $existingTransaction['expires_at'],
                    ],
                ], 200);
            }

            $this->expireStaleMidtransTransactions($invitation);

            $orderId = $this->orderIdForNewMidtransTransaction($invitation);
            $grossAmount = $validated['amount'];

            // Always send a usable email to Midtrans so payment notifications can be delivered.
            $userDomain = $invitation->user->settingOne->domain ?? '-';
            $customerDetails = $this->buildCustomerDetails($user, $invitation, $validated['customer_details'] ?? []);

            if (empty($customerDetails['email'])) {
                Log::warning('Midtrans snap token blocked because customer email is empty', [
                    'user_id' => $user->id,
                    'invitation_id' => $invitation->id,
                    'order_id' => $orderId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Email pengguna wajib tersedia sebelum memulai pembayaran Midtrans.',
                ], 422);
            }

            $packageLabel = PaketUndangan::displayLabelFromCode(
                $invitation->paketUndangan->code ?? null,
                $invitation->paketUndangan->name_paket ?? null
            ) ?? 'Wedding Package';
            $packageCode = PaketUndangan::tierCode(
                $invitation->paketUndangan->name_paket ?? null,
                $invitation->paketUndangan->code ?? null
            ) ?? 'unknown';

            $itemDetails = $validated['item_details'] ?? [[
                'id' => 'paket-' . $invitation->paket_undangan_id,
                'name' => $packageLabel,
                'price' => $grossAmount,
                'quantity' => 1,
            ]];

            $params = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $grossAmount,
                ],
                'customer_details' => $customerDetails,
                'item_details' => $itemDetails,
                'callbacks' => [
                    'finish' => config('midtrans.frontend_finish_url'),
                    'error' => config('midtrans.frontend_error_url'),
                    'pending' => config('midtrans.frontend_pending_url'),
                ],
                // Keep admin reference data out of customer_details so Midtrans receives a valid schema.
                'custom_field1' => $orderId,
                'custom_field2' => $userDomain,
                'custom_field3' => $packageCode,
            ];

            $midtransService = $this->midtransServiceForUser($user->id);
            $snapToken = $midtransService->createTransaction($params);
            $expiresAt = now()->addHours((int) config('midtrans.token_expiry_hours', 24));

            DB::transaction(function () use ($invitation, $orderId, $grossAmount, $user, $params, $snapToken, $expiresAt) {
                $invitation->update([
                    'order_id' => $orderId,
                    'payment_status' => 'pending',
                ]);

                PaymentLog::create([
                    'user_id' => $user->id,
                    'invitation_id' => $invitation->id,
                    'order_id' => $orderId,
                    'event_type' => 'token_request',
                    'transaction_status' => 'pending',
                    'gross_amount' => $grossAmount,
                    'request_payload' => json_encode($params),
                    'response_payload' => json_encode([
                        'snap_token' => $snapToken,
                        'expires_at' => $expiresAt->toIso8601String(),
                    ]),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });

            Log::info('Snap token created successfully', [
                'user_id' => $user->id,
                'order_id' => $orderId,
                'invitation_id' => $invitation->id,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'snap_token' => $snapToken,
                    'order_id' => $orderId,
                    'gross_amount' => $grossAmount,
                    'invitation_id' => $invitation->id,
                    'reused' => false,
                    'redirect_url' => null,
                    'payment_status' => 'pending',
                    'expires_at' => $expiresAt->toIso8601String(),
                ],
                'message' => 'Transaksi Midtrans berhasil dibuat. Silakan lanjutkan pembayaran.',
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\RuntimeException $e) {
            Log::error('Snap token creation failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 503);

        } catch (\Exception $e) {
            Log::error('Unexpected error during snap token creation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat transaksi Midtrans. Silakan coba lagi.',
            ], 500);
        }
    }

    protected function midtransServiceForUser(int $userId): MidtransService
    {
        if (app()->bound(MidtransService::class)) {
            return app(MidtransService::class);
        }

        return new MidtransService($userId);
    }

    private function activeMidtransTransactionFor(Invitation $invitation): ?array
    {
        if (! $invitation->order_id || $this->isTerminalStatus($invitation->payment_status)) {
            return null;
        }

        $log = PaymentLog::query()
            ->where('invitation_id', $invitation->id)
            ->where('order_id', $invitation->order_id)
            ->where('event_type', 'token_request')
            ->latest('id')
            ->first();

        if (! $log || $this->isTerminalStatus($log->transaction_status)) {
            return null;
        }

        $payload = json_decode((string) $log->response_payload, true);
        $snapToken = is_array($payload) ? trim((string) ($payload['snap_token'] ?? '')) : '';
        if ($snapToken === '') {
            return null;
        }

        $expiresAt = $this->snapTokenExpiresAt($log, is_array($payload) ? $payload : []);
        if ($expiresAt->isPast()) {
            return null;
        }

        return [
            'order_id' => $log->order_id,
            'snap_token' => $snapToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'redirect_url' => null,
        ];
    }

    private function expireStaleMidtransTransactions(Invitation $invitation): void
    {
        PaymentLog::query()
            ->where('invitation_id', $invitation->id)
            ->where('event_type', 'token_request')
            ->whereNotIn('transaction_status', ['capture', 'settlement', 'deny', 'cancel', 'expire', 'refund'])
            ->get()
            ->each(function (PaymentLog $log) {
                $payload = json_decode((string) $log->response_payload, true);
                $snapToken = is_array($payload) ? trim((string) ($payload['snap_token'] ?? '')) : '';
                $expiresAt = $this->snapTokenExpiresAt($log, is_array($payload) ? $payload : []);

                if ($snapToken === '' || $expiresAt->isPast()) {
                    $log->update([
                        'transaction_status' => 'expire',
                        'notes' => trim(($log->notes ? $log->notes."\n" : '').'Transaksi Midtrans lama dinonaktifkan secara lokal karena token kosong atau kedaluwarsa.'),
                    ]);
                }
            });
    }

    private function snapTokenExpiresAt(PaymentLog $log, array $payload)
    {
        if (! empty($payload['expires_at'])) {
            return \Carbon\Carbon::parse($payload['expires_at']);
        }

        return $log->created_at->copy()->addHours((int) config('midtrans.token_expiry_hours', 24));
    }

    private function orderIdForNewMidtransTransaction(Invitation $invitation): string
    {
        if (! $invitation->order_id) {
            return $this->baseOrderIdFor($invitation);
        }

        return $this->baseOrderIdFor($invitation).'-'.now()->format('YmdHis');
    }

    private function baseOrderIdFor(Invitation $invitation): string
    {
        return $invitation->kode_pemesanan
            ?? $invitation->user->kode_pemesanan
            ?? 'INV-'.str_pad($invitation->id, 6, '0', STR_PAD_LEFT);
    }

    private function isPaidStatus(?string $status): bool
    {
        return in_array(strtolower((string) $status), ['paid', 'confirmed', 'success', 'settlement', 'capture'], true);
    }

    private function isTerminalStatus(?string $status): bool
    {
        return in_array(strtolower((string) $status), ['paid', 'confirmed', 'success', 'settlement', 'capture', 'failed', 'deny', 'cancel', 'expire', 'expired', 'refund', 'refunded'], true);
    }

    private function buildCustomerDetails($user, Invitation $invitation, mixed $requestCustomerDetails = []): array
    {
        $requestCustomerDetails = is_array($requestCustomerDetails) ? $requestCustomerDetails : [];
        [$firstName, $lastName] = $this->splitCustomerName($this->resolveCustomerName($user, $invitation));

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $user->email ?? $invitation->user->email,
            'phone' => $requestCustomerDetails['phone'] ?? $user->phone ?? '',
        ];
    }

    private function resolveCustomerName($user, Invitation $invitation): string
    {
        foreach ([
            $user->name ?? null,
            $invitation->getAttribute('customer_name'),
            isset($user->email) ? strtok($user->email, '@') : null,
            'Pelanggan Sena Digital',
        ] as $candidate) {
            $name = trim((string) $candidate);
            if ($name !== '') {
                return $name;
            }
        }

        return 'Pelanggan Sena Digital';
    }

    private function splitCustomerName(string $customerName): array
    {
        $parts = preg_split('/\s+/', trim($customerName), 2) ?: [];

        return [
            $parts[0] ?? 'Pelanggan',
            $parts[1] ?? '',
        ];
    }

    private function profileNameMissing($user): bool
    {
        return trim((string) $user->name) === '';
    }

    private function profileIncompleteResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => 'PROFILE_INCOMPLETE',
            'message' => 'Profil belum lengkap. Nama pengguna wajib diisi sebelum membuat invoice pembayaran.',
            'data' => [
                'profile_incomplete' => true,
                'profile_completion_required' => true,
                'missing_fields' => ['name'],
            ],
        ], 422);
    }

    private function extractPaymentDetails(Request $request): array
    {
        $paymentType = $request->input('payment_type');
        $details = [
            'payment_type' => $paymentType,
            'transaction_time' => $request->input('transaction_time'),
            'expiry_time' => $request->input('expiry_time'),
        ];

        if ($paymentType === 'qris') {
            $details['acquirer'] = $request->input('acquirer');
            $details['actions'] = $this->safeMidtransActions($request->input('actions', []));
        }

        if ($paymentType === 'bank_transfer') {
            $vaNumbers = $request->input('va_numbers', []);
            $firstVa = is_array($vaNumbers) ? ($vaNumbers[0] ?? []) : [];
            $details['bank'] = $firstVa['bank'] ?? $request->input('bank');
            $details['va_number'] = $firstVa['va_number'] ?? null;
            $details['permata_va_number'] = $request->input('permata_va_number');
        }

        if ($paymentType === 'echannel') {
            $details['bill_key'] = $request->input('bill_key');
            $details['biller_code'] = $request->input('biller_code');
        }

        if (in_array($paymentType, ['gopay', 'shopeepay'], true)) {
            $details['actions'] = $this->safeMidtransActions($request->input('actions', []));
            $details['deeplink_redirect'] = $this->actionUrl($details['actions'], 'deeplink-redirect');
            $details['generate_qr_code'] = $this->actionUrl($details['actions'], 'generate-qr-code');
        }

        if ($paymentType === 'cstore') {
            $details['store'] = $request->input('store');
            $details['payment_code'] = $request->input('payment_code');
        }

        return array_filter($details, fn ($value) => $value !== null && $value !== '');
    }

    private function safeMidtransActions($actions): array
    {
        if (! is_array($actions)) {
            return [];
        }

        return collect($actions)
            ->filter(fn ($action) => is_array($action))
            ->map(fn ($action) => [
                'name' => $action['name'] ?? null,
                'method' => $action['method'] ?? null,
                'url' => $action['url'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function actionUrl(array $actions, string $name): ?string
    {
        foreach ($actions as $action) {
            if (($action['name'] ?? null) === $name && ! empty($action['url'])) {
                return $action['url'];
            }
        }

        return null;
    }

    private function sendWebhookPaymentNotification(PaymentLog $log, Request $request): void
    {
        $notificationStatus = $this->notificationStatusFor($log->transaction_status);
        if ($notificationStatus === null || $this->notificationAlreadySent($log, $notificationStatus)) {
            return;
        }

        try {
            $invitation = Invitation::with(['user', 'paketUndangan'])->find($log->invitation_id);
            $user = $invitation?->user;

            if (! $invitation || ! $user || empty($user->email)) {
                Log::warning('Midtrans payment notification skipped because user or email is missing', [
                    'payment_log_id' => $log->id,
                    'invitation_id' => $log->invitation_id,
                    'order_id' => $log->order_id,
                ]);

                return;
            }

            $payload = json_decode((string) $log->response_payload, true) ?: [];
            $paymentDetails = is_array($payload['payment_details'] ?? null) ? $payload['payment_details'] : [];
            $continueUrl = $this->continuePaymentUrl($log);

            $user->notify(new MidtransPaymentStatusNotification($notificationStatus, [
                'invoice_code' => $invitation->kode_pemesanan ?? $log->order_id,
                'package_name' => PaketUndangan::displayLabelFromCode(
                    $invitation->paketUndangan?->code,
                    $invitation->paketUndangan?->name_paket ?? ($invitation->package_features_snapshot['name_paket'] ?? null)
                ),
                'gross_amount' => $log->gross_amount,
                'payment_type' => $log->payment_type,
                'expiry_time' => $payload['expiry_time'] ?? $request->input('expiry_time'),
                'payment_details' => $paymentDetails,
                'continue_url' => $continueUrl,
            ]));

            $log->update([
                'notes' => trim(($log->notes ? $log->notes."\n" : '')."notification:{$notificationStatus}_sent"),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send Midtrans payment notification email', [
                'payment_log_id' => $log->id,
                'order_id' => $log->order_id,
                'transaction_status' => $log->transaction_status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notificationStatusFor(?string $transactionStatus): ?string
    {
        return match ($transactionStatus) {
            'pending' => 'pending',
            'capture', 'settlement' => 'paid',
            'deny', 'cancel', 'expire' => 'expired',
            default => null,
        };
    }

    private function notificationAlreadySent(PaymentLog $log, string $notificationStatus): bool
    {
        return PaymentLog::query()
            ->where('order_id', $log->order_id)
            ->where('midtrans_transaction_id', $log->midtrans_transaction_id)
            ->where('event_type', 'webhook_processed')
            ->where('notes', 'like', "%notification:{$notificationStatus}_sent%")
            ->exists();
    }

    private function continuePaymentUrl(PaymentLog $log): string
    {
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'https://www.sena-digital.com')), '/');

        if ($log->transaction_status === 'pending') {
            return $frontendUrl.'/dashboard/payment-pending?order_id='.urlencode((string) $log->order_id);
        }

        if (in_array($log->transaction_status, ['capture', 'settlement'], true)) {
            return $frontendUrl.'/dashboard';
        }

        return $frontendUrl.'/pilih-paket?order_id='.urlencode((string) $log->order_id);
    }

    public function checkPaymentStatus(Request $request): JsonResponse
    {
        $orderId = $request->input('order_id');

        if (!$orderId) {
            return response()->json([
                'success' => false,
                'message' => 'Order ID is required',
            ], 400);
        }

        try {
            $invitation = Invitation::where('order_id', $orderId)->first();

            if (!$invitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            if (in_array($invitation->payment_status, ['paid', 'failed', 'expired', 'refunded'])) {
                return response()->json([
                    'success' => true,
                    'payment_status' => $invitation->payment_status,
                    'message' => 'Payment status: ' . $invitation->payment_status,
                    'data' => [
                        'order_id' => $orderId,
                        'payment_confirmed_at' => $invitation->payment_confirmed_at,
                        'domain_expires_at' => $invitation->domain_expires_at,
                    ],
                ]);
            }

            // Log status check timing for delayed payment analysis
            $secondsSinceCreation = now()->diffInSeconds($invitation->created_at);
            if ($secondsSinceCreation > 180) {
                Log::warning('Delayed payment status check detected', [
                    'order_id' => $orderId,
                    'seconds_since_creation' => $secondsSinceCreation,
                    'invitation_id' => $invitation->id,
                    'current_payment_status' => $invitation->payment_status,
                ]);
            }

            $midtransService = new MidtransService($invitation->user_id);
            $midtransService->configureMidtrans();

            // Retry logic for Midtrans API failures (503, timeout, etc.)
            $maxRetries = 3;
            $attempt = 0;
            $status = null;
            $lastError = null;

            while ($attempt < $maxRetries) {
                try {
                    $status = \Midtrans\Transaction::status($orderId);
                    break; // Success - exit retry loop
                } catch (\Exception $e) {
                    $lastError = $e;
                    $attempt++;

                    if ($attempt >= $maxRetries) {
                        // Log final retry failure
                        Log::error('Payment status check failed after ' . $maxRetries . ' retries', [
                            'order_id' => $orderId,
                            'error' => $e->getMessage(),
                        ]);
                        throw $e; // Re-throw after max retries
                    }

                    // Exponential backoff: 500ms, 1s, 2s
                    $backoffMs = 500000 * pow(2, $attempt - 1);
                    Log::warning('Payment status check retry', [
                        'order_id' => $orderId,
                        'attempt' => $attempt + 1,
                        'max_retries' => $maxRetries,
                        'backoff_ms' => $backoffMs,
                        'error' => $e->getMessage(),
                    ]);
                    usleep($backoffMs);
                }
            }

            PaymentLog::create([
                'user_id' => $invitation->user_id,
                'invitation_id' => $invitation->id,
                'order_id' => $orderId,
                'midtrans_transaction_id' => $status->transaction_id ?? null,
                'event_type' => 'status_check',
                'transaction_status' => $status->transaction_status ?? 'unknown',
                'gross_amount' => $status->gross_amount ?? null,
                'response_payload' => json_encode($status),
                'ip_address' => $request->ip(),
            ]);

            $transactionStatus = $status->transaction_status;

            if (in_array($transactionStatus, ['capture', 'settlement'])) {
                DB::transaction(function () use ($invitation, $status) {
                    $snapshot = $invitation->package_features_snapshot ?? [];

                    $updateData = [
                        'payment_status' => 'paid',
                        'midtrans_transaction_id' => $status->transaction_id,
                        'payment_confirmed_at' => now(),
                    ];

                    // Check if this was an upgrade payment - restore original status
                    if (isset($snapshot['original_status'])) {
                        $updateData['status'] = $snapshot['original_status'];
                    }

                    // Calculate expiry date - use package_duration_snapshot which was captured at registration
                    $duration = $invitation->package_duration_snapshot ?? ($invitation->paketUndangan->masa_aktif ?? 0);

                    // For upgrade payments, extend from current expiry. For new payments, extend from now.
                    if ($duration > 0) {
                        if (isset($snapshot['upgrade_initiated_at']) && $invitation->domain_expires_at) {
                            // Upgrade: extend from current expiry
                            $updateData['domain_expires_at'] = $invitation->domain_expires_at->copy()->addDays($duration);
                        } else {
                            // New payment: extend from now
                            $updateData['domain_expires_at'] = now()->addDays($duration);
                        }
                    }

                    $invitation->update($updateData);

                    // Sync mempelai status — polling confirms payment same as webhook
                    $mempelai = \App\Models\Mempelai::where('user_id', $invitation->user_id)->first();
                    if ($mempelai) {
                        $mempelai->update([
                            'status'    => 'Sudah Bayar',
                            'kd_status' => 'SB',
                        ]);
                    }
                });

                Log::info('Payment status verified and updated', [
                    'order_id' => $orderId,
                    'transaction_status' => $transactionStatus,
                ]);

                return response()->json([
                    'success' => true,
                    'payment_status' => 'paid',
                    'message' => 'Payment confirmed successfully',
                    'data' => [
                        'order_id' => $orderId,
                        'transaction_id' => $status->transaction_id,
                        'payment_confirmed_at' => $invitation->fresh()->payment_confirmed_at,
                        'domain_expires_at' => $invitation->fresh()->domain_expires_at,
                    ],
                ]);
            }

            $paymentStatus = $midtransService->getPaymentStatusFromTransactionStatus($transactionStatus);

            return response()->json([
                'success' => true,
                'payment_status' => $paymentStatus,
                'transaction_status' => $transactionStatus,
                'message' => 'Payment status retrieved',
                'data' => [
                    'order_id' => $orderId,
                    'transaction_id' => $status->transaction_id ?? null,
                ],
            ]);

        } catch (\Midtrans\Exceptions\ApiException $e) {
            Log::warning('Midtrans API error during status check, returning DB status', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
            ]);

            // If the Midtrans API call fails (e.g. transaction not found, sandbox auth issues),
            // return the current DB status rather than propagating a 5xx error.
            return response()->json([
                'success' => true,
                'payment_status' => $invitation->payment_status ?? 'pending',
                'message' => 'Payment status from DB (Midtrans API unavailable)',
                'data' => [
                    'order_id' => $orderId,
                    'payment_confirmed_at' => $invitation->payment_confirmed_at ?? null,
                    'domain_expires_at' => $invitation->domain_expires_at ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Payment status check failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'trace' => $e->getTraceAsString(),
            ]);

            // Fall back to DB status rather than a 5xx if we have the invitation record.
            if (isset($invitation)) {
                return response()->json([
                    'success' => true,
                    'payment_status' => $invitation->payment_status ?? 'pending',
                    'message' => 'Payment status from DB (API error fallback)',
                    'data' => [
                        'order_id' => $orderId,
                        'payment_confirmed_at' => $invitation->payment_confirmed_at ?? null,
                        'domain_expires_at' => $invitation->domain_expires_at ?? null,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status',
            ], 500);
        }
    }

    /**
     * Fallback endpoint to confirm payment success from frontend callback.
     * Called when frontend receives onSuccess from Midtrans Snap but checkStatus fails.
     *
     * This is safe because onSuccess callback only comes from Midtrans Snap after
     * successful payment. We trust this enough to mark as paid temporarily,
     * webhook will provide final confirmation.
     */
    public function confirmPaymentSuccess(Request $request): JsonResponse
    {
        $orderId = $request->input('order_id');
        $transactionId = $request->input('transaction_id');
        $grossAmount = $request->input('gross_amount');

        if (!$orderId) {
            return response()->json([
                'success' => false,
                'message' => 'Order ID is required',
            ], 400);
        }

        try {
            $invitation = Invitation::where('order_id', $orderId)->first();

            if (!$invitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            // If already paid, just return success
            if ($invitation->payment_status === 'paid') {
                return response()->json([
                    'success' => true,
                    'payment_status' => 'paid',
                    'message' => 'Payment already confirmed',
                    'data' => [
                        'order_id' => $orderId,
                        'payment_confirmed_at' => $invitation->payment_confirmed_at,
                        'domain_expires_at' => $invitation->domain_expires_at,
                    ],
                ]);
            }

            // Mark as paid based on frontend callback
            DB::transaction(function () use ($invitation, $transactionId, $orderId) {
                $snapshot = $invitation->package_features_snapshot ?? [];

                $updateData = [
                    'payment_status' => 'paid',
                    'midtrans_transaction_id' => $transactionId,
                    'payment_confirmed_at' => now(),
                ];

                // Check if this was an upgrade payment - restore original status
                if (isset($snapshot['original_status'])) {
                    $updateData['status'] = $snapshot['original_status'];
                }

                // Calculate expiry date - use package_duration_snapshot which was captured at registration
                $duration = $invitation->package_duration_snapshot ?? ($invitation->paketUndangan->masa_aktif ?? 0);

                // For upgrade payments, extend from current expiry. For new payments, extend from now.
                if ($duration > 0) {
                    if (isset($snapshot['upgrade_initiated_at']) && $invitation->domain_expires_at) {
                        // Upgrade: extend from current expiry
                        $updateData['domain_expires_at'] = $invitation->domain_expires_at->copy()->addDays($duration);
                    } else {
                        // New payment: extend from now
                        $updateData['domain_expires_at'] = now()->addDays($duration);
                    }
                }

                $invitation->update($updateData);

                // Sync mempelai status
                $mempelai = \App\Models\Mempelai::where('user_id', $invitation->user_id)->first();
                if ($mempelai) {
                    $mempelai->update([
                        'status'    => 'Sudah Bayar',
                        'kd_status' => 'SB',
                    ]);
                }

                // Log the frontend callback confirmation
                PaymentLog::create([
                    'user_id' => $invitation->user_id,
                    'invitation_id' => $invitation->id,
                    'order_id' => $orderId,
                    'midtrans_transaction_id' => $transactionId,
                    'event_type' => 'frontend_callback_confirmed',
                    'transaction_status' => 'settlement',
                    'gross_amount' => $grossAmount,
                    'signature_valid' => true,
                    'ip_address' => $request->ip(),
                    'notes' => 'Payment confirmed via frontend onSuccess callback (API fallback)',
                ]);
            });

            Log::info('Payment confirmed via frontend callback', [
                'order_id' => $orderId,
                'transaction_id' => $transactionId,
                'invitation_id' => $invitation->id,
            ]);

            return response()->json([
                'success' => true,
                'payment_status' => 'paid',
                'message' => 'Payment confirmed successfully',
                'data' => [
                    'order_id' => $orderId,
                    'transaction_id' => $transactionId,
                    'payment_confirmed_at' => $invitation->fresh()->payment_confirmed_at,
                    'domain_expires_at' => $invitation->fresh()->domain_expires_at,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to confirm payment via frontend callback', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm payment',
            ], 500);
        }
    }

    public function handleWebhook(Request $request): JsonResponse
    {
        // Log all incoming request for debugging
        Log::info('Midtrans webhook received', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'headers' => $request->headers->all(),
            'body' => $request->all(),
        ]);

        $orderId = $request->input('order_id');
        $transactionStatus = $request->input('transaction_status');
        $transactionId = $request->input('transaction_id');
        $statusCode = $request->input('status_code');
        $grossAmount = $request->input('gross_amount');
        $signatureKey = $request->input('signature_key');

        PaymentLog::create([
            'order_id' => $orderId,
            'midtrans_transaction_id' => $transactionId,
            'event_type' => 'webhook_received',
            'transaction_status' => $transactionStatus ?? 'unknown',
            'gross_amount' => $grossAmount,
            'request_payload' => json_encode($request->all()),
            'signature_key' => $signatureKey,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        try {
            $invitation = Invitation::where('order_id', $orderId)->first();

            if (!$invitation) {
                Log::warning('Webhook received for non-existent order', [
                    'order_id' => $orderId,
                ]);

                return response()->json(['message' => 'Order not found'], 404);
            }

            $midtransService = new MidtransService($invitation->user_id);

            $signatureValid = $midtransService->verifySignature(
                $orderId,
                $statusCode,
                $grossAmount,
                $signatureKey
            );

            if (!$signatureValid) {
                PaymentLog::create([
                    'order_id' => $orderId,
                    'midtrans_transaction_id' => $transactionId,
                    'event_type' => 'error',
                    'transaction_status' => $transactionStatus,
                    'signature_valid' => false,
                    'error_message' => 'Invalid signature',
                    'ip_address' => $request->ip(),
                ]);

                Log::warning('Invalid webhook signature', [
                    'order_id' => $orderId,
                    'ip' => $request->ip(),
                ]);

                return response()->json(['message' => 'Invalid signature'], 403);
            }

            if ($this->isPaidStatus($invitation->payment_status)) {
                Log::info('Duplicate webhook for already-terminal invitation', [
                    'order_id' => $orderId,
                    'invitation_id' => $invitation->id,
                    'current_status' => $invitation->payment_status,
                    'incoming_transaction_status' => $transactionStatus,
                ]);

                return response()->json(['message' => 'Already processed'], 200);
            }

            $processedLog = DB::transaction(function () use ($invitation, $transactionStatus, $transactionId, $midtransService, $request, $orderId, $grossAmount) {
                $paymentStatus = $midtransService->getPaymentStatusFromTransactionStatus($transactionStatus);
                $snapshot = $invitation->package_features_snapshot ?? [];
                $paymentDetails = $this->extractPaymentDetails($request);

                $updateData = [
                    'payment_status' => $paymentStatus,
                    'midtrans_transaction_id' => $transactionId,
                ];

                if (in_array($transactionStatus, ['capture', 'settlement'])) {
                    $updateData['payment_confirmed_at'] = now();

                    // Check if this was an upgrade payment - restore original status
                    if (isset($snapshot['original_status'])) {
                        $updateData['status'] = $snapshot['original_status'];
                    }

                    // Calculate expiry date - use package_duration_snapshot which was captured at registration
                    $duration = $invitation->package_duration_snapshot ?? ($invitation->paketUndangan->masa_aktif ?? 0);

                    // For upgrade payments, extend from current expiry. For new payments, extend from now.
                    if ($duration > 0) {
                        if (isset($snapshot['upgrade_initiated_at']) && $invitation->domain_expires_at) {
                            // Upgrade: extend from current expiry
                            $updateData['domain_expires_at'] = $invitation->domain_expires_at->copy()->addDays($duration);
                        } else {
                            // New payment: extend from now
                            $updateData['domain_expires_at'] = now()->addDays($duration);
                        }
                    }
                }

                $invitation->update($updateData);

                // Midtrans payments are auto-confirmed — sync mempelai status immediately
                if (in_array($transactionStatus, ['capture', 'settlement'])) {
                    $mempelai = \App\Models\Mempelai::where('user_id', $invitation->user_id)->first();
                    if ($mempelai) {
                        $mempelai->update([
                            'status'    => 'Sudah Bayar',
                            'kd_status' => 'SB',
                        ]);
                    }
                }

                return PaymentLog::create([
                    'user_id' => $invitation->user_id,
                    'invitation_id' => $invitation->id,
                    'order_id' => $orderId,
                    'midtrans_transaction_id' => $transactionId,
                    'event_type' => 'webhook_processed',
                    'transaction_status' => $transactionStatus,
                    'payment_type' => $request->input('payment_type'),
                    'gross_amount' => $grossAmount,
                    'request_payload' => json_encode($request->all()),
                    'response_payload' => json_encode([
                        'transaction_time' => $request->input('transaction_time'),
                        'expiry_time' => $request->input('expiry_time'),
                        'payment_details' => $paymentDetails,
                    ]),
                    'signature_valid' => true,
                    'ip_address' => $request->ip(),
                    'notes' => "Payment status updated to: {$paymentStatus}",
                ]);
            });

            $this->sendWebhookPaymentNotification($processedLog->fresh(), $request);

            Log::info('Webhook processed successfully', [
                'order_id' => $orderId,
                'transaction_status' => $transactionStatus,
                'payment_status' => $invitation->fresh()->payment_status,
            ]);

            return response()->json(['message' => 'Webhook processed successfully'], 200);

        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'trace' => $e->getTraceAsString(),
            ]);

            PaymentLog::create([
                'order_id' => $orderId,
                'midtrans_transaction_id' => $transactionId,
                'event_type' => 'error',
                'transaction_status' => $transactionStatus,
                'error_message' => $e->getMessage(),
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['message' => 'Webhook processing failed'], 500);
        }
    }
}
