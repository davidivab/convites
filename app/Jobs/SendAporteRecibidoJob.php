<?php

namespace App\Jobs;

use App\Mail\AporteRecibidoMail;
use App\Models\Aporte;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Correo al creador cuando alguien se compromete con su convite.
 */
class SendAporteRecibidoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Aporte $aporte)
    {
    }

    public function handle(): void
    {
        $aporte = $this->aporte->fresh([
            'user',
            'iniciativa.creador',
            'items.iniciativaItem',
            'puntoAcopio',
        ]);
        $creador = $aporte?->iniciativa?->creador;

        if (! $creador?->email || ! $aporte) {
            return;
        }

        Mail::to($creador->email)->send(new AporteRecibidoMail($aporte));
    }
}
