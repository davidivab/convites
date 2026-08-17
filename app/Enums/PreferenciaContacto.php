<?php

namespace App\Enums;

/**
 * Preferencia de canal al contactar a un profesional.
 */
enum PreferenciaContacto: string
{
    case Llamada = 'llamada';
    case Whatsapp = 'whatsapp';
    case Correo = 'correo';

    public function label(): string
    {
        return match ($this) {
            self::Llamada => 'Llamada',
            self::Whatsapp => 'WhatsApp',
            self::Correo => 'Correo',
        };
    }
}
