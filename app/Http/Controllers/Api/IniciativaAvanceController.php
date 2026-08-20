<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIniciativaAvanceMediaRequest;
use App\Http\Requests\StoreIniciativaAvanceRequest;
use App\Http\Requests\UpdateIniciativaAvanceRequest;
use App\Http\Resources\IniciativaAvanceMediaResource;
use App\Http\Resources\IniciativaAvanceResource;
use App\Jobs\SendAvanceAportantesJob;
use App\Models\Iniciativa;
use App\Models\IniciativaAvance;
use App\Models\IniciativaAvanceMedia;
use App\Support\UniqueSlug;
use App\Support\UploadDisk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Avances de convite (P54): reportes de progreso, con o sin ítem asociado.
 *
 * `porcentaje` es narrativo — nunca muta `iniciativa_items.cantidad_aportada`
 * ni `iniciativas.progreso_cache` (P54, D-C). No hay bump de `iniciativas.version`
 * en ninguna mutación de este controller (D-G): los avances son un agregado
 * hermano, no parte del payload optimista-lock de la iniciativa.
 *
 * Notificación a aportantes (`SendAvanceAportantesJob`): se despacha solo
 * cuando `publicado_at` se establece por primera vez en esta operación
 * (create-publish o transición borrador→publicado en update), con
 * `notificar_aportantes=true` y `notificado_at` aún nulo. Este controller
 * nunca escribe `notificado_at` directamente — solo el job lo hace, de
 * forma atómica (D-F) — así que el invariante "editar después de notificar
 * no re-notifica" se mantiene incluso si este guard llegara a dispararse
 * más de una vez: el job colapsa a lo sumo un envío de correo.
 */
class IniciativaAvanceController extends Controller
{
    private const RELATIONS = ['item', 'autor', 'media'];

    public function index(Request $request, Iniciativa $iniciativa_uuid): AnonymousResourceCollection
    {
        $avances = IniciativaAvance::query()
            ->with(self::RELATIONS)
            ->where('iniciativa_id', $iniciativa_uuid->id)
            ->publicados()
            ->orderByDesc('publicado_at')
            ->paginate(min(50, max(1, (int) $request->input('limit', 12))));

        return IniciativaAvanceResource::collection($avances);
    }

    public function show(Request $request, Iniciativa $iniciativa_uuid, string $avanceSlug): IniciativaAvanceResource
    {
        $avance = IniciativaAvance::query()
            ->with(self::RELATIONS)
            ->where('iniciativa_id', $iniciativa_uuid->id)
            ->where('slug', $avanceSlug)
            ->publicados()
            ->firstOrFail();

        return new IniciativaAvanceResource($avance);
    }

