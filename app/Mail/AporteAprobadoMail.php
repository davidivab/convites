<?php

namespace App\Mail;

use App\Models\Aporte;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Al aportante, cuando el creador confirma que recibió lo prometido.
 */
class AporteAprobadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Aporte $aporte)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('¡Confirmamos que recibimos tu aporte!')
            ->view('emails.aporte-aprobado')
            ->with(['aporte' => $this->aporte, 'iniciativa' => $this->aporte->iniciativa]);
    }
}
