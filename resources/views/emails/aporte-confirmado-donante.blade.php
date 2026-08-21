<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Confirmamos tu compromiso</title>
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
                                Confirmamos tu compromiso
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 40px 0 40px;font-size:15px;line-height:1.7;color:#4a453e;">
                            <p style="margin:0 0 16px 0;">
                                Hola {{ $aporte->user->name }}, quedamos registrados tu apoyo al convite
                                <strong>"{{ $iniciativa->titulo }}"</strong>.
                            </p>
                            @php
                                $fechaTexto = $iniciativa->fecha_convite_texto
                                    ?: ($iniciativa->fecha_convite?->locale('es')->isoFormat('D [de] MMMM [de] YYYY'));
                                $lugar = $iniciativa->lugar_exacto ?: $iniciativa->lugar_convite;
                                $ciudad = null;
                                if ($iniciativa->municipio) {
                                    $ciudad = $iniciativa->municipio->departamento
                                        ? $iniciativa->municipio->nombre.', '.$iniciativa->municipio->departamento->nombre
                                        : $iniciativa->municipio->nombre;
                                }
                            @endphp
                            @if ($fechaTexto)
                                <p style="margin:0 0 16px 0;padding:12px 14px;background-color:#f0f7f2;border-radius:8px;color:#2d2a26;">
                                    Nos vemos en el convite el <strong>{{ $fechaTexto }}</strong>.
                                    @if ($lugar)
                                        Anota para llegar a la dirección <strong>{{ $lugar }}</strong>@if ($ciudad) en <strong>{{ $ciudad }}</strong>@endif.
                                    @endif
                                    Adjuntamos un archivo de calendario (.ics) para que lo guardes en tu agenda.
                                </p>
                            @endif
                            <p style="margin:0 0 8px 0;font-weight:600;color:#2d2a26;">Detalle de tu compromiso:</p>
                            <ul style="margin:0 0 16px 0;padding-left:20px;">
                                @forelse ($aporte->items as $linea)
                                    <li style="margin-bottom:4px;">
                                        {{ $linea->cantidad }} {{ $linea->iniciativaItem?->unidad }}
                                        {{ $linea->iniciativaItem?->nombre }}
                                    </li>
                                @empty
                                    @unless ($aporte->asiste_al_convite)
                                        <li>Sin materiales registrados</li>
                                    @endunless
                                @endforelse
                                @if ($aporte->asiste_al_convite)
                                    <li>Asistencia / apoyo con tu tiempo el día del convite</li>
                                @endif
                            </ul>
                            @if ($aporte->puntoAcopio)
                                <p style="margin:0 0 16px 0;">
                                    Punto de entrega: <strong>{{ $aporte->puntoAcopio->nombre }}</strong>
                                </p>
                            @endif
                            <p style="margin:0 0 16px 0;">
                                El organizador ya fue notificado. Si no puedes cumplir, cancela o ajusta el aporte
                                desde tu panel de ayudas.
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
