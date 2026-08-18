<?php

namespace App\Jobs;

use App\Mail\ProfesionalAprobadoMail;
use App\Models\Profesional;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Correo al profesional cuando su perfil es aprobado.
 */
class SendProfesionalAprobadoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Profesional $profesional)
    {
    }

    public function handle(): void
    {
        $user = $this->profesional->user;
        $destino = $user?->email ?: $this->profesional->email;

        if (! $destino) {
            return;
        }

        Mail::to($destino)->send(new ProfesionalAprobadoMail($this->profesional));
    }
}
