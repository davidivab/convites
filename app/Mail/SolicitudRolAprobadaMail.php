<?php

namespace App\Mail;

use App\Models\SolicitudRol;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Al ciudadano, cuando su solicitud de rol (moderador/voluntario) es aprobada.
 */
class SolicitudRolAprobadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly SolicitudRol $solicitud)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('¡Ya sos '.$this->solicitud->rol->label().' en Convites!')
            ->view('emails.solicitud-rol-aprobada')
            ->with(['solicitud' => $this->solicitud]);
    }
}
