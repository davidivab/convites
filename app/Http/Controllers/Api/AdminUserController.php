<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoProfesional;
use App\Enums\EstadoSolicitudRol;
use App\Enums\TipoSolicitudRol;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\SyncUserMunicipiosRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\Activity;
use App\Models\User;
use App\Services\ActivityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/**
 * Administración de usuarios y municipios asignados.
 * P56: listado unificado con roles_status + detalle.
 */
class AdminUserController extends Controller
{
    public function __construct(
        private readonly ActivityService $activities,
    ) {}

    /**
     * Sin `todos=1` ni `tipo`: solo moderador/voluntario (histórico P19–P21).
     * Con `todos=1` o `tipo`: cualquier usuario. `tipo` acota por rol activo o pendiente.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query()
            ->with([
                'municipiosAsignados.departamento',
                'roles',
                'profesional',
                'solicitudesRol' => fn ($q) => $q->where('estado', EstadoSolicitudRol::Pendiente),
            ]);

        $tipo = $request->filled('tipo')
            ? (string) $request->string('tipo')
            : null;

        if ($tipo !== null) {
            $this->applyTipoFilter($query, $tipo);
        } elseif (! $request->boolean('todos')) {
            $query->role(['moderator', 'voluntario']);
        }

        if ($request->filled('role') && $tipo === null) {
            $query->role((string) $request->string('role'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('celular', 'like', $term);
            });
        }

        $sort = (string) $request->input('sort', 'name');
        $order = strtolower((string) $request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';
        if (! in_array($sort, ['name', 'email', 'created_at'], true)) {
            $sort = 'name';
        }
        $query->orderBy($sort, $order);

        $perPage = min(100, max(1, (int) $request->input('per_page', 30)));

        return AdminUserResource::collection($query->paginate($perPage));
    }

    public function show(User $user): AdminUserResource
    {
        $user->load([
            'municipiosAsignados.departamento',
            'roles',
            'solicitudesRol' => fn ($q) => $q
                ->where('estado', EstadoSolicitudRol::Pendiente)
                ->with('municipios'),
            'profesional.documentos',
            'profesional.zona',
        ]);

        return new AdminUserResource($user);
    }

    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'celular' => $data['celular'] ?? null,
            'inicial' => Str::upper(Str::substr($data['name'], 0, 1)),
        ]);

        $user->forceFill([
            'acepta_terminos_at' => now(),
            'acepta_descargo_at' => now(),
        ])->save();

        $user->syncRoles([$data['role']]);
        $user->municipiosAsignados()->sync($data['municipio_ids']);

        $this->activities->createActivityForModel([
            'message' => "Usuario {$user->email} creado con rol {$data['role']}",
            'status_text' => 'creado',
            'status' => 'user_created',
            'color' => Activity::COLOR_PRIMARY,
            'data' => [
                'role' => $data['role'],
                'municipio_ids' => $data['municipio_ids'],
            ],
        ], $user);

        return (new AdminUserResource(
            $user->load([
                'municipiosAsignados.departamento',
                'roles',
                'profesional',
                'solicitudesRol' => fn ($q) => $q->where('estado', EstadoSolicitudRol::Pendiente),
            ]),
        ))->response()->setStatusCode(201);
    }

    public function syncMunicipios(SyncUserMunicipiosRequest $request, User $user): AdminUserResource
    {
        abort_unless(
            $user->hasAnyRole(['moderator', 'voluntario']),
            422,
            'Solo se asignan municipios a moderadores o voluntarios.',
        );

        $user->municipiosAsignados()->sync($request->validated('municipio_ids'));

        return new AdminUserResource(
            $user->fresh()->load([
                'municipiosAsignados.departamento',
                'roles',
                'profesional',
                'solicitudesRol' => fn ($q) => $q->where('estado', EstadoSolicitudRol::Pendiente),
            ]),
        );
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applyTipoFilter(Builder $query, string $tipo): void
    {
        match ($tipo) {
            'todos', 'ciudadano' => null,
            'moderador' => $this->whereRoleOrPendingSolicitud(
                $query,
                'moderator',
                TipoSolicitudRol::Moderador,
            ),
            'voluntario' => $this->whereRoleOrPendingSolicitud(
                $query,
                'voluntario',
                TipoSolicitudRol::Voluntario,
            ),
            'profesional' => $query->where(function (Builder $q): void {
                $q->role('profesional')
                    ->orWhereHas('profesional', function (Builder $pq): void {
                        $pq->whereIn('estado', [
                            EstadoProfesional::Pendiente->value,
                            EstadoProfesional::CambiosSolicitados->value,
                        ]);
                    });
            }),
            'pendientes' => $query->where(function (Builder $q): void {
                $q->whereHas('solicitudesRol', function (Builder $sq): void {
                    $sq->where('estado', EstadoSolicitudRol::Pendiente);
                })->orWhereHas('profesional', function (Builder $pq): void {
                    $pq->whereIn('estado', [
                        EstadoProfesional::Pendiente->value,
                        EstadoProfesional::CambiosSolicitados->value,
                    ]);
                });
            }),
            default => abort(422, 'tipo inválido. Usa: todos, ciudadano, moderador, voluntario, profesional, pendientes.'),
        };

        // ciudadano = misma base que todos (cuenta registrada); el front filtra visualmente.
        if ($tipo === 'ciudadano') {
            $query->role('member');
        }
    }

    /**
     * @param  Builder<User>  $query
     */
    private function whereRoleOrPendingSolicitud(
        Builder $query,
        string $spatieRole,
        TipoSolicitudRol $tipo,
    ): void {
        $query->where(function (Builder $q) use ($spatieRole, $tipo): void {
            $q->role($spatieRole)
                ->orWhereHas('solicitudesRol', function (Builder $sq) use ($tipo): void {
                    $sq->where('rol', $tipo)
                        ->where('estado', EstadoSolicitudRol::Pendiente);
                });
        });
    }
}
