<?php

namespace App\Enums;

/**
 * Aptitud física autodeclarada (perfil de aportante).
 */
enum AptitudFisica: string
{
    case Alta = 'alta';
    case Media = 'media';
    case Baja = 'baja';

    public function label(): string
    {
        return match ($this) {
            self::Alta => 'Puedo hacer trabajo físico pesado',
            self::Media => 'Trabajo físico moderado',
            self::Baja => 'Mejor apoyo no físico',
        };
    }
}
