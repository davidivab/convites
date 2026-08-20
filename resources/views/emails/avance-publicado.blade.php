<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nuevo avance de la iniciativa</title>
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
                                Hay novedades en "{{ $iniciativa->titulo }}"
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 40px 0 40px;font-size:15px;line-height:1.7;color:#4a453e;">
                            <p style="margin:0 0 16px 0;">
                                Hola {{ $aportante->name }}, el organizador de <strong>"{{ $iniciativa->titulo }}"</strong>
                                publicó un nuevo avance:
                            </p>
                            <p style="margin:0 0 8px 0;font-weight:600;">
                                {{ $avance->titulo }}
                            </p>
                            @if ($avance->cuerpo)
                                <p style="margin:0 0 16px 0;">
                                    {{ $avance->cuerpo }}
                                </p>
                            @endif
                            @if (! $avance->esGeneral() && $avance->porcentaje !== null)
                                <p style="margin:0 0 16px 0;">
                                    Avance reportado: <strong>{{ $avance->porcentaje }}%</strong>
                                </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 40px 32px 40px;">
                            <p style="margin:24px 0 0 0;font-size:14px;color:#8a6d3b;">
                                Gracias por acompañar esta causa,<br>El equipo de Convites
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
