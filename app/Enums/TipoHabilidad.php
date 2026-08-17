<?php

namespace App\Enums;

/**
 * Clasificación de habilidades que un usuario puede ofrecer en un convite.
 */
enum TipoHabilidad: string
{
    case Manual = 'manual';
    case Conocimiento = 'conocimiento';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Habilidad manual / de oficio',
            self::Conocimiento => 'Conocimiento / apoyo no manual',
        };
    }
}
