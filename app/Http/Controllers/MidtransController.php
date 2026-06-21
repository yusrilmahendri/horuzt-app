<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateSnapTokenRequest;
use App\Models\Invitation;
use App\Models\PaymentLog;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            $user = Auth::user();

            if (! $user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }

            $validated = $request->validated();

            $invitation = Invitation::with('paketUndangan')
                ->where('user_id', $user->id)
                ->findOrFail($validated['invitation_id']);

            $orderId = $this->generateUniqueOrderId($invitation);
            $grossAmount = $this->expectedGrossAmount($invitation);
            $submittedCustomer = $validated['customer_details'] ?? [];
            $customerDetails = array_filter([
                'first_name' => $submittedCustomer['first_name'] ?? $user->name ?? 'Customer',
                'last_name' => $submittedCustomer['last_name'] ?? null,
                'email' => $user->email,
                'phone' => $user->phone ?? ($submittedCustomer['phone'] ?? null),
            ], static fn ($value) => $value !== null && $value !== '');

            // Pricing and items are always server-authoritative. The Midtrans SDK
            // recalculates gross_amount from item_details when this field exists.
            $itemDetails = [[
                'id' => 'paket-'.$invitation->paket_undangan_id,
                'name' => Str::limit($invitation->paketUndangan->name_paket ?? 'Wedding Package', 50, ''),
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
                    'finish' => config('midtrans.frontend_finish_url', env('APP_URL').'/payment/success'),
                    'error' => config('midtrans.frontend_error_url', env('APP_URL').'/payment/error'),
                    'pending' => config('midtrans.frontend_pending_url', env('APP_URL').'/payment/pending'),
                ],
            ];

            $midtransService = new MidtransService($user->id);
            $snapToken = $midtransService->createTransaction($params);

            DB::transaction(function () use ($invitation, $orderId, $grossAmount, $user, $params) {
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
                    'request_payload' => json_encode([
                        'order_id' => $orderId,
                        'gross_amount' => $grossAmount,
                        'item_count' => count($params['item_details']),
                        'customer_fields' => array_keys($params['customer_details']),
                    ]),
                    'response_payload' => json_encode(['token_created' => true]),
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
                    'expires_at' => now()->addHours(24)->toIso8601String(),
                ],
                'message' => 'Snap token created successfully',
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\RuntimeException $e) {
            $error = MidtransService::errorContext($e->getPrevious() ?? $e);

            Log::error('Snap token creation failed', [
                'midtrans_status' => $error['status'],
                'midtrans_message' => $error['message'],
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
                'message' => 'An unexpected error occurred. Please try again later.',
            ], 500);
        }
    }

    public function checkPaymentStatus(Request $request): JsonResponse
    {
        $orderId = $request->input('order_id');

        if (! $orderId) {
            return response()->json([
                'success' => false,
                'message' => 'Order ID is required',
            ], 400);
        }

        try {
            $invitation = Invitation::where('order_id', $orderId)
                ->where('user_id', Auth::id())
                ->first();

            if (! $invitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            if (in_array($invitation->payment_status, ['paid', 'failed', 'expired', 'refunded'])) {
                return response()->json([
                    'success' => true,
                    'payment_status' => $invitation->payment_status,
                    'message' => 'Payment status: '.$invitation->payment_status,
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

            while ($attempt < $maxRetries) {
                try {
                    $status = \Midtrans\Transaction::status($orderId);
                    break; // Success - exit retry loop
                } catch (\Exception $e) {
                    $attempt++;
                    $error = MidtransService::errorContext($e);

                    if ($attempt >= $maxRetries) {
                        // Log final retry failure
                        Log::error('Payment status check failed after '.$maxRetries.' retries', [
                            'order_id' => $orderId,
                            'midtrans_status' => $error['status'],
                            'midtrans_message' => $error['message'],
                        ]);
                        throw $e; // Re-throw after max retries
                    }

                    // Exponential backoff: 500ms, 1s, 2s
                    $backoffUs = 500000 * pow(2, $attempt - 1);
                    Log::warning('Payment status check retry', [
                        'order_id' => $orderId,
                        'attempt' => $attempt + 1,
                        'max_retries' => $maxRetries,
                        'backoff_us' => $backoffUs,
                        'midtrans_status' => $error['status'],
                        'midtrans_message' => $error['message'],
                    ]);
                    usleep($backoffUs);
                }
            }

            if (! $this->grossAmountMatches($invitation, $status->gross_amount ?? null)) {
                Log::warning('Midtrans gross amount mismatch', [
                    'order_id' => $orderId,
                    'expected_gross_amount' => $this->expectedGrossAmount($invitation),
                    'midtrans_gross_amount' => $status->gross_amount ?? null,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount does not match the invoice.',
                ], 409);
            }

            PaymentLog::create([
                'user_id' => $invitation->user_id,
                'invitation_id' => $invitation->id,
                'order_id' => $orderId,
                'midtrans_transaction_id' => $status->transaction_id ?? null,
                'event_type' => 'status_check',
                'transaction_status' => $status->transaction_status ?? 'unknown',
                'gross_amount' => $status->gross_amount ?? null,
                'response_payload' => json_encode([
                    'transaction_status' => $status->transaction_status ?? 'unknown',
                    'status_code' => $status->status_code ?? null,
                    'gross_amount' => $status->gross_amount ?? null,
                ]),
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
                            'status' => 'Sudah Bayar',
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

        } catch (\Exception $e) {
            $error = MidtransService::errorContext($e);

            Log::error('Payment status check failed', [
                'midtrans_status' => $error['status'],
                'midtrans_message' => $error['message'],
                'order_id' => $orderId,
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
     * Keep the frontend callback endpoint for compatibility, but never trust
     * client-supplied payment state. Confirmation is performed server-to-server.
     */
    public function confirmPaymentSuccess(Request $request): JsonResponse
    {
        return $this->checkPaymentStatus($request);
    }

    public function handleWebhook(Request $request): JsonResponse
    {
        Log::info('Midtrans webhook received', [
            'order_id' => $request->input('order_id'),
            'transaction_status' => $request->input('transaction_status'),
            'status_code' => $request->input('status_code'),
            'ip' => $request->ip(),
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
            'request_payload' => json_encode([
                'order_id' => $orderId,
                'transaction_status' => $transactionStatus,
                'status_code' => $statusCode,
                'gross_amount' => $grossAmount,
                'payment_type' => $request->input('payment_type'),
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        try {
            $invitation = Invitation::where('order_id', $orderId)->first();

            if (! $invitation) {
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

            if (! $signatureValid) {
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

            if (! $this->grossAmountMatches($invitation, $grossAmount)) {
                Log::warning('Rejected Midtrans webhook with gross amount mismatch', [
                    'order_id' => $orderId,
                    'expected_gross_amount' => $this->expectedGrossAmount($invitation),
                    'midtrans_gross_amount' => $grossAmount,
                ]);

                return response()->json(['message' => 'Gross amount mismatch'], 422);
            }

            if (in_array($invitation->payment_status, ['paid', 'failed', 'expired']) && in_array($transactionStatus, ['capture', 'settlement'])) {
                Log::info('Duplicate webhook for already-terminal invitation', [
                    'order_id' => $orderId,
                    'invitation_id' => $invitation->id,
                    'current_status' => $invitation->payment_status,
                ]);

                return response()->json(['message' => 'Already processed'], 200);
            }

            DB::transaction(function () use ($invitation, $transactionStatus, $transactionId, $midtransService, $request, $orderId, $grossAmount) {
                $paymentStatus = $midtransService->getPaymentStatusFromTransactionStatus($transactionStatus);
                $snapshot = $invitation->package_features_snapshot ?? [];

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
                            'status' => 'Sudah Bayar',
                            'kd_status' => 'SB',
                        ]);
                    }
                }

                PaymentLog::create([
                    'user_id' => $invitation->user_id,
                    'invitation_id' => $invitation->id,
                    'order_id' => $orderId,
                    'midtrans_transaction_id' => $transactionId,
                    'event_type' => 'webhook_processed',
                    'transaction_status' => $transactionStatus,
                    'payment_type' => $request->input('payment_type'),
                    'gross_amount' => $grossAmount,
                    'signature_valid' => true,
                    'ip_address' => $request->ip(),
                    'notes' => "Payment status updated to: {$paymentStatus}",
                ]);
            });

            Log::info('Webhook processed successfully', [
                'order_id' => $orderId,
                'transaction_status' => $transactionStatus,
                'payment_status' => $invitation->payment_status,
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

    private function generateUniqueOrderId(Invitation $invitation): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $orderId = sprintf(
                'INV-%d-%s',
                $invitation->id,
                Str::upper(Str::random(16))
            );

            if (! Invitation::where('order_id', $orderId)->exists()) {
                return $orderId;
            }
        }

        throw new \RuntimeException('Unable to generate a unique payment order ID.');
    }

    private function expectedGrossAmount(Invitation $invitation): int
    {
        $amount = $invitation->package_price_snapshot ?? $invitation->paketUndangan?->price;

        if (! is_numeric($amount) || (float) $amount <= 0 || floor((float) $amount) !== (float) $amount) {
            throw new \RuntimeException('Invoice gross amount must be a positive integer in IDR.');
        }

        return (int) $amount;
    }

    private function grossAmountMatches(Invitation $invitation, mixed $midtransAmount): bool
    {
        return is_numeric($midtransAmount)
            && abs($this->expectedGrossAmount($invitation) - (float) $midtransAmount) < 0.01;
    }
}
