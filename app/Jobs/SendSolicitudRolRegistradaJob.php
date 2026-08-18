<?php

namespace App\Jobs;

use App\Mail\SolicitudRolRegistradaMail;
use App\Models\SolicitudRol;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Correo de confirmación al enviar una solicitud de rol.
 */
class SendSolicitudRolRegistradaJob implements ShouldQueue
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

        Mail::to($user->email)->send(new SolicitudRolRegistradaMail($this->solicitud));
    }
}
