@extends('emails.layout', [
    'title' => 'Reset Kata Sandi - Sena Digital',
    'preheader' => 'Kami menerima permintaan untuk mereset kata sandi akun Anda.',
])

@section('content')
    <h1 style="margin:0 0 14px; font-size:28px; line-height:1.25; font-weight:800; color:#1f2937;">
        Reset Kata Sandi
    </h1>

    <p style="margin:0 0 26px; font-size:16px; line-height:1.7; color:#64748b;">
        Kami menerima permintaan untuk mereset kata sandi akun Anda.
    </p>

    @isset($code)
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 24px;">
            <tr>
                <td align="center" style="background:#fdf2f8; border:2px dashed #ec6aa5; border-radius:14px; padding:22px 16px;">
                    <div style="font-size:34px; line-height:1.2; font-weight:800; letter-spacing:8px; color:#db4f91; font-family:Arial, Helvetica, sans-serif;">
                        {{ $code }}
                    </div>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 24px; font-size:14px; line-height:1.7; color:#64748b;">
            Kode ini akan kedaluwarsa{{ isset($expireMinutes) ? ' dalam '.$expireMinutes.' menit' : '' }} dan hanya dapat digunakan satu kali.
        </p>
    @endisset

    @isset($resetUrl)
        <table align="center" cellpadding="0" cellspacing="0" role="presentation" style="margin:28px auto;">
            <tr>
                <td align="center" bgcolor="#db4f91" style="border-radius:14px; box-shadow:0 12px 24px rgba(219,79,145,0.22);">
                    <a href="{{ $resetUrl }}" style="display:inline-block; padding:15px 30px; font-size:16px; line-height:1.2; font-weight:700; color:#ffffff; text-decoration:none; border-radius:14px;">
                        Reset Kata Sandi
                    </a>
                </td>
            </tr>
        </table>
    @endisset

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:24px;">
        <tr>
            <td style="background:#fdf2f8; border:1px solid #f7d6e7; border-radius:14px; padding:16px;">
                <p style="margin:0; font-size:14px; line-height:1.7; color:#64748b;">
                    Jika Anda tidak meminta reset kata sandi, abaikan email ini.
                </p>
            </td>
        </tr>
    </table>
@endsection

@isset($resetUrl)
    @section('fallback')
        <p style="margin:0 0 8px; font-size:12px; line-height:1.6; color:#64748b; text-align:center;">
            Jika tombol tidak bisa diklik, salin dan buka link berikut:
        </p>
        <p style="margin:0; font-size:12px; line-height:1.6; color:#db4f91; word-break:break-all; text-align:center;">
            {{ $resetUrl }}
        </p>
    @endsection
@endisset
