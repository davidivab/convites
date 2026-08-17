<?php

namespace App\Enums;

/**
 * Acciones de moderación sobre perfiles profesionales.
 */
enum AccionModeracionProfesional: string
{
    case Aprobar = 'aprobar';
    case Rechazar = 'rechazar';
    case SolicitarCambios = 'solicitar_cambios';
    case Reenviar = 'reenviar';

    public function label(): string
    {
        return match ($this) {
            self::Aprobar => 'Aprobar',
            self::Rechazar => 'Rechazar',
            self::SolicitarCambios => 'Solicitar cambios',
            self::Reenviar => 'Reenviar a revisión',
        };
    }
}
