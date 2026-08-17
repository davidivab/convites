<?php

namespace App\Enums;

/**
 * Género opcional del perfil (autodeclarado).
 */
enum Genero: string
{
    case Mujer = 'mujer';
    case Hombre = 'hombre';
    case NoBinario = 'no_binario';
    case PrefieroNoDecir = 'prefiero_no_decir';

    public function label(): string
    {
        return match ($this) {
            self::Mujer => 'Mujer',
            self::Hombre => 'Hombre',
            self::NoBinario => 'No binario',
            self::PrefieroNoDecir => 'Prefiero no decir',
        };
    }
}
