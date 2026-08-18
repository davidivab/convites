<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccionModeracion;
use App\Enums\EstadoIniciativa;
use App\Http\Controllers\Controller;
use App\Jobs\SendConviteEnviadoRevisionJob;
use App\Http\Requests\StoreIniciativaRequest;
use App\Http\Requests\UpdateIniciativaRequest;
use App\Http\Resources\IniciativaResource;
use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use App\Models\IniciativaPuntoAcopio;
use App\Notifications\IniciativaPendienteModeracionNotification;
use App\Services\ModeratorNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Listado público, CRUD propio y envío a revisión de iniciativas.
 */
class IniciativaController extends Controller
{
    public function __construct(
        private readonly ModeratorNotificationService $moderatorNotifications,
    ) {}
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Iniciativa::query()
            ->with(['zona', 'municipio.departamento', 'categoria', 'creador', 'items', 'puntosAcopio.municipio.departamento'])
            ->whereIn('estado', [
                EstadoIniciativa::Publicada->value,
                EstadoIniciativa::EnCurso->value,
            ]);

        if ($request->filled('zona')) {
            $query->whereHas('zona', fn ($q) => $q->where('slug', $request->string('zona')));
        }

        if ($request->filled('municipio')) {
            $query->whereHas('municipio', fn ($q) => $q->where('slug', $request->string('municipio')));
        }

        if ($request->filled('departamento')) {
            $query->whereHas(
                'municipio.departamento',
                fn ($q) => $q->where('slug', $request->string('departamento')),
            );
        }

        if ($request->filled('categoria')) {
            $query->whereHas('categoria', fn ($q) => $q->where('slug', $request->string('categoria')));
        }

        if ($request->filled('urgencia')) {
            $query->where('urgencia', $request->string('urgencia'));
        }

        if ($request->filled('q')) {
            $this->applyTituloResumenSearch($query, (string) $request->string('q'));
        }

        if ($request->boolean('destacadas')) {
            $query->where('destacada', true)->orderBy('orden_destacada');
        } else {
            $this->applyOrden($query, $request, 'publicada_at');
        }

