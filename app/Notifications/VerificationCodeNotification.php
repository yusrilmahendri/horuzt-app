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

        $message = (new MailMessage)->subject($reset ? 'Kode Reset Kata Sandi' : 'Kode Verifikasi Akun')
            ->greeting('Halo!')->line($reset ? 'Gunakan kode berikut untuk mereset kata sandi Anda.' : 'Gunakan kode berikut untuk memverifikasi akun Anda.')
            ->line($this->code)->line('Kode ini akan kedaluwarsa dan hanya dapat digunakan satu kali.');

        if ($reset) {
            $message->action('Reset Kata Sandi', rtrim((string) config('verification.frontend_url'), '/').'/reset-password?token='.urlencode($this->code).'&email='.urlencode((string) $notifiable->email));
        }

        return $message;
    }
}
