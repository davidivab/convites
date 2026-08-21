<?php

namespace App\Services;

use App\Enums\EstadoAporte;
use App\Enums\EstadoIniciativa;
use App\Jobs\SendAporteAprobadoJob;
use App\Jobs\SendAporteConfirmadoDonanteJob;
use App\Jobs\SendAporteRecibidoJob;
use App\Jobs\SendProveedorInstruccionesJob;
use App\Models\Activity;
use App\Models\Aporte;
use App\Models\AporteItem;
use App\Models\IdempotencyKey;
use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use App\Models\User;
use App\Notifications\AporteConfirmadoNotification;
use App\Support\UploadDisk;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Crea / actualiza aportes con idempotencia y recalculo de progreso.
 */
class AporteService
{
    public function __construct(
        private readonly IniciativaProgresoService $progreso,
        private readonly ActivityService $activities,
        private readonly ModeratorNotificationService $moderatorNotifications,
    ) {}

    /**
     * @param  array{
     *     asiste_al_convite?: bool,
     *     nota?: string|null,
     *     fecha_entrega?: string|null,
     *     client_request_id?: string|null,
     *     items: list<array{iniciativa_item_id: int, cantidad: int}>
     * }  $payload
     */
    public function confirmar(User $user, Iniciativa $iniciativa, array $payload): Aporte
    {
        $this->assertIniciativaAceptaAportes($iniciativa);

        $clientRequestId = $payload['client_request_id'] ?? null;

        if ($clientRequestId) {
            $cached = IdempotencyKey::query()
                ->where('user_id', $user->id)
                ->where('key', $clientRequestId)
                ->where('route', 'aportes.store')
                ->where('expires_at', '>', now())
                ->first();

            if ($cached && is_array($cached->response_body) && isset($cached->response_body['aporte_id'])) {
                return Aporte::query()
                    ->with(['items.iniciativaItem', 'iniciativa', 'puntoAcopio.municipio', 'proveedor'])
                    ->findOrFail($cached->response_body['aporte_id']);
            }
        }

        $aporte = DB::transaction(function () use ($user, $iniciativa, $payload, $clientRequestId) {
            /** @var Iniciativa $locked */
            $locked = Iniciativa::query()
                ->whereKey($iniciativa->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertIniciativaAceptaAportes($locked);

            $items = $payload['items'] ?? [];
            $asiste = (bool) ($payload['asiste_al_convite'] ?? false);

            if ($items === [] && ! $asiste) {
                throw ValidationException::withMessages([
                    'items' => ['Debes indicar al menos un ítem o marcar asistencia al convite.'],
                ]);
            }

            if ($items !== []) {
                $this->assertItemsPertenecen($locked, $items);
            }

            if ($clientRequestId) {
                $otro = Aporte::query()
                    ->where('client_request_id', $clientRequestId)
                    ->where('user_id', '!=', $user->id)
                    ->exists();

                if ($otro) {
                    throw ValidationException::withMessages([
                        'client_request_id' => ['Esta clave de idempotencia ya fue usada.'],
                    ]);
                }
            }

            /** @var Aporte $aporte */
            $aporte = Aporte::query()->firstOrNew([
                'user_id' => $user->id,
                'iniciativa_id' => $locked->id,
            ]);

            $aporte->fill([
                'estado' => EstadoAporte::Confirmado,
                'asiste_al_convite' => $asiste,
                'nota' => $payload['nota'] ?? null,
                'anonimo' => (bool) ($payload['anonimo'] ?? false),
                'punto_acopio_id' => $payload['punto_acopio_id'] ?? null,
                'proveedor_id' => $payload['proveedor_id'] ?? null,
                'fecha_entrega' => $payload['fecha_entrega'] ?? null,
                'client_request_id' => $clientRequestId ?: $aporte->client_request_id,
                'confirmado_at' => now(),
                'cancelado_at' => null,
                'cumplido_at' => null,
            ]);
            $aporte->save();

            $aporte->items()->delete();

            foreach ($items as $linea) {
                AporteItem::query()->create([
                    'aporte_id' => $aporte->id,
                    'iniciativa_item_id' => $linea['iniciativa_item_id'],
                    'cantidad' => $linea['cantidad'],
                ]);
            }

            $this->progreso->recalcular($locked);

            $fresh = $aporte->fresh(['items.iniciativaItem', 'iniciativa.creador', 'user', 'puntoAcopio.municipio', 'proveedor']);
            $this->activities->createActivityForModel([
                'message' => "Aporte confirmado en iniciativa {$locked->slug}",
                'status_text' => 'confirmado',
                'status' => 'confirmado',
                'color' => Activity::COLOR_SUCCESS,
                'data' => [
                    'iniciativa_id' => $locked->id,
                    'anonimo' => (bool) $fresh->anonimo,
                ],
            ], $fresh);

            $this->moderatorNotifications->notifyForMunicipio(
                $locked->municipio_id,
                new AporteConfirmadoNotification($fresh),
            );

            SendAporteRecibidoJob::dispatch($fresh);
            SendAporteConfirmadoDonanteJob::dispatch($fresh);

            if ($fresh->proveedor_id) {
                SendProveedorInstruccionesJob::dispatch($fresh);
            }

            return $fresh;
        });

        if ($clientRequestId) {
            IdempotencyKey::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'key' => $clientRequestId,
                ],
                [
                    'route' => 'aportes.store',
                    'response_code' => 201,
                    'response_body' => ['aporte_id' => $aporte->id],
                    'expires_at' => now()->addDay(),
                ],
            );
        }

