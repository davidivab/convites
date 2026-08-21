<?php

namespace App\Jobs;

use App\Mail\AporteConfirmadoDonanteMail;
use App\Models\Aporte;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Correo de confirmación al aportante cuando registra su compromiso.
 */
class SendAporteConfirmadoDonanteJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Aporte $aporte)
    {
    }

    public function handle(): void
    {
        $aporte = $this->aporte->fresh(['user', 'iniciativa', 'items.iniciativaItem', 'puntoAcopio']);
        $email = $aporte?->user?->email;
        if (! $email) {
            return;
        }

        Mail::to($email)->send(new AporteConfirmadoDonanteMail($aporte));
    }
}
