@extends('emails.layout', [
    'title' => 'Kode Verifikasi Akun - Sena Digital',
    'preheader' => 'Gunakan kode berikut untuk memverifikasi akun Anda.',
])

@section('content')
    <h1 style="margin:0 0 14px; font-size:28px; line-height:1.25; font-weight:800; color:#1f2937;">
        Kode Verifikasi Akun
    </h1>

    <p style="margin:0 0 26px; font-size:16px; line-height:1.7; color:#64748b;">
        Gunakan kode berikut untuk memverifikasi akun Anda.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 24px;">
        <tr>
            <td align="center" style="background:#fdf2f8; border:2px dashed #ec6aa5; border-radius:14px; padding:22px 16px;">
                <div style="font-size:34px; line-height:1.2; font-weight:800; letter-spacing:8px; color:#db4f91; font-family:Arial, Helvetica, sans-serif;">
                    {{ $code }}
                </div>
            </td>
        </tr>
    </table>

    <p style="margin:0; font-size:14px; line-height:1.7; color:#64748b;">
        Kode ini akan kedaluwarsa{{ isset($expireMinutes) ? ' dalam '.$expireMinutes.' menit' : '' }} dan hanya dapat digunakan satu kali.
    </p>
@endsection
