<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AsignarCreadorIniciativaRequest;
use App\Http\Resources\AporteResource;
use App\Http\Resources\IniciativaResource;
use App\Jobs\SendConviteAsignadoJob;
use App\Models\Activity;
use App\Models\Aporte;
use App\Models\Iniciativa;
use App\Models\User;
use App\Services\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Lectura admin sin scope de municipio (auditoría) + reasignar creador.
 */
class AdminIniciativaController extends Controller
{
    public function __construct(
        private readonly ActivityService $activities,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Iniciativa::query()
            ->with(['zona', 'municipio.departamento', 'categoria', 'creador', 'items', 'galeria', 'enlaces'])
            ->orderByDesc('updated_at');

        if ($request->filled('estado') && $request->string('estado') !== 'todas') {
            $query->where('estado', $request->string('estado'));
        }

        if ($request->filled('municipio_id')) {
            $query->where('municipio_id', (int) $request->input('municipio_id'));
        }

        if ($request->filled('categoria')) {
            $query->whereHas('categoria', fn ($q) => $q->where('slug', $request->string('categoria')));
        }

        if ($request->filled('urgencia')) {
            $query->where('urgencia', $request->string('urgencia'));
        }

        if ($request->filled('desde')) {
            $query->whereDate('created_at', '>=', $request->string('desde'));
        }

        if ($request->filled('hasta')) {
            $query->whereDate('created_at', '<=', $request->string('hasta'));
        }

        if ($request->filled('q')) {
            $raw = trim((string) $request->string('q'));
            if (mb_strlen($raw) >= 3) {
                $term = '%'.$raw.'%';
                $query->where(function ($builder) use ($term) {
                    $builder->where('titulo', 'like', $term)
                        ->orWhere('resumen', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhere('telefono_contacto', 'like', $term)
                        ->orWhere('persona_responsable', 'like', $term)
                        ->orWhereHas('creador', function ($q) use ($term) {
                            $q->where('name', 'like', $term)
                                ->orWhere('email', 'like', $term);
                        });
                });
            }
        }

        return IniciativaResource::collection(
            $query->paginate(min(50, max(1, (int) $request->input('per_page', 20)))),
        );
    }

    public function show(string $slug): JsonResponse
    {
        $iniciativa = Iniciativa::query()
            ->with([
                'zona',
                'municipio.departamento',
                'categoria',
                'creador.municipio.departamento',
                'items',
                'puntosAcopio.municipio.departamento',
                'proveedores',
                'galeria',
                'enlaces',
                'moderacionAcciones.user',
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json(['data' => $this->adminDetallePayload($iniciativa)]);
    }

    /**
     * Reasigna el dueño del convite a otro ciudadano (por correo).
     *
     * Condiciones: el correo debe existir, ser distinto del actual, y el
     * usuario debe poder crear convites (`iniciativas.create`).
     */
    public function asignarCreador(AsignarCreadorIniciativaRequest $request, string $slug): JsonResponse
    {
        $iniciativa = Iniciativa::query()
            ->with(['creador'])
            ->where('slug', $slug)
            ->firstOrFail();

        $email = mb_strtolower(trim((string) $request->validated('email')));

        /** @var User|null $nuevo */
        $nuevo = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $nuevo) {
            throw ValidationException::withMessages([
                'email' => ['No hay un ciudadano registrado con ese correo.'],
            ]);
        }

        if ($nuevo->id === $iniciativa->user_id) {
            throw ValidationException::withMessages([
                'email' => ['Esa persona ya es la creadora de este convite.'],
            ]);
        }

        if (! $nuevo->can('iniciativas.create')) {
            throw ValidationException::withMessages([
                'email' => ['Ese usuario no puede ser creador de convites (falta permiso para crear).'],
            ]);
        }

        $anteriorId = $iniciativa->user_id;
        $iniciativa->user_id = $nuevo->id;
        $iniciativa->version = (int) $iniciativa->version + 1;
        $iniciativa->save();

        $this->activities->createActivityForModel([
            'message' => "Admin reasignó creador de iniciativa {$iniciativa->slug}",
            'status_text' => 'asignar_creador',
            'status' => 'asignar_creador',
            'color' => Activity::COLOR_INFO,
            'data' => [
                'iniciativa_id' => $iniciativa->id,
                'user_id_anterior' => $anteriorId,
                'user_id_nuevo' => $nuevo->id,
                'email_nuevo' => $nuevo->email,
            ],
        ], $iniciativa);

        SendConviteAsignadoJob::dispatch($iniciativa, $nuevo);

        $iniciativa->load([
            'zona',
            'municipio.departamento',
            'categoria',
            'creador.municipio.departamento',
            'items',
            'puntosAcopio.municipio.departamento',
            'proveedores',
            'galeria',
            'enlaces',
            'moderacionAcciones.user',
        ]);

        return response()->json(['data' => $this->adminDetallePayload($iniciativa)]);
    }

    public function aportes(Request $request, string $slug): AnonymousResourceCollection
    {
        $iniciativa = Iniciativa::query()->where('slug', $slug)->firstOrFail();

        // Flag para que AporteResource muestre nombres anónimos al admin.
        $request->attributes->set('admin_reveal_anonimo', true);

        $aportes = Aporte::query()
            ->with(['items.iniciativaItem', 'user', 'iniciativa'])
            ->where('iniciativa_id', $iniciativa->id)
            ->orderByDesc('created_at')
            ->paginate(50);

        return AporteResource::collection($aportes);
    }

    /**
     * @return array<string, mixed>
     */
    private function adminDetallePayload(Iniciativa $iniciativa): array
    {
        $payload = (new IniciativaResource($iniciativa))->resolve();

        $payload['verificacion'] = [
            'persona_responsable' => $iniciativa->persona_responsable,
            'quien_respalda' => $iniciativa->quien_respalda,
            'telefono_contacto' => $iniciativa->telefono_contacto,
            'lugar_exacto' => $iniciativa->lugar_exacto,
        ];

        $payload['creador'] = $this->creadorAdminPayload($iniciativa->creador);

        $payload['moderacion_historial'] = $iniciativa->relationLoaded('moderacionAcciones')
            ? $iniciativa->moderacionAcciones
                ->sortBy('created_at')
                ->values()
                ->map(fn ($accion) => [
                    'id' => $accion->id,
                    'accion' => $accion->accion?->value,
                    'estado_anterior' => $accion->estado_anterior?->value,
                    'estado_nuevo' => $accion->estado_nuevo?->value,
                    'nota' => $accion->nota,
                    'moderador' => $accion->user
                        ? [
                            'id' => $accion->user->id,
                            'name' => $accion->user->name,
                        ]
                        : null,
                    'created_at' => $accion->created_at?->toIso8601String(),
                ])
                ->all()
            : [];

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function creadorAdminPayload(?User $creador): ?array
    {
        if (! $creador) {
            return null;
        }

        $municipio = null;
        if ($creador->relationLoaded('municipio') && $creador->municipio) {
            $municipio = [
                'id' => $creador->municipio->id,
                'nombre' => $creador->municipio->nombre,
                'slug' => $creador->municipio->slug,
                'departamento' => $creador->municipio->relationLoaded('departamento') && $creador->municipio->departamento
                    ? [
                        'id' => $creador->municipio->departamento->id,
                        'nombre' => $creador->municipio->departamento->nombre,
                        'slug' => $creador->municipio->departamento->slug,
                    ]
                    : null,
            ];
        }

        return [
            'id' => $creador->id,
            'name' => $creador->name,
            'inicial' => $creador->inicial,
            'email' => $creador->email,
            'celular' => $creador->celular,
            'barrio' => $creador->barrio,
            'municipio' => $municipio,
        ];
    }
}
