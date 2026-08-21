<?php

namespace App\Jobs;

use App\Mail\ConviteAsignadoMail;
use App\Models\Iniciativa;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Notifica al nuevo creador cuando un admin le asigna un convite.
 */
class SendConviteAsignadoJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Iniciativa $iniciativa,
        public readonly User $nuevoCreador,
    ) {
    }

    public function handle(): void
    {
        $iniciativa = $this->iniciativa->fresh(['municipio.departamento']);
        $user = $this->nuevoCreador->fresh();
        $email = $user?->email;
        if (! $iniciativa || ! $email) {
            return;
        }

        Mail::to($email)->send(new ConviteAsignadoMail($iniciativa, $user));
    }
}
