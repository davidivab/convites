<?php

namespace App\Mail;

use App\Models\Iniciativa;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Al creador, cuando su convite es aprobado y queda publicado.
 */
class ConviteAprobadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Iniciativa $iniciativa)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('¡Tu convite ya está publicado! — '.$this->iniciativa->titulo)
            ->view('emails.convite-aprobado')
            ->with(['iniciativa' => $this->iniciativa]);
    }
}
