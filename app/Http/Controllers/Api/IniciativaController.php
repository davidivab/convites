<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccionModeracion;
use App\Enums\EstadoIniciativa;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIniciativaGaleriaRequest;
use App\Http\Requests\StoreIniciativaRequest;
use App\Http\Requests\UpdateIniciativaRequest;
use App\Http\Resources\IniciativaGaleriaResource;
use App\Http\Resources\IniciativaResource;
use App\Jobs\SendConviteEnviadoRevisionJob;
use App\Models\Iniciativa;
use App\Models\IniciativaEnlace;
use App\Models\IniciativaGaleria;
use App\Models\IniciativaItem;
use App\Models\IniciativaProveedor;
use App\Models\IniciativaPuntoAcopio;
use App\Notifications\IniciativaPendienteModeracionNotification;
use App\Services\IniciativaProgresoService;
use App\Services\ModeratorNotificationService;
use App\Support\UniqueSlug;
use App\Support\UploadDisk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Listado público, CRUD propio y envío a revisión de iniciativas.
 */
class IniciativaController extends Controller
{
    public function __construct(
        private readonly ModeratorNotificationService $moderatorNotifications,
        private readonly IniciativaProgresoService $progreso,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Iniciativa::query()
            ->with(['zona', 'municipio.departamento', 'categoria', 'creador', 'items', 'puntosAcopio.municipio.departamento', 'proveedores', 'galeria', 'enlaces'])
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
            ->with(['zona', 'municipio.departamento', 'categoria', 'creador', 'items', 'puntosAcopio.municipio.departamento', 'proveedores', 'galeria', 'enlaces'])
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
            ->with(['zona', 'municipio.departamento', 'categoria', 'items', 'puntosAcopio.municipio.departamento', 'proveedores', 'galeria', 'enlaces'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->paginate(20);

        return IniciativaResource::collection($items);
    }

    public function store(StoreIniciativaRequest $request): JsonResponse
    {
        $data = $request->validated();

        $iniciativa = DB::transaction(function () use ($request, $data) {
            $slug = UniqueSlug::forIniciativa($data['titulo']);

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
                'lugar_convite' => $data['lugar_convite'] ?? null,
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
                'persona_responsable' => $data['persona_responsable'] ?? null,
                'quien_respalda' => $data['quien_respalda'] ?? null,
                'telefono_contacto' => $data['telefono_contacto'] ?? null,
                'version' => 1,
                'wizard_paso' => $data['wizard_paso'] ?? null,
                'acepta_terminos_at' => now(),
                'acepta_descargo_at' => now(),
            ]);

            $this->syncItems($iniciativa, $data['items'] ?? []);
            if (array_key_exists('puntos_acopio', $data)) {
                $this->syncPuntosAcopio($iniciativa, $data['puntos_acopio'] ?? []);
            }
            if (array_key_exists('proveedores', $data)) {
                $this->syncProveedores($iniciativa, $data['proveedores'] ?? []);
            }
            if (array_key_exists('enlaces', $data)) {
                $this->syncEnlaces($iniciativa, $data['enlaces'] ?? []);
            }

            return $iniciativa->load([
                'zona',
                'municipio.departamento',
                'categoria',
                'creador',
                'items',
                'puntosAcopio.municipio.departamento',
                'proveedores',
                'galeria',
                'enlaces',
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
                // Campos propios de pasos posteriores del wizard: si el autosave de un paso
                // previo no los envía, se conserva el valor ya persistido en lugar de borrarlo.
                'lugar_convite' => array_key_exists('lugar_convite', $data)
                    ? $data['lugar_convite']
                    : $locked->lugar_convite,
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
                'persona_responsable' => array_key_exists('persona_responsable', $data)
                    ? $data['persona_responsable']
                    : $locked->persona_responsable,
                'quien_respalda' => array_key_exists('quien_respalda', $data)
                    ? $data['quien_respalda']
                    : $locked->quien_respalda,
                'telefono_contacto' => array_key_exists('telefono_contacto', $data)
                    ? $data['telefono_contacto']
                    : $locked->telefono_contacto,
                'version' => $locked->version + 1,
                'wizard_paso' => array_key_exists('wizard_paso', $data)
                    ? $data['wizard_paso']
                    : $locked->wizard_paso,
            ])->save();

            if (array_key_exists('items', $data)) {
                $this->syncItems($locked, $data['items']);
                // syncItems recrea ítems con cantidad_aportada=0; sin esto
                // progreso_cache queda stale (p.ej. "20% listo" con 0/meta).
                $this->progreso->recalcular($locked);
            }
            if (array_key_exists('puntos_acopio', $data)) {
                $this->syncPuntosAcopio($locked, $data['puntos_acopio'] ?? []);
            }
            if (array_key_exists('proveedores', $data)) {
                $this->syncProveedores($locked, $data['proveedores'] ?? []);
            }
            if (array_key_exists('enlaces', $data)) {
                $this->syncEnlaces($locked, $data['enlaces'] ?? []);
            }
        });

        return new IniciativaResource($iniciativa->fresh([
            'zona',
            'municipio.departamento',
            'categoria',
            'creador',
            'items',
            'puntosAcopio.municipio.departamento',
            'proveedores',
            'galeria',
            'enlaces',
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
            'proveedores',
            'galeria',
            'enlaces',
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
            'proveedores',
            'galeria',
            'enlaces',
        ]));
    }

