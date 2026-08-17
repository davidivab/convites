<?php

namespace App\Enums;

/**
 * Disponibilidad operativa de un centro.
 */
enum EstadoCentro: string
{
    case Abierto = 'abierto';
    case Cerrado = 'cerrado';
    case Lleno = 'lleno';
    case VeinticuatroHoras = '24h';

    public function label(): string
    {
        return match ($this) {
            self::Abierto => 'Abierto ahora',
            self::Cerrado => 'Cerrado',
            self::Lleno => 'Sin cupo',
            self::VeinticuatroHoras => 'Atiende 24 horas',
        };
    }
}
