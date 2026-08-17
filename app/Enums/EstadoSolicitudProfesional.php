<?php

namespace App\Enums;

/**
 * Estado de una solicitud de contacto a profesional.
 */
enum EstadoSolicitudProfesional: string
{
    case Pendiente = 'pendiente';
    case Notificada = 'notificada';
    case Respondida = 'respondida';
    case Cerrada = 'cerrada';
    case Spam = 'spam';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Notificada => 'Notificada',
            self::Respondida => 'Respondida',
            self::Cerrada => 'Cerrada',
            self::Spam => 'Spam',
        };
    }
}
