<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerificationCodeNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $code, private readonly string $purpose) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reset = $this->purpose === 'password_reset';
        $resetUrl = rtrim((string) config('verification.frontend_url'), '/').'/reset-password?token='.urlencode($this->code).'&email='.urlencode((string) $notifiable->email);

        return (new MailMessage)
            ->subject($reset ? 'Reset Kata Sandi - Sena Digital' : 'Kode Verifikasi Akun - Sena Digital')
            ->view($reset ? 'emails.reset-password' : 'emails.verification-code', [
                'code' => $this->code,
                'email' => $notifiable->email,
                'expireMinutes' => config('verification.email_token_ttl_minutes'),
                'resetUrl' => $reset ? $resetUrl : null,
            ]);
    }
}
