<?php

namespace App\Jobs;

use App\Enums\EstadoAporte;
use App\Mail\AvancePublicadoMail;
use App\Models\IniciativaAvance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Notifica a los aportantes de una iniciativa cuando se publica un avance
 * con `notificar_aportantes=true` (P54).
 *
 * At-most-once (D-F): reclama `notificado_at` de forma atómica ANTES de
 * enviar los correos. Si el `update()` afecta 0 filas, otra ejecución ya
 * reclamó el envío y este handle() no hace nada — evita duplicar el correo
 * en un reintento de cola.
 *
 * Elegibilidad (D-B): `$user->notificacionPreferencia?->email_avances ?? true`
 * — una fila de preferencia ausente se trata como "opt-in", nunca como
 * "opt-out". `anonimo=true` en el aporte NO excluye al aportante (la
 * anonimidad es solo de presentación pública, decisión de producto
 * confirmada) — por eso no hay ningún filtro `where('anonimo', ...)` aquí.
 */
class SendAvanceAportantesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly IniciativaAvance $avance) {}

    public function handle(): void
    {
        $avance = $this->avance->fresh(['iniciativa', 'item', 'media']);

        if (! $avance || $avance->publicado_at === null) {
            return;
        }

        $claimed = IniciativaAvance::query()
            ->whereKey($avance->id)
            ->whereNull('notificado_at')
            ->update(['notificado_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        User::query()
            ->whereIn('id', function ($query) use ($avance) {
                $query->select('user_id')
                    ->from('aportes')
                    ->where('iniciativa_id', $avance->iniciativa_id)
                    ->whereIn('estado', [EstadoAporte::Confirmado->value, EstadoAporte::Cumplido->value]);
            })
            ->whereNotNull('email')
            ->with('notificacionPreferencia')
            ->chunkById(200, function ($aportantes) use ($avance) {
                $this->notificarLote($aportantes, $avance);
            });
    }

    /**
     * @param  Collection<int, User>  $aportantes
     */
    private function notificarLote(Collection $aportantes, IniciativaAvance $avance): void
    {
        foreach ($aportantes as $aportante) {
            if (($aportante->notificacionPreferencia?->email_avances ?? true) === false) {
                continue;
            }

            Mail::to($aportante->email)->send(new AvancePublicadoMail($avance, $aportante));
        }
    }
}
