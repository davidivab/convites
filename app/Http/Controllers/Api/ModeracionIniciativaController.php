<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccionModeracion;
use App\Enums\EstadoIniciativa;
use App\Http\Controllers\Controller;
use App\Http\Resources\IniciativaResource;
use App\Jobs\SendConviteAprobadoJob;
use App\Models\Activity;
use App\Models\Iniciativa;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Cola de moderación de iniciativas.
 */
class ModeracionIniciativaController extends Controller
{
    public function __construct(
        private readonly ActivityService $activities,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $estado = $request->input('estado', EstadoIniciativa::EnRevision->value);

        $query = Iniciativa::query()
            ->with(['zona', 'municipio.departamento', 'categoria', 'creador', 'items', 'galeria', 'enlaces'])
            ->orderBy('enviada_revision_at');

        if ($estado !== 'todas') {
            $query->where('estado', $estado);
        }

        // Moderador: solo municipios asignados. Admin: sin filtro.
        if ($user && ! $user->isPlatformAdmin()) {
            $ids = $user->assignedMunicipioIds();
            $query->whereIn('municipio_id', $ids !== [] ? $ids : [-1]);
        }

        return IniciativaResource::collection($query->paginate(20));
    }

    public function aprobar(Request $request, Iniciativa $iniciativa): IniciativaResource
    {
        $data = $request->validate([
            'nota' => ['nullable', 'string', 'max:1000'],
            'destacada' => ['sometimes', 'boolean'],
        ]);

        $resource = $this->transicionar(
            $request,
            $iniciativa,
            AccionModeracion::Aprobar,
            EstadoIniciativa::Publicada,
            [
                'publicada_at' => now(),
                'moderada_por' => $request->user()->id,
                'nota_moderacion' => $data['nota'] ?? null,
                'destacada' => $data['destacada'] ?? $iniciativa->destacada,
            ],
        );

        SendConviteAprobadoJob::dispatch($iniciativa->fresh());

        return $resource;
    }

    public function rechazar(Request $request, Iniciativa $iniciativa): IniciativaResource
    {
        $data = $request->validate([
            'nota' => ['required', 'string', 'max:1000'],
        ]);

        return $this->transicionar(
            $request,
            $iniciativa,
            AccionModeracion::Rechazar,
            EstadoIniciativa::Rechazada,
            [
                'moderada_por' => $request->user()->id,
                'nota_moderacion' => $data['nota'],
            ],
        );
    }

    public function solicitarCambios(Request $request, Iniciativa $iniciativa): IniciativaResource
    {
        $data = $request->validate([
            'nota' => ['required', 'string', 'max:1000'],
        ]);

        return $this->transicionar(
            $request,
            $iniciativa,
            AccionModeracion::SolicitarCambios,
            EstadoIniciativa::Borrador,
            [
                'moderada_por' => $request->user()->id,
                'nota_moderacion' => $data['nota'],
            ],
        );
    }

    public function cerrar(Request $request, Iniciativa $iniciativa): IniciativaResource
    {
        $data = $request->validate([
            'nota' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->transicionar(
            $request,
            $iniciativa,
            AccionModeracion::Cerrar,
            EstadoIniciativa::Cerrada,
            [
                'cerrada_at' => now(),
                'moderada_por' => $request->user()->id,
                'nota_moderacion' => $data['nota'] ?? $iniciativa->nota_moderacion,
            ],
            allowedFrom: [
                EstadoIniciativa::Publicada,
                EstadoIniciativa::EnCurso,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  list<EstadoIniciativa>|null  $allowedFrom
     */
    private function transicionar(
        Request $request,
        Iniciativa $iniciativa,
        AccionModeracion $accion,
        EstadoIniciativa $nuevo,
        array $extra,
        ?array $allowedFrom = null,
    ): IniciativaResource {
        $this->authorize('moderate', $iniciativa);

        $allowedFrom ??= [EstadoIniciativa::EnRevision];

        if (! in_array($iniciativa->estado, $allowedFrom, true)) {
            abort(422, 'Transición de estado no permitida.');
        }

        $anterior = $iniciativa->estado;

        $iniciativa->forceFill(array_merge([
            'estado' => $nuevo,
        ], $extra))->save();

        $iniciativa->moderacionAcciones()->create([
            'user_id' => $request->user()->id,
            'accion' => $accion,
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'nota' => $extra['nota_moderacion'] ?? null,
        ]);

        $this->activities->createActivityForModel([
            'message' => "Moderación {$accion->value}: iniciativa {$iniciativa->slug}",
            'status_text' => $accion->value,
            'status' => $accion->value,
            'color' => match ($accion) {
                AccionModeracion::Aprobar, AccionModeracion::Publicar => Activity::COLOR_SUCCESS,
                AccionModeracion::Rechazar => Activity::COLOR_DANGER,
                AccionModeracion::SolicitarCambios => Activity::COLOR_WARNING,
                default => Activity::COLOR_INFO,
            },
            'data' => [
                'estado_anterior' => $anterior?->value,
                'estado_nuevo' => $nuevo->value,
                'nota' => $extra['nota_moderacion'] ?? null,
            ],
        ], $iniciativa);

        return new IniciativaResource($iniciativa->fresh(['zona', 'municipio.departamento', 'categoria', 'creador', 'items', 'galeria', 'enlaces']));
    }
}
