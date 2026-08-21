<?php

namespace App\Mail;

use App\Models\Iniciativa;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Al nuevo dueño, cuando un admin le asigna un convite.
 */
class ConviteAsignadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Iniciativa $iniciativa,
        public readonly User $nuevoCreador,
    ) {
    }

    public function build(): self
    {
        $this->iniciativa->loadMissing(['municipio.departamento']);

        $frontend = rtrim((string) (explode(',', (string) env('FRONTEND_URL', 'https://convites.co'))[0] ?? 'https://convites.co'), '/');
        $panelUrl = $frontend.'/panel/creador';
        $editarUrl = $frontend.'/panel/creador/'.$this->iniciativa->slug.'/editar';

        return $this
            ->subject('Te asignaron un convite: '.$this->iniciativa->titulo)
            ->view('emails.convite-asignado')
            ->with([
                'iniciativa' => $this->iniciativa,
                'user' => $this->nuevoCreador,
                'panelUrl' => $panelUrl,
                'editarUrl' => $editarUrl,
            ]);
    }
}