    /**
     * P53 (parte 3): portada del convite (`imagen_path`). Misma autorización
     * que el resto de acciones de mutación: dueño o moderador/admin.
     */
    public function imagenPortada(Request $request, Iniciativa $iniciativa): IniciativaResource
    {
        $this->authorize('update', $iniciativa);

        $data = $request->validate([
            'imagen' => ['required', 'image', 'max:5120'],
        ]);

        $disk = UploadDisk::name();
        $path = $data['imagen']->store('iniciativas/portada', $disk);

        $iniciativa->forceFill(['imagen_path' => $path])->save();

        return new IniciativaResource($iniciativa->fresh([
            'zona',
            'municipio.departamento',
            'categoria',
            'creador',
            'items',
            'puntosAcopio.municipio.departamento',
            'proveedores',
            'galeria',
            'enlaces',
        ]));
    }

    /**
     * P53 (parte 3): sube una imagen a la galería. `orden` es autoincremental
     * por iniciativa. `version` en la respuesta es la de la Iniciativa (bump
     * de optimistic-lock, mismo patrón que `update()`) — no un campo propio
     * del item de galería — así el front actualiza su lock local sin
     * necesitar un refetch.
     *
     * ancho/alto se obtienen con getimagesize() (núcleo de PHP, no requiere
     * ninguna librería de procesamiento de imágenes) y quedan en null solo
     * si el archivo subido no es una imagen legible por esa función.
     */
    public function galeriaStore(StoreIniciativaGaleriaRequest $request, Iniciativa $iniciativa): JsonResponse
    {
        $this->authorize('update', $iniciativa);

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

        $path = $archivo->store('iniciativas/galeria', $disk);

        [$item, $version] = DB::transaction(function () use ($iniciativa, $path, $tipo, $ancho, $alto, $esVideo, $data) {
            /** @var Iniciativa $locked */
            $locked = Iniciativa::query()
                ->whereKey($iniciativa->id)
                ->lockForUpdate()
                ->firstOrFail();

            $siguienteOrden = (int) $locked->galeria()->max('orden') + 1;

            $item = IniciativaGaleria::query()->create([
                'iniciativa_id' => $locked->id,
                'path' => $path,
                'tipo' => $tipo,
                'orden' => $siguienteOrden,
                'ancho' => $ancho,
                'alto' => $alto,
                'duracion_segundos' => $esVideo ? ($data['duracion_segundos'] ?? null) : null,
            ]);

            $locked->version = $locked->version + 1;
            $locked->save();

            return [$item, $locked->version];
        });

        $payload = (new IniciativaGaleriaResource($item))->toArray($request);
        $payload['version'] = $version;

        return response()->json(['data' => $payload], 201);
    }