        return IniciativaResource::collection(
            $query->paginate(min(50, max(1, (int) $request->input('per_page', 12))))
        );
    }

    /**
     * P48: `orden` (fecha|avance|nombre) + `dir` (asc|desc, default desc).
     * Sin `orden` (o valor inválido) mantiene el comportamiento por defecto.
     */
    private function applyOrden(Builder $query, Request $request, string $defaultColumn): void
    {
        $dir = $request->string('dir')->value() === 'asc' ? 'asc' : 'desc';

        match ($request->string('orden')->value()) {
            'fecha' => $query->orderBy('fecha_convite', $dir),
            'avance' => $query->orderBy('progreso_cache', $dir),
            'nombre' => $query->orderBy('titulo', $dir),
            default => $query->orderByDesc($defaultColumn)->orderByDesc('id'),
        };
    }

    /**
     * Listado liviano para el mapa de exploración (sin historia / ítems).
     */
    public function mapa(Request $request): JsonResponse
    {
        $query = Iniciativa::query()
            ->with('zona')
            ->whereIn('estado', [
                EstadoIniciativa::Publicada->value,
                EstadoIniciativa::EnCurso->value,
            ])
            ->where('mapa_visible', true)
            ->whereNotNull('lat')
            ->whereNotNull('lng');

        if ($request->filled('zona')) {
            $query->whereHas('zona', fn ($q) => $q->where('slug', $request->string('zona')));
        }

        if ($request->filled('municipio')) {
            $query->whereHas('municipio', fn ($q) => $q->where('slug', $request->string('municipio')));
        }

        if ($request->filled('departamento')) {
            $query->whereHas(
                'municipio.departamento',
                fn ($q) => $q->where('slug', $request->string('departamento')),
            );
        }

        if ($request->filled('categoria')) {
            $query->whereHas('categoria', fn ($q) => $q->where('slug', $request->string('categoria')));
        }

        if ($request->filled('urgencia')) {
            $query->where('urgencia', $request->string('urgencia'));
        }

        if ($request->filled('q')) {
            $this->applyTituloResumenSearch($query, (string) $request->string('q'));
        }

        if ($request->filled('bbox')) {
            $parts = array_map('floatval', explode(',', (string) $request->input('bbox')));
            if (count($parts) === 4) {
                [$minLng, $minLat, $maxLng, $maxLat] = $parts;
                $query->whereBetween('lat', [min($minLat, $maxLat), max($minLat, $maxLat)])
                    ->whereBetween('lng', [min($minLng, $maxLng), max($minLng, $maxLng)]);
            }
        }

        $rows = $query
            ->orderByDesc('destacada')
            ->orderByDesc('publicada_at')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (Iniciativa $i) => [
                'id' => $i->id,
                'slug' => $i->slug,
                'titulo' => $i->titulo,
                'urgencia' => $i->urgencia?->value,
                'progreso' => $i->progreso_cache,
                'zona' => $i->zona ? ['nombre' => $i->zona->nombre] : null,
                'lat' => (float) $i->lat,
                'lng' => (float) $i->lng,
            ])->values(),
        ]);
    }

    public function show(Request $request, string $slug): IniciativaResource
    {
        $iniciativa = Iniciativa::query()
            ->with(['zona', 'municipio.departamento', 'categoria', 'creador', 'items', 'puntosAcopio.municipio.departamento'])
            ->where('slug', $slug)
            ->firstOrFail();

        $viewer = $request->user();
        $esPublica = in_array($iniciativa->estado, [EstadoIniciativa::Publicada, EstadoIniciativa::EnCurso], true);
        $esDuenio = $viewer && $viewer->id === $iniciativa->user_id;
        $esModerador = $viewer && $viewer->can('iniciativas.moderate');

        abort_unless($esPublica || $esDuenio || $esModerador, 404);

        return new IniciativaResource($iniciativa);
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        $items = Iniciativa::query()
            ->with(['zona', 'municipio.departamento', 'categoria', 'items', 'puntosAcopio.municipio.departamento'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->paginate(20);

        return IniciativaResource::collection($items);
    }

    public function store(StoreIniciativaRequest $request): JsonResponse
    {
        $data = $request->validated();

        $iniciativa = DB::transaction(function () use ($request, $data) {
            $slug = $this->uniqueSlug($data['titulo']);

            $iniciativa = Iniciativa::query()->create([
                'user_id' => $request->user()->id,
                'zona_id' => $data['zona_id'] ?? null,
                'municipio_id' => $data['municipio_id'] ?? null,
                'categoria_id' => $data['categoria_id'],
                'slug' => $slug,
                'titulo' => $data['titulo'],
                'resumen' => $data['resumen'],
                'historia' => $data['historia'],
                'urgencia' => $data['urgencia'],
                'estado' => EstadoIniciativa::Borrador,
                'fecha_convite' => $data['fecha_convite'] ?? null,
                'fecha_limite_aportes' => $data['fecha_limite_aportes'] ?? null,
                'fecha_convite_texto' => $data['fecha_convite_texto'] ?? null,
                'lugar_convite' => $data['lugar_convite'],
                'lugar_exacto' => $data['lugar_exacto'] ?? null,
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'geo_fuente' => $data['geo_fuente'] ?? null,
                'geo_precision' => $data['geo_precision'] ?? 'punto',
                'mapa_visible' => array_key_exists('mapa_visible', $data)
                    ? (bool) $data['mapa_visible']
                    : true,
                'enlace_externo_plataforma' => $data['enlace_externo_plataforma'] ?? null,
                'enlace_externo_url' => $data['enlace_externo_url'] ?? null,
                'persona_responsable' => $data['persona_responsable'],
                'quien_respalda' => $data['quien_respalda'],
                'telefono_contacto' => $data['telefono_contacto'],
                'version' => 1,
                'acepta_terminos_at' => now(),
                'acepta_descargo_at' => now(),
            ]);

            $this->syncItems($iniciativa, $data['items']);
            if (array_key_exists('puntos_acopio', $data)) {
                $this->syncPuntosAcopio($iniciativa, $data['puntos_acopio'] ?? []);
            }

            return $iniciativa->load([
                'zona',
                'municipio.departamento',
                'categoria',
                'creador',
                'items',
                'puntosAcopio.municipio.departamento',
            ]);
        });

        return (new IniciativaResource($iniciativa))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateIniciativaRequest $request, Iniciativa $iniciativa): IniciativaResource
    {
        $this->authorize('update', $iniciativa);

        if (! in_array($iniciativa->estado, [
            EstadoIniciativa::Borrador,
            EstadoIniciativa::Rechazada,
            EstadoIniciativa::EnRevision,
        ], true) && ! $request->user()->can('iniciativas.moderate')) {
            abort(422, 'Solo puedes editar borradores o iniciativas con cambios solicitados.');
        }

        $data = $request->validated();
        $expectedVersion = (int) $data['version'];

        DB::transaction(function () use ($iniciativa, $data, $expectedVersion) {
            /** @var Iniciativa $locked */
            $locked = Iniciativa::query()
                ->whereKey($iniciativa->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->version !== $expectedVersion) {
                abort(409, 'Esta iniciativa cambió mientras la editabas. Recarga y vuelve a intentar.');
            }

            $locked->fill([
                'zona_id' => $data['zona_id'] ?? null,
                'municipio_id' => $data['municipio_id'] ?? null,
                'categoria_id' => $data['categoria_id'],
                'titulo' => $data['titulo'],
                'resumen' => $data['resumen'],
                'historia' => $data['historia'],
                'urgencia' => $data['urgencia'],
                'fecha_convite' => $data['fecha_convite'] ?? null,
                'fecha_limite_aportes' => $data['fecha_limite_aportes'] ?? null,
                'fecha_convite_texto' => $data['fecha_convite_texto'] ?? null,
                'lugar_convite' => $data['lugar_convite'],
                'lugar_exacto' => $data['lugar_exacto'] ?? null,
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'geo_fuente' => $data['geo_fuente'] ?? null,
                'geo_precision' => $data['geo_precision'] ?? 'punto',
                'mapa_visible' => array_key_exists('mapa_visible', $data)
                    ? (bool) $data['mapa_visible']
                    : $locked->mapa_visible,
                'enlace_externo_plataforma' => $data['enlace_externo_plataforma'] ?? null,
                'enlace_externo_url' => $data['enlace_externo_url'] ?? null,
                'persona_responsable' => $data['persona_responsable'],
                'quien_respalda' => $data['quien_respalda'],
                'telefono_contacto' => $data['telefono_contacto'],
                'version' => $locked->version + 1,
            ])->save();

            if (array_key_exists('items', $data)) {
                $this->syncItems($locked, $data['items']);
            }
            if (array_key_exists('puntos_acopio', $data)) {
                $this->syncPuntosAcopio($locked, $data['puntos_acopio'] ?? []);
            }
        });

        return new IniciativaResource($iniciativa->fresh([
            'zona',
            'municipio.departamento',
            'categoria',
            'creador',
            'items',
            'puntosAcopio.municipio.departamento',
        ]));
    }

    public function enviarRevision(Request $request, Iniciativa $iniciativa): IniciativaResource
    {
        $this->authorize('enviarRevision', $iniciativa);

        if (! in_array($iniciativa->estado, [EstadoIniciativa::Borrador, EstadoIniciativa::Rechazada], true)) {
            abort(422, 'Esta iniciativa no se puede enviar a revisión.');
        }

        if ($iniciativa->items()->count() === 0) {
            abort(422, 'Agrega al menos un ítem antes de enviar a revisión.');
        }

        $anterior = $iniciativa->estado;
        $iniciativa->forceFill([
            'estado' => EstadoIniciativa::EnRevision,
            'enviada_revision_at' => now(),
            'nota_moderacion' => null,
        ])->save();

        $iniciativa->moderacionAcciones()->create([
            'user_id' => $request->user()->id,
            'accion' => AccionModeracion::EnviarRevision,
            'estado_anterior' => $anterior,
            'estado_nuevo' => EstadoIniciativa::EnRevision,
        ]);

        $this->moderatorNotifications->notifyForMunicipio(
            $iniciativa->municipio_id,
            new IniciativaPendienteModeracionNotification($iniciativa),
        );

        SendConviteEnviadoRevisionJob::dispatch($iniciativa);

        return new IniciativaResource($iniciativa->fresh([
            'zona',
            'municipio.departamento',
            'categoria',
            'creador',
            'items',
            'puntosAcopio.municipio.departamento',
        ]));
    }

    /**
     * P43: el dueño cierra/detiene su propio convite (fuera del flujo de
     * moderación — distinto de `ModeracionIniciativaController::cerrar`,
     * que exige `iniciativas.moderate`).
     */
    public function cerrar(Request $request, Iniciativa $iniciativa): IniciativaResource
    {
        $this->authorize('close', $iniciativa);

        $data = $request->validate([
            'nota' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! in_array($iniciativa->estado, [
            EstadoIniciativa::Publicada,
            EstadoIniciativa::EnCurso,
        ], true)) {
            abort(422, 'Solo se pueden cerrar convites publicados o en curso.');
        }

        $anterior = $iniciativa->estado;
        $iniciativa->forceFill([
            'estado' => EstadoIniciativa::Cerrada,
            'cerrada_at' => now(),
            'nota_moderacion' => $data['nota'] ?? $iniciativa->nota_moderacion,
        ])->save();

        $iniciativa->moderacionAcciones()->create([
            'user_id' => $request->user()->id,
            'accion' => AccionModeracion::Cerrar,
            'estado_anterior' => $anterior,
            'estado_nuevo' => EstadoIniciativa::Cerrada,
            'nota' => $data['nota'] ?? null,
        ]);

        return new IniciativaResource($iniciativa->fresh([
            'zona',
            'municipio.departamento',
            'categoria',
            'creador',
            'items',
            'puntosAcopio.municipio.departamento',
        ]));
    }

    /**
     * @param  list<array{nombre: string, unidad: string, cantidad_meta: int, orden?: int}>  $items
     */
    private function syncItems(Iniciativa $iniciativa, array $items): void
    {
        $iniciativa->items()->delete();

        foreach (array_values($items) as $i => $item) {
            IniciativaItem::query()->create([
                'iniciativa_id' => $iniciativa->id,
                'nombre' => $item['nombre'],
                'unidad' => $item['unidad'],
                'cantidad_meta' => $item['cantidad_meta'],
                'cantidad_aportada' => 0,
                'orden' => $item['orden'] ?? ($i + 1),
            ]);
        }
    }

    /**
     * Reemplaza la lista de puntos de acopio (P33).
     *
     * @param  list<array{
     *   municipio_id: int,
     *   nombre: string,
     *   direccion: string,
     *   horario?: string|null,
     *   contacto?: string|null,
     *   notas?: string|null,
     *   centro_id?: int|null,
     *   lat?: float|null,
     *   lng?: float|null,
     *   orden?: int
     * }>  $puntos
     */
    private function syncPuntosAcopio(Iniciativa $iniciativa, array $puntos): void
    {
        $iniciativa->puntosAcopio()->delete();

        foreach (array_values($puntos) as $i => $punto) {
            IniciativaPuntoAcopio::query()->create([
                'iniciativa_id' => $iniciativa->id,
                'municipio_id' => $punto['municipio_id'],
                'centro_id' => $punto['centro_id'] ?? null,
                'nombre' => $punto['nombre'],
                'direccion' => $punto['direccion'],
                'horario' => $punto['horario'] ?? null,
                'contacto' => $punto['contacto'] ?? null,
                'notas' => $punto['notas'] ?? null,
                'lat' => $punto['lat'] ?? null,
                'lng' => $punto['lng'] ?? null,
                'orden' => $punto['orden'] ?? ($i + 1),
            ]);
        }
    }

    private function uniqueSlug(string $titulo): string
    {
        $base = Str::slug(Str::limit($titulo, 140, ''));
        $slug = $base !== '' ? $base : 'iniciativa';
        $i = 1;

        while (Iniciativa::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * FULLTEXT para términos ≥3 caracteres; LIKE para búsquedas cortas.
     */
    private function applyTituloResumenSearch($query, string $term): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }

        if (mb_strlen($term) < 3) {
            $like = '%'.$term.'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('titulo', 'like', $like)
                    ->orWhere('resumen', 'like', $like);
            });

            return;
        }

        $query->whereFullText(['titulo', 'resumen'], $term);
    }
}
