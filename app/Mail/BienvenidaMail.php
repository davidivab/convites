<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Correo de bienvenida — se envía una sola vez, al crear la cuenta
 * (registro por correo o primer login con Google).
 */
class BienvenidaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('¡Bienvenido/a a Convites!')
            ->view('emails.bienvenida')
            ->with(['user' => $this->user]);
    }
}
