<?php

namespace App\Jobs;

use App\Mail\AporteAprobadoMail;
use App\Models\Aporte;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Correo al aportante cuando el creador confirma que recibió su aporte.
 */
class SendAporteAprobadoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Aporte $aporte)
    {
    }

    public function handle(): void
    {
        $aportante = $this->aporte->user;

        if (! $aportante?->email) {
            return;
        }

        Mail::to($aportante->email)->send(new AporteAprobadoMail($this->aporte));
    }
}
