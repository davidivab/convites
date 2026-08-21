<?php

namespace App\Mail;

use App\Models\Aporte;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Al creador, cuando alguien se compromete a ayudar en su convite.
 * Incluye detalle del compromiso; contacto solo si el aporte no es anónimo.
 */
class AporteRecibidoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Aporte $aporte)
    {
    }

    public function build(): self
    {
        $this->aporte->loadMissing([
            'user',
            'iniciativa.creador',
            'items.iniciativaItem',
            'puntoAcopio',
        ]);

        return $this
            ->subject('¡Alguien se comprometió con tu convite!')
            ->view('emails.aporte-recibido')
            ->with(['aporte' => $this->aporte, 'iniciativa' => $this->aporte->iniciativa]);
    }
}
