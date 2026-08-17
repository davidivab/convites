<?php

namespace App\Enums;

/**
 * Rol que un ciudadano puede solicitar. Los valores están en español para la
 * API/UI, pero el rol Spatie real de `moderador` es `moderator` (inglés,
 * heredado del catálogo de roles existente) — ver `rolSpatie()`.
 */
enum TipoSolicitudRol: string
{
    case Moderador = 'moderador';
    case Voluntario = 'voluntario';

    public function label(): string
    {
        return match ($this) {
            self::Moderador => 'Moderador',
            self::Voluntario => 'Voluntario',
        };
    }

    /**
     * Nombre real del rol Spatie a asignar cuando se aprueba la solicitud.
     */
    public function rolSpatie(): string
    {
        return match ($this) {
            self::Moderador => 'moderator',
            self::Voluntario => 'voluntario',
        };
    }
}
