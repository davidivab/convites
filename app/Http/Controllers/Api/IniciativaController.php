<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccionModeracion;
use App\Enums\EstadoIniciativa;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIniciativaRequest;
use App\Http\Requests\UpdateIniciativaRequest;
use App\Http\Resources\IniciativaResource;
use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use App\Notifications\IniciativaPendienteModeracionNotification;
use App\Services\ModeratorNotificationService;
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
            ->with(['zona', 'municipio.departamento', 'categoria', 'creador', 'items'])
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
            $query->orderByDesc('publicada_at')->orderByDesc('id');
        }

        return IniciativaResource::collection(
            $query->paginate(min(50, max(1, (int) $request->input('per_page', 12))))
        );
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
            ->with(['zona', 'municipio.departamento', 'categoria', 'creador', 'items'])
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
            ->with(['zona', 'municipio.departamento', 'categoria', 'items'])
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

            return $iniciativa->load(['zona', 'municipio.departamento', 'categoria', 'creador', 'items']);
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
        });

        return new IniciativaResource($iniciativa->fresh(['zona', 'municipio.departamento', 'categoria', 'creador', 'items']));
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

        return new IniciativaResource($iniciativa->fresh(['zona', 'municipio.departamento', 'categoria', 'creador', 'items']));
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
