<?php

namespace App\Support;

use App\Models\Iniciativa;
use Carbon\CarbonInterface;

/**
 * Genera un .ics de día completo para el convite (Apple / Google / Outlook).
 */
final class ConviteCalendarIcs
{
    public static function forIniciativa(Iniciativa $iniciativa, ?string $descripcionExtra = null): ?string
    {
        $fecha = $iniciativa->fecha_convite;
        if (! $fecha instanceof CarbonInterface) {
            return null;
        }

        $iniciativa->loadMissing(['municipio.departamento']);

        $dtStart = $fecha->format('Ymd');
        $dtEnd = $fecha->copy()->addDay()->format('Ymd');
        $stamp = now('UTC')->format('Ymd\THis\Z');
        $uid = 'convite-'.$iniciativa->id.'-'.$dtStart.'@convites.co';

        $ciudad = null;
        if ($iniciativa->municipio) {
            $ciudad = $iniciativa->municipio->departamento
                ? $iniciativa->municipio->nombre.', '.$iniciativa->municipio->departamento->nombre
                : $iniciativa->municipio->nombre;
        }

        $lugar = $iniciativa->lugar_exacto ?: $iniciativa->lugar_convite;
        $location = collect([$lugar, $ciudad])->filter()->implode(', ');

        $frontend = rtrim((string) (explode(',', (string) env('FRONTEND_URL', 'https://convites.co'))[0] ?? 'https://convites.co'), '/');
        $url = $frontend.'/iniciativa/'.$iniciativa->slug;

        $fechaTexto = $iniciativa->fecha_convite_texto
            ?: $fecha->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

        $description = "Nos vemos en el convite el {$fechaTexto}. Lugar: {$location}.";
        if ($descripcionExtra) {
            $description .= ' '.$descripcionExtra;
        }
        $description .= ' '.$url;

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Convites//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$stamp,
            'DTSTART;VALUE=DATE:'.$dtStart,
            'DTEND;VALUE=DATE:'.$dtEnd,
            'SUMMARY:'.self::escape('Convite: '.$iniciativa->titulo),
            $location !== '' ? 'LOCATION:'.self::escape($location) : null,
            'DESCRIPTION:'.self::escape($description),
            'URL:'.$url,
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_values(array_filter($lines, static fn ($l) => $l !== null)))."\r\n";
    }

    private static function escape(string $value): string
    {
        return str_replace(
            ["\\", ';', ',', "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', ''],
            $value,
        );
    }
}
