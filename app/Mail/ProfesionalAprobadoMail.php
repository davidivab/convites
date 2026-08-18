<?php

namespace App\Mail;

use App\Models\Profesional;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Al profesional, cuando su perfil es aprobado y activa el rol.
 */
class ProfesionalAprobadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Profesional $profesional)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('¡Tu perfil profesional fue aprobado!')
            ->view('emails.profesional-aprobado')
            ->with(['profesional' => $this->profesional]);
    }
}
