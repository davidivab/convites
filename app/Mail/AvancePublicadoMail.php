<?php

namespace App\Mail;

use App\Models\IniciativaAvance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Al aportante, cuando el organizador publica un avance de la iniciativa
 * y marca `notificar_aportantes` (P54).
 */
class AvancePublicadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly IniciativaAvance $avance,
        public readonly User $aportante,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Nuevo avance en "'.$this->avance->iniciativa->titulo.'"')
            ->view('emails.avance-publicado')
            ->with([
                'avance' => $this->avance,
                'iniciativa' => $this->avance->iniciativa,
                'aportante' => $this->aportante,
            ]);
    }
}
