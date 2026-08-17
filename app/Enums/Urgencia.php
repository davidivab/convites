<?php

namespace App\Enums;

/**
 * Nivel de urgencia mostrado en listados y detalle de iniciativas.
 */
enum Urgencia: string
{
    case Alta = 'alta';
    case Media = 'media';
    case Baja = 'baja';

    public function label(): string
    {
        return match ($this) {
            self::Alta => 'Urgencia alta',
            self::Media => 'Urgencia media',
            self::Baja => 'Sin prisa',
        };
    }
}
