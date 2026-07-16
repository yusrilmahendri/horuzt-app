<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAccountIsVerified
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe() || $this->isExempt($request) || $request->user()?->isAccountVerified()) {
            return $next($request);
        }

        return response()->json(['status' => 403, 'code' => 'ACCOUNT_NOT_VERIFIED',
            'message' => 'Verifikasi email terlebih dahulu sebelum memilih paket dan metode pembayaran.',
            'data' => ['verification_required' => true]], 403);
    }

    private function isExempt(Request $request): bool
    {
        return $request->is('api/v1/auth/verification/*', 'api/v1/logout', 'api/profile*',
            'api/v1/user-profile', 'api/v1/submission-update/user-profile', 'api/v1/midtrans/*',
            'api/v1/user/tagihan*', 'api/v1/user/upgrade-package*');
    }
}