    /**
     * P53 (parte 3): elimina un item de galería. Debe pertenecer a la
     * iniciativa indicada en la ruta (si no, 404). Bump de versión igual
     * que el resto de mutaciones.
     */
    public function galeriaDestroy(Request $request, Iniciativa $iniciativa, int $galeriaId): IniciativaResource
    {
        $this->authorize('update', $iniciativa);

        $item = IniciativaGaleria::query()
            ->where('iniciativa_id', $iniciativa->id)
            ->whereKey($galeriaId)
            ->firstOrFail();

        DB::transaction(function () use ($iniciativa, $item) {
            /** @var Iniciativa $locked */
            $locked = Iniciativa::query()
                ->whereKey($iniciativa->id)
                ->lockForUpdate()
                ->firstOrFail();

            Storage::disk(UploadDisk::name())->delete($item->path);
            $item->delete();

            $locked->version = $locked->version + 1;
            $locked->save();
        });

        return new IniciativaResource($iniciativa->fresh([
            'zona',
            'municipio.departamento',
            'categoria',
            'creador',
            'items',
            'puntosAcopio.municipio.departamento',
            'proveedores',
            'galeria',
            'enlaces',
        ]));
    }

    /**
     * @param  list<array{nombre: string, unidad: string, cantidad_meta: int, descripcion?: string|null, valor_unitario_aprox?: float|null, orden?: int}>  $items
     */
    private function syncItems(Iniciativa $iniciativa, array $items): void
    {
        $iniciativa->items()->delete();

        foreach (array_values($items) as $i => $item) {
            IniciativaItem::query()->create([
                'iniciativa_id' => $iniciativa->id,
                'nombre' => $item['nombre'],
                'unidad' => $item['unidad'],
                'descripcion' => $item['descripcion'] ?? null,
                'valor_unitario_aprox' => $item['valor_unitario_aprox'] ?? null,
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

    /**
     * Reemplaza la lista de proveedores de la iniciativa — mismo patrón
     * "reemplazo total, sin diff" que `syncItems`/`syncPuntosAcopio`.
     *
     * @param  list<array{
     *   nombre: string,
     *   direccion?: string|null,
     *   ciudad?: string|null,
     *   correo?: string|null,
     *   celular?: string|null,
     *   instrucciones_pago: string,
     *   orden?: int
     * }>  $proveedores
     */
    private function syncProveedores(Iniciativa $iniciativa, array $proveedores): void
    {
        $iniciativa->proveedores()->delete();

        foreach (array_values($proveedores) as $i => $proveedor) {
            IniciativaProveedor::query()->create([
                'iniciativa_id' => $iniciativa->id,
                'nombre' => $proveedor['nombre'],
                'direccion' => $proveedor['direccion'] ?? null,
                'ciudad' => $proveedor['ciudad'] ?? null,
                'correo' => $proveedor['correo'] ?? null,
                'celular' => $proveedor['celular'] ?? null,
                'instrucciones_pago' => $proveedor['instrucciones_pago'],
                'orden' => $proveedor['orden'] ?? ($i + 1),
            ]);
        }
    }

    /**
     * Reemplaza la lista de enlaces del convite (P53, parte 3) — mismo
     * patrón "reemplazo total, sin diff" que `syncItems`/`syncPuntosAcopio`.
     *
     * @param  list<array{titulo: string, url: string, orden?: int}>  $enlaces
     */
    private function syncEnlaces(Iniciativa $iniciativa, array $enlaces): void
    {
        $iniciativa->enlaces()->delete();

        foreach (array_values($enlaces) as $i => $enlace) {
            IniciativaEnlace::query()->create([
                'iniciativa_id' => $iniciativa->id,
                'titulo' => $enlace['titulo'],
                'url' => $enlace['url'],
                'orden' => $enlace['orden'] ?? ($i + 1),
            ]);
        }
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
