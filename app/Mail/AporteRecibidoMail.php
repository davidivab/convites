<?php

namespace App\Mail;

use App\Models\Aporte;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Al creador, cuando alguien se compromete a ayudar en su convite.
 *
 * Nunca menciona quién es el aportante (respeta el anonimato en el correo,
 * más allá de lo que el propio aportante haya elegido en la app).
 */
class AporteRecibidoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Aporte $aporte)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('¡Alguien se comprometió con tu convite!')
            ->view('emails.aporte-recibido')
            ->with(['aporte' => $this->aporte, 'iniciativa' => $this->aporte->iniciativa]);
    }
}
