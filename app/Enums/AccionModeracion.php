<?php

namespace App\Enums;

/**
 * Acciones registradas en la bitácora de moderación.
 */
enum AccionModeracion: string
{
    case EnviarRevision = 'enviar_revision';
    case Aprobar = 'aprobar';
    case Rechazar = 'rechazar';
    case SolicitarCambios = 'solicitar_cambios';
    case Publicar = 'publicar';
    case Cerrar = 'cerrar';

    public function label(): string
    {
        return match ($this) {
            self::EnviarRevision => 'Enviar a revisión',
            self::Aprobar => 'Aprobar',
            self::Rechazar => 'Rechazar',
            self::SolicitarCambios => 'Solicitar cambios',
            self::Publicar => 'Publicar',
            self::Cerrar => 'Cerrar',
        };
    }
}
