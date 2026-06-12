<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomResetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * The password reset token.
     */
    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Notification delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation using the custom Sena Digital template.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Atur Ulang Kata Sandi Akun Sena Digital')
            ->view('emails.reset-password', [
                'resetUrl' => $this->resetUrl($notifiable),
                'user' => $notifiable,
                'expireMinutes' => config('auth.passwords.users.expire'),
            ]);
    }

    /**
     * Build the frontend (Angular) reset password URL.
     */
    private function resetUrl($notifiable): string
    {
        return rtrim(config('app.frontend_url'), '/')
            . '/reset-password?token=' . $this->token
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
    }
}
