<?php

namespace App\Mail;

use App\Models\Aporte;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Al aportante, con las instrucciones de pago/entrega del proveedor que
 * eligió al confirmar su aporte.
 */
class ProveedorInstruccionesMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Aporte $aporte)
    {
    }

    public function build(): self
    {
        return $this
            ->subject("Instrucciones para tu aporte con {$this->aporte->proveedor->nombre}")
            ->view('emails.proveedor-instrucciones')
            ->with([
                'aporte' => $this->aporte,
                'iniciativa' => $this->aporte->iniciativa,
                'proveedor' => $this->aporte->proveedor,
            ]);
    }
}
