<?php

namespace App\Mail;

use App\Models\Profesional;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmación al enviar la solicitud de perfil profesional.
 */
class ProfesionalRegistradoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Profesional $profesional)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('Recibimos tu registro profesional')
            ->view('emails.profesional-registrado')
            ->with(['profesional' => $this->profesional]);
    }
}
