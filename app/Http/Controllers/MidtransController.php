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
        // Removed auth middleware - now using user_id query param
    }

    public function createSnapToken(CreateSnapTokenRequest $request): JsonResponse
    {
        try {
            // Get user from query parameter instead of auth
            $userId = $request->query('user_id');
            $user = \App\Models\User::findOrFail($userId);

            $validated = $request->validated();

            $invitation = Invitation::with('paketUndangan')->findOrFail($validated['invitation_id']);

            $orderId = 'INV-' . Str::uuid()->toString();
            $grossAmount = $validated['amount'];

            $customerDetails = $validated['customer_details'] ?? [
                'first_name' => $user->name ?? 'Guest',
                'last_name' => '',
                'email' => $user->email ?? 'guest@example.com',
                'phone' => $user->phone ?? '',
            ];

            $itemDetails = $validated['item_details'] ?? [[
                'id' => 'paket-' . $invitation->paket_undangan_id,
                'name' => $invitation->paketUndangan->name_paket ?? 'Wedding Package',
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
                    'finish' => config('midtrans.frontend_finish_url', env('APP_URL') . '/payment/success'),
                    'error' => config('midtrans.frontend_error_url', env('APP_URL') . '/payment/error'),
                    'pending' => config('midtrans.frontend_pending_url', env('APP_URL') . '/payment/pending'),
                ],
            ];

            $midtransService = new MidtransService($user->id);
            $snapToken = $midtransService->createTransaction($params);

            DB::transaction(function () use ($invitation, $orderId, $grossAmount, $user, $params, $snapToken) {
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
                    'response_payload' => json_encode(['snap_token' => $snapToken]),
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
                'message' => 'An unexpected error occurred. Please try again later.',
            ], 500);
        }
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

            $midtransService = new MidtransService($invitation->user_id);
            $midtransService->configureMidtrans();

            $status = \Midtrans\Transaction::status($orderId);

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
                    $updateData = [
                        'payment_status' => 'paid',
                        'midtrans_transaction_id' => $status->transaction_id,
                        'payment_confirmed_at' => now(),
                    ];

                    if ($invitation->paketUndangan && $invitation->paketUndangan->masa_aktif) {
                        $updateData['domain_expires_at'] = now()->addMonths($invitation->paketUndangan->masa_aktif);
                    }

                    $invitation->update($updateData);
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
            Log::error('Midtrans API error during status check', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status from Midtrans',
                'error' => $e->getMessage(),
            ], 503);

        } catch (\Exception $e) {
            Log::error('Payment status check failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status',
            ], 500);
        }
    }

    public function handleWebhook(Request $request): JsonResponse
    {
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

            if ($invitation->payment_status === 'paid' && in_array($transactionStatus, ['capture', 'settlement'])) {
                Log::info('Duplicate webhook for already paid invitation', [
                    'order_id' => $orderId,
                    'invitation_id' => $invitation->id,
                ]);

                return response()->json(['message' => 'Already processed'], 200);
            }

            DB::transaction(function () use ($invitation, $transactionStatus, $transactionId, $midtransService, $request, $orderId, $grossAmount) {
                $paymentStatus = $midtransService->getPaymentStatusFromTransactionStatus($transactionStatus);

                $updateData = [
                    'payment_status' => $paymentStatus,
                    'midtrans_transaction_id' => $transactionId,
                ];

                if (in_array($transactionStatus, ['capture', 'settlement'])) {
                    $updateData['payment_confirmed_at'] = now();

                    if ($invitation->paketUndangan && $invitation->paketUndangan->masa_aktif) {
                        $updateData['domain_expires_at'] = now()->addMonths($invitation->paketUndangan->masa_aktif);
                    }
                }

                $invitation->update($updateData);

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
}
