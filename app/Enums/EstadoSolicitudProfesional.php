<?php

namespace App\Enums;

/**
 * Estado de una solicitud de contacto a profesional.
 */
enum EstadoSolicitudProfesional: string
{
    case Pendiente = 'pendiente';
    case Notificada = 'notificada';
    case Atendida = 'atendida';
    case Negada = 'negada';
    case Trasladada = 'trasladada';
    case NoContesta = 'no_contesta';
    case Spam = 'spam';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Notificada => 'Notificada',
            self::Atendida => 'Atendida',
            self::Negada => 'Negada',
            self::Trasladada => 'Trasladada',
            self::NoContesta => 'No contesta',
            self::Spam => 'Spam',
        };
    }
}
