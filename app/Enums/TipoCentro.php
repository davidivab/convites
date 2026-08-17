<?php

namespace App\Enums;

/**
 * Tipos de centro de interés durante la emergencia / reconstrucción.
 */
enum TipoCentro: string
{
    case Acopio = 'acopio';
    case Albergue = 'albergue';
    case Bomberos = 'bomberos';
    case Hospital = 'hospital';
    case Policia = 'policia';
    case DefensaCivil = 'defensa_civil';
    case Censo = 'censo';

    public function label(): string
    {
        return match ($this) {
            self::Acopio => 'Centro de acopio',
            self::Albergue => 'Albergue',
            self::Bomberos => 'Bomberos',
            self::Hospital => 'Hospital / centro de salud',
            self::Policia => 'Policía',
            self::DefensaCivil => 'Defensa Civil',
            self::Censo => 'Censo de afectaciones',
        };
    }
}
