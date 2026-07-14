<?php

namespace App\Http\Middleware;

use App\Services\AccountStatusService;
use Closure;
use Illuminate\Http\Request;

class EnsureInvitationFeatureAccess
{
    public function __construct(private AccountStatusService $accountStatusService)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $summary = $this->accountStatusService->summary($user);

        if ($summary['account_status'] === AccountStatusService::STATUS_PENDING_PAYMENT) {
            return $this->blocked('PAYMENT_NOT_CONFIRMED', 'Pembayaran belum dikonfirmasi.', $summary);
        }

        if ($summary['account_status'] === AccountStatusService::STATUS_ONBOARDING) {
            return $this->blocked('PAYMENT_NOT_CONFIRMED', 'Pembayaran belum dikonfirmasi.', $summary);
        }

        if ($summary['account_status'] === AccountStatusService::STATUS_EXPIRED) {
            return $this->blocked('ACCOUNT_EXPIRED', 'Masa aktif akun sudah berakhir.', $summary);
        }

        if ($summary['account_status'] === AccountStatusService::STATUS_UNVERIFIED) {
            return $this->blocked('ACCOUNT_NOT_VERIFIED', 'Verifikasi akun terlebih dahulu sebelum mengisi data undangan.', $summary);
        }

        return $next($request);
    }

    private function blocked(string $code, string $message, array $summary)
    {
        return response()->json([
            'status' => 403,
            'code' => $code,
            'message' => $message,
            'data' => [
                'account_status' => $summary['account_status'],
                'payment_status' => $summary['payment_status'],
                'has_invoice' => $summary['has_invoice'],
                'has_pending_invoice' => $summary['has_pending_invoice'],
                'invoice_id' => $summary['invoice_id'],
                'invoice_code' => $summary['invoice_code'],
                'kode_pemesanan' => $summary['kode_pemesanan'],
                'package_name' => $summary['package_name'],
                'package_code' => $summary['package_code'],
                'active_until' => $summary['active_until'],
                'remaining_days' => $summary['remaining_days'],
                'is_payment_confirmed' => $summary['is_payment_confirmed'],
                'is_expired' => $summary['is_expired'],
                'feature_access' => $summary['feature_access'],
            ],
        ], 403);
    }
}
