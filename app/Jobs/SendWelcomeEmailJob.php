<?php

namespace App\Jobs;

use App\Mail\BienvenidaMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Correo de bienvenida al crear una cuenta (registro por correo o primer
 * login con Google). Se dispara una sola vez, en el momento de creación.
 */
class SendWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly User $user)
    {
    }

    public function handle(): void
    {
        if (! $this->user->email) {
            return;
        }

        Mail::to($this->user->email)->send(new BienvenidaMail($this->user));
    }
}
