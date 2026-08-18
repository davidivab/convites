<?php

namespace App\Mail;

use App\Models\Iniciativa;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Al creador, cuando su convite se envía a revisión.
 */
class ConviteEnviadoRevisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Iniciativa $iniciativa)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('Recibimos tu convite: '.$this->iniciativa->titulo)
            ->view('emails.convite-enviado-revision')
            ->with(['iniciativa' => $this->iniciativa]);
    }
}
