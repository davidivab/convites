<?php

namespace App\Jobs;

use App\Mail\ConviteAprobadoMail;
use App\Models\Iniciativa;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Correo al creador cuando su convite es aprobado.
 */
class SendConviteAprobadoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Iniciativa $iniciativa)
    {
    }

    public function handle(): void
    {
        $creador = $this->iniciativa->creador;

        if (! $creador?->email) {
            return;
        }

        Mail::to($creador->email)->send(new ConviteAprobadoMail($this->iniciativa));
    }
}
