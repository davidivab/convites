<?php

namespace App\Jobs;

use App\Mail\SolicitudRolAprobadaMail;
use App\Models\SolicitudRol;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Correo al ciudadano cuando su solicitud de rol es aprobada.
 */
class SendSolicitudRolAprobadaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly SolicitudRol $solicitud)
    {
    }

    public function handle(): void
    {
        $user = $this->solicitud->user;

        if (! $user?->email) {
            return;
        }

        Mail::to($user->email)->send(new SolicitudRolAprobadaMail($this->solicitud));
    }
}
