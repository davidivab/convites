<?php

namespace App\Enums;

/**
 * Estado de verificación del perfil profesional (documentos / experiencia).
 */
enum EstadoProfesional: string
{
    case Pendiente = 'pendiente';
    case CambiosSolicitados = 'cambios_solicitados';
    case Aprobado = 'aprobado';
    case Rechazado = 'rechazado';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente de revisión',
            self::CambiosSolicitados => 'Cambios solicitados',
            self::Aprobado => 'Aprobado',
            self::Rechazado => 'Rechazado',
        };
    }
}
