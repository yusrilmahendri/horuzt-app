<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\VerificationCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AccountVerificationController extends Controller
{
    public function __construct(private readonly VerificationCodeService $codes) {}

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate(['channel' => ['required', 'in:email,whatsapp']]);
        $user = $request->user();
        if ($data['channel'] === 'whatsapp' && ! $user->phone) {
            return $this->error(422, 'Nomor WhatsApp belum tersedia.', 'WHATSAPP_REQUIRED');
        }
        $user->forceFill(['verification_channel' => $data['channel']])->save();
        try {
            $this->codes->issue($user, $data['channel'], 'account_verification');
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'RESEND_LIMIT_REACHED') {
                return $this->error(429, 'Tunggu sebelum mengirim ulang kode.', 'RESEND_LIMIT_REACHED');
            }

            return $this->error(503, 'Kode verifikasi gagal dikirim. Silakan coba lagi.', 'DELIVERY_FAILED');
        }

        return response()->json(['status' => 200, 'message' => 'Kode verifikasi berhasil dikirim.', 'data' => ['channel' => $data['channel']]]);
    }

    public function resend(Request $request): JsonResponse
    {
        return $this->send($request);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['channel' => ['required', 'in:email,whatsapp'], 'code' => ['required', 'digits:6']]);
        $result = $this->codes->verify($request->user(), $data['channel'], 'account_verification', $data['code']);
        if ($result !== 'valid') {
            return $this->verificationError($result);
        }
        DB::transaction(function () use ($request, $data) {
            $field = $data['channel'] === 'email' ? 'email_verified_at' : 'whatsapp_verified_at';
            $request->user()->forceFill([$field => now(), 'verification_channel' => $data['channel']])->save();
        });

        return response()->json(['status' => 200, 'message' => 'Akun berhasil diverifikasi.', 'data' => ['is_verified' => true]]);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $channel = $user->verification_channel ?: 'email';
        $seconds = $this->codes->resendAvailableIn($user, $channel, 'account_verification');

        return response()->json(['status' => 200, 'message' => 'Status verifikasi berhasil diambil.', 'data' => [
            'is_verified' => $user->isAccountVerified(), 'verification_channel' => $channel,
            'email_verified' => $user->isEmailVerified(), 'whatsapp_verified' => $user->isWhatsappVerified(),
            'masked_destination' => $channel === 'email' ? $this->maskEmail($user->email) : $this->maskPhone((string) $user->phone),
            'can_resend' => $seconds === 0, 'resend_available_in' => $seconds,
        ]]);
    }

    private function verificationError(string $result): JsonResponse
    {
        return match ($result) {
            'expired' => $this->error(422, 'Kode verifikasi sudah kedaluwarsa.', 'VERIFICATION_CODE_EXPIRED'),
            'attempts_exceeded' => $this->error(429, 'Batas percobaan verifikasi terlampaui.', 'VERIFICATION_ATTEMPTS_EXCEEDED'),
            default => $this->error(422, 'Kode verifikasi tidak valid.', 'VERIFICATION_CODE_INVALID'),
        };
    }

    private function error(int $status, string $message, string $code): JsonResponse
    {
        return response()->json(['status' => $status, 'code' => $code, 'message' => $message, 'data' => []], $status);
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($name, 0, 2).str_repeat('*', max(3, strlen($name) - 2)).'@'.$domain;
    }

    private function maskPhone(string $phone): string
    {
        return strlen($phone) < 5 ? '***' : substr($phone, 0, 2).str_repeat('*', max(4, strlen($phone) - 5)).substr($phone, -3);
    }
}
