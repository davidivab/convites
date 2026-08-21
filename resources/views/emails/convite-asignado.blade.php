<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Te asignaron un convite</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f1ec;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#2d2a26;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f1ec;padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background-color:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:32px 40px 0 40px;">
                            <p style="margin:0;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#8a6d3b;">Convites</p>
                            <h1 style="margin:12px 0 0 0;font-family:Georgia,'Times New Roman',serif;font-size:24px;line-height:1.3;color:#2d2a26;">
                                Te asignaron un convite
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 40px 0 40px;font-size:15px;line-height:1.7;color:#4a453e;">
                            <p style="margin:0 0 16px 0;">
                                Hola {{ $user->name }}, el equipo de Convites te asignó como responsable del convite
                                <strong>"{{ $iniciativa->titulo }}"</strong>.
                            </p>
                            <p style="margin:0 0 16px 0;">
                                Desde ahora puedes editarlo, revisar aportes y gestionar el avance desde tu panel de
                                creador.
                            </p>
                            <p style="margin:0 0 24px 0;">
                                <a href="{{ $editarUrl }}" style="display:inline-block;padding:12px 18px;background-color:#2d6a4f;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;">
                                    Abrir el convite
                                </a>
                            </p>
                            <p style="margin:0 0 16px 0;font-size:14px;color:#8a6d3b;">
                                También puedes ir a tu panel: <a href="{{ $panelUrl }}" style="color:#2d6a4f;">{{ $panelUrl }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 40px 32px 40px;">
                            <p style="margin:24px 0 0 0;font-size:14px;color:#8a6d3b;">
                                Gracias,<br>El equipo de Convites
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
