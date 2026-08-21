<?php

namespace App\Mail;

use App\Models\Aporte;
use App\Support\ConviteCalendarIcs;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Al aportante, al confirmar su compromiso (ítems y/o asistencia).
 */
class AporteConfirmadoDonanteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Aporte $aporte)
    {
    }

    public function build(): self
    {
        $this->aporte->loadMissing([
            'user',
            'iniciativa.municipio.departamento',
            'items.iniciativaItem',
            'puntoAcopio',
        ]);

        $iniciativa = $this->aporte->iniciativa;

        $extra = [];
        foreach ($this->aporte->items as $linea) {
            $nombre = $linea->iniciativaItem?->nombre ?? 'ítem';
            $unidad = $linea->iniciativaItem?->unidad;
            $extra[] = trim($linea->cantidad.($unidad ? " {$unidad}" : '')." {$nombre}");
        }
        if ($this->aporte->asiste_al_convite) {
            $extra[] = 'Asistencia / apoyo con tu tiempo';
        }

        $mail = $this
            ->subject('Confirmamos tu compromiso con el convite')
            ->view('emails.aporte-confirmado-donante')
            ->with([
                'aporte' => $this->aporte,
                'iniciativa' => $iniciativa,
            ]);

        $ics = ConviteCalendarIcs::forIniciativa(
            $iniciativa,
            $extra !== [] ? 'Tu compromiso: '.implode('; ', $extra).'.' : null,
        );

        if ($ics) {
            $mail->attachData($ics, 'convite-'.$iniciativa->slug.'.ics', [
                'mime' => 'text/calendar; charset=UTF-8; method=PUBLISH',
            ]);
        }

        return $mail;
    }
}
