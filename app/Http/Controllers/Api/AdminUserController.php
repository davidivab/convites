<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\SyncUserMunicipiosRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\Activity;
use App\Models\User;
use App\Services\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/**
 * Administración de usuarios (moderadores / voluntarios) y municipios asignados.
 */
class AdminUserController extends Controller
{
    public function __construct(
        private readonly ActivityService $activities,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query()
            ->with(['municipiosAsignados.departamento', 'roles'])
            ->role(['moderator', 'voluntario'])
            ->orderBy('name');

        if ($request->filled('role')) {
            $query->role((string) $request->string('role'));
        }

        return AdminUserResource::collection($query->paginate(30));
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
            $user->load(['municipiosAsignados.departamento', 'roles']),
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
            $user->fresh()->load(['municipiosAsignados.departamento', 'roles']),
        );
    }
}
