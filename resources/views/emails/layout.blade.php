<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Stylebite') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f7fb; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f5f7fb; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px; background-color:#ffffff; border-radius:18px; overflow:hidden;">
                    <tr>
                        <td style="background:linear-gradient(135deg, #ff7a59 0%, #ffb347 100%); padding:28px 32px;">
                            <h1 style="margin:0; font-size:28px; line-height:1.2; color:#ffffff;">Stylebite</h1>
                            <p style="margin:8px 0 0; font-size:14px; line-height:1.5; color:#fff3eb;">Fashion, food, memories, and community in one place.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 32px; background-color:#fff8f3; border-top:1px solid #fde7db;">
                            <p style="margin:0; font-size:13px; line-height:1.6; color:#6b7280;">
                                You received this email from {{ config('app.name', 'Stylebite') }}.
                                If you did not expect it, you can safely ignore this message.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
