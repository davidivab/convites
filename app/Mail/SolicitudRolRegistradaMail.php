<?php

namespace App\Mail;

use App\Models\SolicitudRol;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmación al enviar una solicitud de rol (moderador o voluntario).
 */
class SolicitudRolRegistradaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly SolicitudRol $solicitud)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('Recibimos tu solicitud de '.$this->solicitud->rol->label())
            ->view('emails.solicitud-rol-registrada')
            ->with(['solicitud' => $this->solicitud]);
    }
}
