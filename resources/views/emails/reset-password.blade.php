<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password Sena Digital</title>
</head>
<body style="margin:0; padding:0; background:#f7edf4; font-family:Arial, Helvetica, sans-serif; color:#2f2430;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f7edf4; padding:40px 16px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;">
                    <tr>
                        <td align="center" style="padding-bottom:24px;">
                            <img src="https://sena-digital.com/landing/logo.png"
                                 alt="Sena Digital"
                                 width="160"
                                 style="display:block; margin:0 auto; max-width:160px; height:auto;" />
                            <div style="font-size:13px; color:#9b7890; margin-top:12px;">
                                Undangan digital elegan untuk hari bahagiamu
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#ffffff; border-radius:24px; padding:40px 34px; box-shadow:0 18px 45px rgba(122,47,104,0.12);">
                            <div style="display:inline-block; background:#f7edf4; color:#7A2F68; padding:8px 14px; border-radius:999px; font-size:13px; font-weight:600;">
                                Permintaan reset kata sandi
                            </div>

                            <h1 style="margin:24px 0 12px; font-size:30px; line-height:1.25; color:#2f2430;">
                                Atur Ulang Kata Sandimu
                            </h1>

                            <p style="margin:0; font-size:16px; line-height:1.7; color:#6f6270;">
                                Kami menerima permintaan untuk mengatur ulang kata sandi akun Sena Digital milikmu.
                                Klik tombol di bawah ini untuk membuat kata sandi baru.
                            </p>

                            <div style="text-align:center; margin:34px 0;">
                                <a href="{{ $resetUrl }}"
                                   style="display:inline-block; background:#7A2F68; color:#ffffff; text-decoration:none; padding:15px 34px; border-radius:999px; font-size:16px; font-weight:700; box-shadow:0 12px 24px rgba(122,47,104,0.25);">
                                    Reset Kata Sandi
                                </a>
                            </div>

                            <p style="margin:0 0 16px; font-size:14px; line-height:1.7; color:#7b6b78; text-align:center;">
                                Tautan ini berlaku selama {{ $expireMinutes }} menit.
                            </p>

                            <div style="background:#fbf5f9; border:1px solid #efd9e8; border-radius:18px; padding:18px; margin-top:26px;">
                                <p style="margin:0; font-size:14px; line-height:1.7; color:#7b6b78;">
                                    Jika kamu tidak merasa meminta reset kata sandi, abaikan email ini.
                                    Akunmu tetap aman dan kata sandi tidak akan berubah.
                                </p>
                            </div>

                            <p style="margin:28px 0 8px; font-size:13px; line-height:1.6; color:#9b8b98;">
                                Jika tombol tidak berfungsi, salin dan tempel tautan berikut ke browser:
                            </p>

                            <p style="word-break:break-all; margin:0; font-size:12px; line-height:1.6; color:#7A2F68;">
                                {{ $resetUrl }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:24px 12px 0;">
                            <p style="margin:0; font-size:13px; color:#9b7890;">
                                &copy; {{ date('Y') }} Sena Digital. Semua hak dilindungi.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>