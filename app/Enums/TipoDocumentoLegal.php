<?php

namespace App\Enums;

/**
 * Tipos de documento legal versionado.
 */
enum TipoDocumentoLegal: string
{
    case Terminos = 'terminos';
    case Descargo = 'descargo';
    case Privacidad = 'privacidad';

    public function label(): string
    {
        return match ($this) {
            self::Terminos => 'Términos y condiciones',
            self::Descargo => 'Descargo de responsabilidad',
            self::Privacidad => 'Política de privacidad',
        };
    }
}
