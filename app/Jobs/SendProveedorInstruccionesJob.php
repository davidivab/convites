<?php

namespace App\Jobs;

use App\Mail\ProveedorInstruccionesMail;
use App\Models\Aporte;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Correo al aportante con las instrucciones de pago/entrega del proveedor
 * que eligió al confirmar su aporte.
 */
class SendProveedorInstruccionesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Aporte $aporte)
    {
    }

    public function handle(): void
    {
        $this->aporte->loadMissing(['user', 'proveedor']);

        if (! $this->aporte->proveedor || ! $this->aporte->user?->email) {
            return;
        }

        Mail::to($this->aporte->user->email)->send(new ProveedorInstruccionesMail($this->aporte));
    }
}