    public function store(StoreIniciativaAvanceRequest $request, Iniciativa $iniciativa_uuid): JsonResponse
    {
        $this->authorize('update', $iniciativa_uuid);

        $data = $request->validated();
        $esItem = $data['tipo'] === 'item';
        $publicar = (bool) ($data['publicado'] ?? false);

        $avance = DB::transaction(function () use ($request, $iniciativa_uuid, $data, $esItem, $publicar) {
            $slug = UniqueSlug::forAvance($iniciativa_uuid->id, $data['titulo']);

            return IniciativaAvance::query()->create([
                'iniciativa_id' => $iniciativa_uuid->id,
                'iniciativa_item_id' => $esItem ? $data['iniciativa_item_id'] : null,
                'user_id' => $request->user()->id,
                'slug' => $slug,
                'titulo' => $data['titulo'],
                'cuerpo' => $data['cuerpo'] ?? null,
                'porcentaje' => $esItem ? $data['porcentaje'] : null,
                'enlace_externo' => $data['enlace_externo'] ?? null,
                'notificar_aportantes' => (bool) ($data['notificar_aportantes'] ?? false),
                'publicado_at' => $publicar ? now() : null,
            ]);
        });

        $this->despacharNotificacionSiCorresponde($avance);

        return (new IniciativaAvanceResource($avance->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateIniciativaAvanceRequest $request, Iniciativa $iniciativa_uuid, int $avance): IniciativaAvanceResource
    {
        $this->authorize('update', $iniciativa_uuid);

        $model = IniciativaAvance::query()
            ->where('iniciativa_id', $iniciativa_uuid->id)
            ->whereKey($avance)
            ->firstOrFail();

        $estabaPublicado = $model->publicado_at !== null;

        $data = $request->validated();
        $esItem = $data['tipo'] === 'item';

        $model->fill([
            'iniciativa_item_id' => $esItem ? $data['iniciativa_item_id'] : null,
            // D-E: slug never regenerated on title edit — only `titulo` changes.
            'titulo' => $data['titulo'],
            'cuerpo' => array_key_exists('cuerpo', $data) ? $data['cuerpo'] : $model->cuerpo,
            'porcentaje' => $esItem
                ? (array_key_exists('porcentaje', $data) ? $data['porcentaje'] : $model->porcentaje)
                : null,
            'enlace_externo' => array_key_exists('enlace_externo', $data) ? $data['enlace_externo'] : $model->enlace_externo,
            'notificar_aportantes' => array_key_exists('notificar_aportantes', $data)
                ? (bool) $data['notificar_aportantes']
                : $model->notificar_aportantes,
        ]);

        if (array_key_exists('publicado', $data)) {
            $model->publicado_at = (bool) $data['publicado']
                ? ($model->publicado_at ?? now())
                : null;
        }

        $model->save();

        if (! $estabaPublicado) {
            $this->despacharNotificacionSiCorresponde($model);
        }

        return new IniciativaAvanceResource($model->fresh(self::RELATIONS));
    }

    /**
     * Despacha `SendAvanceAportantesJob` solo cuando el avance quedó
     * publicado en esta operación, con `notificar_aportantes=true` y
     * `notificado_at` aún nulo. El llamador es responsable de solo invocar
     * esto cuando `publicado_at` se estableció por primera vez (create, o
     * la transición borrador→publicado en update) — ver el docblock de la
     * clase.
     */
    private function despacharNotificacionSiCorresponde(IniciativaAvance $avance): void
    {
        if ($avance->publicado_at !== null && $avance->notificar_aportantes && $avance->notificado_at === null) {
            SendAvanceAportantesJob::dispatch($avance);
        }
    }

    public function destroy(Request $request, Iniciativa $iniciativa_uuid, int $avance): JsonResponse
    {
        $this->authorize('update', $iniciativa_uuid);

        $model = IniciativaAvance::query()
            ->where('iniciativa_id', $iniciativa_uuid->id)
            ->whereKey($avance)
            ->firstOrFail();

        DB::transaction(function () use ($model) {
            $disk = UploadDisk::name();
            $mediaItems = IniciativaAvanceMedia::query()
                ->where('iniciativa_avance_id', $model->id)
                ->get();

            foreach ($mediaItems as $media) {
                Storage::disk($disk)->delete($media->path);
            }

            $model->delete();
        });

        return response()->json(null, 204);
    }

    public function mediaStore(StoreIniciativaAvanceMediaRequest $request, Iniciativa $iniciativa_uuid, int $avance): JsonResponse
    {
        $this->authorize('update', $iniciativa_uuid);

        $model = IniciativaAvance::query()
            ->where('iniciativa_id', $iniciativa_uuid->id)
            ->whereKey($avance)
            ->firstOrFail();

        $data = $request->validated();
        $archivo = $request->file('archivo');

        $mime = (string) $archivo->getMimeType();
        $esVideo = str_starts_with($mime, 'video/');
        $tipo = $esVideo ? 'video' : 'imagen';

        $disk = UploadDisk::name();
        $ancho = null;
        $alto = null;

        if (! $esVideo) {
            $dimensiones = @getimagesize($archivo->getRealPath());
            $ancho = $dimensiones !== false ? $dimensiones[0] : null;
            $alto = $dimensiones !== false ? $dimensiones[1] : null;
        }

        $path = $archivo->store('iniciativas/avances', $disk);

        $siguienteOrden = (int) $model->media()->max('orden') + 1;

        $media = IniciativaAvanceMedia::query()->create([
            'iniciativa_avance_id' => $model->id,
            'path' => $path,
            'tipo' => $tipo,
            'orden' => $siguienteOrden,
            'ancho' => $ancho,
            'alto' => $alto,
            'duracion_segundos' => $esVideo ? ($data['duracion_segundos'] ?? null) : null,
        ]);

        return (new IniciativaAvanceMediaResource($media))
            ->response()
            ->setStatusCode(201);
    }

    public function mediaDestroy(Request $request, Iniciativa $iniciativa_uuid, int $avance, int $mediaId): JsonResponse
    {
        $this->authorize('update', $iniciativa_uuid);

        $model = IniciativaAvance::query()
            ->where('iniciativa_id', $iniciativa_uuid->id)
            ->whereKey($avance)
            ->firstOrFail();

        $media = IniciativaAvanceMedia::query()
            ->where('iniciativa_avance_id', $model->id)
            ->whereKey($mediaId)
            ->firstOrFail();

        Storage::disk(UploadDisk::name())->delete($media->path);
        $media->delete();

        return response()->json(null, 204);
    }
}
