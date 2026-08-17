<?php

namespace App\Enums;

/**
 * Modalidad de atención del perfil profesional.
 */
enum ModalidadProfesional: string
{
    case Presencial = 'presencial';
    case Virtual = 'virtual';
    case Ambas = 'presencial_y_virtual';

    public function label(): string
    {
        return match ($this) {
            self::Presencial => 'Presencial',
            self::Virtual => 'Virtual',
            self::Ambas => 'Presencial y virtual',
        };
    }
}