        return $aporte;
    }

    public function cancelar(User $user, Aporte $aporte): Aporte
    {
        if ($aporte->user_id !== $user->id && ! $user->can('iniciativas.moderate')) {
            throw new HttpException(403, 'No puedes cancelar este aporte.');
        }

        if ($aporte->estado === EstadoAporte::Cancelado) {
            return $aporte;
        }

        return DB::transaction(function () use ($aporte) {
            $aporte->forceFill([
                'estado' => EstadoAporte::Cancelado,
                'cancelado_at' => now(),
            ])->save();

            $this->progreso->recalcular($aporte->iniciativa);

            $fresh = $aporte->fresh(['items.iniciativaItem', 'iniciativa']);
            $this->activities->createActivityForModel([
                'message' => "Aporte #{$fresh->id} cancelado",
                'status_text' => 'cancelado',
                'status' => 'cancelado',
                'color' => Activity::COLOR_WARNING,
            ], $fresh);

            return $fresh;
        });
    }

    /**
     * Dueño de la iniciativa marca el aporte como recibido (cumplido) o vuelve a confirmado.
     *
     * @param  \Illuminate\Http\UploadedFile|null  $evidencia
     */
    public function marcarRecepcion(
        User $actor,
        Aporte $aporte,
        bool $recibido,
        $evidencia = null,
    ): Aporte {
        $aporte->loadMissing('iniciativa');
        $iniciativa = $aporte->iniciativa;

        if ($iniciativa === null) {
            throw new HttpException(404, 'Iniciativa no encontrada.');
        }

        if ($actor->id !== $iniciativa->user_id && ! $actor->canModerateIniciativa($iniciativa)) {
            throw new HttpException(403, 'No puedes gestionar los aportes de esta iniciativa.');
        }

        if ($aporte->estado === EstadoAporte::Cancelado) {
            throw ValidationException::withMessages([
                'aporte' => ['No se puede marcar un aporte cancelado.'],
            ]);
        }

        return DB::transaction(function () use ($aporte, $recibido, $evidencia) {
            /** @var Aporte $locked */
            $locked = Aporte::query()->whereKey($aporte->id)->lockForUpdate()->firstOrFail();

            if ($recibido) {
                $locked->forceFill([
                    'estado' => EstadoAporte::Cumplido,
                    'cumplido_at' => now(),
                ]);

                if ($evidencia) {
                    $disk = UploadDisk::name();
                    $path = $evidencia->store('aportes/evidencias', $disk);
                    $locked->forceFill([
                        'evidencia_disk' => $disk,
                        'evidencia_path' => $path,
                        'evidencia_nombre_original' => $evidencia->getClientOriginalName(),
                        'evidencia_mime' => $evidencia->getClientMimeType(),
                        'evidencia_tamanio_bytes' => $evidencia->getSize(),
                    ]);
                }
            } else {
                $locked->forceFill([
                    'estado' => EstadoAporte::Confirmado,
                    'cumplido_at' => null,
                ]);
            }

            $locked->save();
            $this->progreso->recalcular($locked->iniciativa);

            $fresh = $locked->fresh(['items.iniciativaItem', 'iniciativa', 'user']);
            $this->activities->createActivityForModel([
                'message' => $recibido
                    ? "Aporte #{$fresh->id} marcado como recibido"
                    : "Aporte #{$fresh->id} marcado como no recibido",
                'status_text' => $recibido ? 'cumplido' : 'confirmado',
                'status' => $recibido ? 'cumplido' : 'no_recibido',
                'color' => $recibido ? Activity::COLOR_SUCCESS : Activity::COLOR_INFO,
            ], $fresh);

            if ($recibido) {
                SendAporteAprobadoJob::dispatch($fresh);
            }

            return $fresh;
        });
    }

    /**
     * P39: borra solo el archivo de evidencia — no cambia el estado de
     * recepción (`cumplido`/`confirmado`), por si el creador quiere volver
     * a subir una evidencia mejor sin perder el resto del compromiso.
     */
    public function eliminarEvidencia(User $actor, Aporte $aporte): Aporte
    {
        $aporte->loadMissing('iniciativa');
        $iniciativa = $aporte->iniciativa;

        if ($iniciativa === null) {
            throw new HttpException(404, 'Iniciativa no encontrada.');
        }

        if ($actor->id !== $iniciativa->user_id && ! $actor->canModerateIniciativa($iniciativa)) {
            throw new HttpException(403, 'No puedes gestionar los aportes de esta iniciativa.');
        }

        if ($aporte->evidencia_path && $aporte->evidencia_disk) {
            \Illuminate\Support\Facades\Storage::disk($aporte->evidencia_disk)->delete($aporte->evidencia_path);
        }

        $aporte->forceFill([
            'evidencia_disk' => null,
            'evidencia_path' => null,
            'evidencia_nombre_original' => null,
            'evidencia_mime' => null,
            'evidencia_tamanio_bytes' => null,
        ])->save();

        return $aporte->fresh(['items.iniciativaItem', 'iniciativa', 'user']);
    }

    /**
     * El aportante (dueño del aporte) sube su propia evidencia (ej. recibo
     * de compra, foto de los ítems listos para entrega). Independiente de
     * la evidencia del organizador: no toca `estado` ni `cumplido_at`.
     */
    public function subirEvidenciaPropia(User $actor, Aporte $aporte, \Illuminate\Http\UploadedFile $file): Aporte
    {
        if ($actor->id !== $aporte->user_id) {
            throw new HttpException(403, 'No puedes adjuntar evidencia a un aporte que no es tuyo.');
        }

        if ($aporte->evidencia_aportante_path && $aporte->evidencia_aportante_disk) {
            \Illuminate\Support\Facades\Storage::disk($aporte->evidencia_aportante_disk)->delete($aporte->evidencia_aportante_path);
        }

        $disk = UploadDisk::name();
        $path = $file->store('aportes/evidencias-aportante', $disk);

        $aporte->forceFill([
            'evidencia_aportante_disk' => $disk,
            'evidencia_aportante_path' => $path,
            'evidencia_aportante_nombre_original' => $file->getClientOriginalName(),
            'evidencia_aportante_mime' => $file->getClientMimeType(),
            'evidencia_aportante_tamanio_bytes' => $file->getSize(),
        ])->save();

        return $aporte->fresh(['items.iniciativaItem', 'iniciativa']);
    }

    /**
     * El aportante borra su propia evidencia. No cambia `estado`/`cumplido_at`.
     */
    public function eliminarEvidenciaPropia(User $actor, Aporte $aporte): Aporte
    {
        if ($actor->id !== $aporte->user_id) {
            throw new HttpException(403, 'No puedes adjuntar evidencia a un aporte que no es tuyo.');
        }

        if ($aporte->evidencia_aportante_path && $aporte->evidencia_aportante_disk) {
            \Illuminate\Support\Facades\Storage::disk($aporte->evidencia_aportante_disk)->delete($aporte->evidencia_aportante_path);
        }

        $aporte->forceFill([
            'evidencia_aportante_disk' => null,
            'evidencia_aportante_path' => null,
            'evidencia_aportante_nombre_original' => null,
            'evidencia_aportante_mime' => null,
            'evidencia_aportante_tamanio_bytes' => null,
        ])->save();

        return $aporte->fresh(['items.iniciativaItem', 'iniciativa']);
    }

    private function assertIniciativaAceptaAportes(Iniciativa $iniciativa): void
    {
        if (! in_array($iniciativa->estado, [EstadoIniciativa::Publicada, EstadoIniciativa::EnCurso], true)) {
            throw ValidationException::withMessages([
                'iniciativa' => ['Esta iniciativa no acepta aportes en su estado actual.'],
            ]);
        }

        if ($iniciativa->fecha_limite_aportes && $iniciativa->fecha_limite_aportes->isPast()) {
            throw ValidationException::withMessages([
                'iniciativa' => ['Ya pasó la fecha límite de aportes.'],
            ]);
        }
    }

    /**
     * @param  list<array{iniciativa_item_id: int, cantidad: int}>  $items
     */
    private function assertItemsPertenecen(Iniciativa $iniciativa, array $items): void
    {
        $ids = collect($items)->pluck('iniciativa_item_id')->all();
        $validos = IniciativaItem::query()
            ->where('iniciativa_id', $iniciativa->id)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        if (count($validos) !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'items' => ['Uno o más ítems no pertenecen a esta iniciativa.'],
            ]);
        }
    }
}
