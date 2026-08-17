<?php

namespace App\Enums;

/**
 * Ciclo de vida de una iniciativa / convite.
 *
 * Flujo típico:
 *  borrador → en_revision → publicada → en_curso → cerrada
 *  en_revision → rechazada (moderación)
 */
enum EstadoIniciativa: string
{
    case Borrador = 'borrador';
    case EnRevision = 'en_revision';
    case Publicada = 'publicada';
    case EnCurso = 'en_curso';
    case Cerrada = 'cerrada';
    case Rechazada = 'rechazada';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::EnRevision => 'En revisión',
            self::Publicada => 'Publicada',
            self::EnCurso => 'En curso',
            self::Cerrada => 'Cerrada',
            self::Rechazada => 'Rechazada',
        };
    }
}
