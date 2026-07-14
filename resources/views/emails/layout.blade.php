<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $title ?? 'Sena Digital' }}</title>
</head>
<body style="margin:0; padding:0; background:#fff5f8; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    @isset($preheader)
        <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">
            {{ $preheader }}
        </div>
    @endisset

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width:100%; background:#fff5f8; margin:0; padding:0;">
        <tr>
            <td align="center" style="padding:32px 14px;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width:100%; max-width:600px; margin:0 auto;">
                    <tr>
                        <td align="center" style="padding:10px 0 22px;">
                            <div style="font-size:28px; line-height:1.2; font-weight:800; color:#db4f91; letter-spacing:0;">
                                Sena Digital
                            </div>
                            <div style="margin-top:8px; font-size:13px; line-height:1.5; color:#64748b;">
                                Undangan digital elegan untuk momen bahagia Anda
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#ffffff; border-radius:18px; padding:34px 28px; box-shadow:0 16px 38px rgba(219,79,145,0.14);">
                            @yield('content')

                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:28px;">
                                <tr>
                                    <td style="border-top:1px solid #f7d6e7; padding-top:22px;">
                                        <p style="margin:0; font-size:15px; line-height:1.7; color:#1f2937;">
                                            Salam,<br>
                                            <strong>Sena Digital</strong>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @hasSection('fallback')
                        <tr>
                            <td style="padding:18px 8px 0;">
                                @yield('fallback')
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td align="center" style="padding:24px 10px 0;">
                            <p style="margin:0 0 8px; font-size:13px; line-height:1.6; color:#64748b;">
                                Email ini dikirim otomatis oleh Sena Digital.
                            </p>
                            <p style="margin:0; font-size:13px; line-height:1.6; color:#64748b;">
                                &copy; 2026 Sena Digital. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
