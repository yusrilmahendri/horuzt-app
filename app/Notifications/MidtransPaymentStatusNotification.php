<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MidtransPaymentStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $status,
        private readonly array $payload
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function status(): string
    {
        return $this->status;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoiceCode = $this->payload['invoice_code'] ?? '-';
        $subject = match ($this->status) {
            'paid' => "Pembayaran Sena Digital Berhasil - {$invoiceCode}",
            'expired' => "Tagihan Sena Digital Tidak Dapat Dilanjutkan - {$invoiceCode}",
            default => "Tagihan Sena Digital Menunggu Pembayaran - {$invoiceCode}",
        };

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('Halo '.trim((string) ($notifiable->name ?: 'Pelanggan Sena Digital')).',');

        if ($this->status === 'paid') {
            return $mail
                ->line('Pembayaran Anda telah berhasil dikonfirmasi.')
                ->line('Nomor invoice: '.$invoiceCode)
                ->line('Paket: '.($this->payload['package_name'] ?? '-'))
                ->line('Total: '.$this->formatRupiah($this->payload['gross_amount'] ?? null))
                ->action('Masuk Dashboard', $this->payload['continue_url'] ?? url('/dashboard'))
                ->line('Terima kasih telah menggunakan Sena Digital.');
        }

        if ($this->status === 'expired') {
            return $mail
                ->line('Transaksi pembayaran Anda tidak dapat dilanjutkan karena statusnya sudah kedaluwarsa, dibatalkan, atau ditolak.')
                ->line('Nomor invoice: '.$invoiceCode)
                ->line('Paket: '.($this->payload['package_name'] ?? '-'))
                ->line('Metode: '.$this->methodLabel($this->payload['payment_type'] ?? null))
                ->action('Pilih Metode Pembayaran', $this->payload['continue_url'] ?? url('/pilih-paket'))
                ->line('Silakan pilih kembali metode pembayaran untuk melanjutkan.');
        }

        $mail
            ->line('Tagihan Anda telah dibuat dan saat ini menunggu pembayaran.')
            ->line('Nomor invoice: '.$invoiceCode)
            ->line('Paket: '.($this->payload['package_name'] ?? '-'))
            ->line('Metode: '.$this->methodLabel($this->payload['payment_type'] ?? null))
            ->line('Total: '.$this->formatRupiah($this->payload['gross_amount'] ?? null))
            ->line('Batas pembayaran: '.($this->payload['expiry_time'] ?? '-'));

        foreach ($this->instructionLines($this->payload['payment_type'] ?? null, $this->payload['payment_details'] ?? []) as $line) {
            $mail->line($line);
        }

        return $mail
            ->action('Lanjutkan Pembayaran', $this->payload['continue_url'] ?? url('/dashboard/payment-pending'))
            ->line('Undangan Anda akan aktif setelah pembayaran dikonfirmasi.');
    }

    private function methodLabel(?string $paymentType): string
    {
        return match ($paymentType) {
            'qris' => 'QRIS',
            'bank_transfer' => 'Virtual Account',
            'echannel' => 'Mandiri Bill',
            'cstore' => 'Convenience Store',
            'gopay' => 'GoPay',
            'shopeepay' => 'ShopeePay',
            'credit_card' => 'Kartu Kredit',
            default => $paymentType ? strtoupper(str_replace('_', ' ', $paymentType)) : '-',
        };
    }

    private function instructionLines(?string $paymentType, array $details): array
    {
        return match ($paymentType) {
            'qris' => ['Buka kembali halaman pembayaran dan pindai kode QR menggunakan aplikasi pembayaran yang mendukung QRIS.'],
            'bank_transfer' => $this->bankTransferLines($details),
            'echannel' => array_filter([
                'Gunakan Mandiri Bill Payment untuk menyelesaikan pembayaran.',
                isset($details['bill_key']) ? 'Bill key: '.$details['bill_key'] : null,
                isset($details['biller_code']) ? 'Biller code: '.$details['biller_code'] : null,
            ]),
            'cstore' => array_filter([
                'Lakukan pembayaran di gerai yang dipilih.',
                isset($details['store']) ? 'Gerai: '.strtoupper((string) $details['store']) : null,
                isset($details['payment_code']) ? 'Kode pembayaran: '.$details['payment_code'] : null,
            ]),
            'gopay', 'shopeepay' => ['Buka kembali halaman pembayaran dan lanjutkan melalui aplikasi e-wallet Anda.'],
            default => ['Buka kembali halaman pembayaran untuk melihat instruksi pembayaran.'],
        };
    }

    private function bankTransferLines(array $details): array
    {
        $lines = ['Lakukan pembayaran ke nomor Virtual Account berikut.'];

        if (isset($details['bank'])) {
            $lines[] = 'Bank: '.strtoupper((string) $details['bank']);
        }

        if (isset($details['va_number'])) {
            $lines[] = 'Nomor Virtual Account: '.$details['va_number'];
        }

        if (isset($details['permata_va_number'])) {
            $lines[] = 'Nomor Virtual Account Permata: '.$details['permata_va_number'];
        }

        return $lines;
    }

    private function formatRupiah($amount): string
    {
        if (! is_numeric($amount)) {
            return '-';
        }

        return 'Rp'.number_format((float) $amount, 0, ',', '.');
    }
}
