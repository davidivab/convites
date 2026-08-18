<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Confirmamos que recibimos tu aporte</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f1ec;font-family:Georgia,'Times New Roman',serif;color:#2d2a26;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f1ec;padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background-color:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:32px 40px 0 40px;">
                            <p style="margin:0;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#8a6d3b;">Convites</p>
                            <h1 style="margin:12px 0 0 0;font-size:24px;line-height:1.3;color:#2d2a26;">
                                ¡Gracias por tu aporte!
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 40px 0 40px;font-size:15px;line-height:1.7;color:#4a453e;">
                            <p style="margin:0 0 16px 0;">
                                Hola {{ $aporte->user->name }}, el organizador de <strong>"{{ $iniciativa->titulo }}"</strong>
                                confirmó que recibió tu aporte.
                            </p>
                            <p style="margin:0 0 16px 0;">
                                Gracias de verdad por cumplir tu palabra — gente como vos es la que hace que estas
                                causas salgan adelante.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 40px 32px 40px;">
                            <p style="margin:24px 0 0 0;font-size:14px;color:#8a6d3b;">
                                Con gratitud,<br>El equipo de Convites
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
