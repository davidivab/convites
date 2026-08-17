<?php

namespace App\Enums;

/**
 * Estado de un compromiso de aporte en especie (o asistencia).
 *
 * confirmado: el vecino se comprometió; suma a los contadores.
 * cancelado:  retiró el compromiso; se resta del cache de aportado.
 * cumplido:   se registró que efectivamente llevó lo prometido.
 */
enum EstadoAporte: string
{
    case Confirmado = 'confirmado';
    case Cancelado = 'cancelado';
    case Cumplido = 'cumplido';

    public function label(): string
    {
        return match ($this) {
            self::Confirmado => 'Confirmado',
            self::Cancelado => 'Cancelado',
            self::Cumplido => 'Cumplido',
        };
    }
}
