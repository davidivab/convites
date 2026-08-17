<?php

namespace App\Enums;

/**
 * Áreas de "Manos profesionales" (voluntariado especializado gratuito).
 */
enum AreaProfesional: string
{
    case Psicologia = 'psicologia';
    case Legal = 'legal';
    case Arquitectura = 'arquitectura';
    case Nutricion = 'nutricion';
    case Salud = 'salud';

    public function label(): string
    {
        return match ($this) {
            self::Psicologia => 'Apoyo psicológico',
            self::Legal => 'Asesoría legal',
            self::Arquitectura => 'Arquitectura e ingeniería civil',
            self::Nutricion => 'Nutrición',
            self::Salud => 'Salud y primeros auxilios',
        };
    }
}
