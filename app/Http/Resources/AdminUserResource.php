<?php

namespace App\Http\Resources;

use App\Enums\EstadoProfesional;
use App\Enums\EstadoSolicitudRol;
use App\Enums\TipoSolicitudRol;
use App\Models\Profesional;
use App\Models\SolicitudRol;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AdminUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        $base = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'celular' => $user->celular,
            'inicial' => $user->inicial,
            'roles' => $user->getRoleNames()->values()->all(),
            'roles_status' => $this->rolesStatus($user),
            'municipios' => $user->relationLoaded('municipiosAsignados')
                ? $user->municipiosAsignados->map(fn ($m) => [
                    'id' => $m->id,
                    'nombre' => $m->nombre,
                    'slug' => $m->slug,
                    'departamento' => $m->relationLoaded('departamento') && $m->departamento
                        ? [
                            'id' => $m->departamento->id,
                            'nombre' => $m->departamento->nombre,
                            'slug' => $m->departamento->slug,
                        ]
                        : null,
                ])->values()->all()
                : [],
            'created_at' => $user->created_at?->toIso8601String(),
            'solicitudes_rol' => $user->relationLoaded('solicitudesRol')
                ? SolicitudRolResource::collection(
                    $user->solicitudesRol
                        ->where('estado', EstadoSolicitudRol::Pendiente)
                        ->values(),
                )->resolve()
                : [],
            'profesional' => $user->relationLoaded('profesional') && $user->profesional
                ? (new ProfesionalResource($user->profesional))->resolve()
                : null,
        ];

        return $base;
    }

    /**
     * @return array{ciudadano: string, moderador: string, voluntario: string, profesional: string}
     */
    private function rolesStatus(User $user): array
    {
        return [
            'ciudadano' => $user->hasRole('member') ? 'active' : 'none',
            'moderador' => $this->statusForStaffRole(
                $user,
                'moderator',
                TipoSolicitudRol::Moderador,
            ),
            'voluntario' => $this->statusForStaffRole(
                $user,
                'voluntario',
                TipoSolicitudRol::Voluntario,
            ),
            'profesional' => $this->statusForProfesional($user),
        ];
    }

    private function statusForStaffRole(User $user, string $spatieRole, TipoSolicitudRol $tipo): string
    {
        if ($user->hasRole($spatieRole)) {
            return 'active';
        }

        if ($this->hasPendingSolicitud($user, $tipo)) {
            return 'pending';
        }

        return 'none';
    }

    private function statusForProfesional(User $user): string
    {
        if ($user->hasRole('profesional')) {
            return 'active';
        }

        /** @var Profesional|null $pro */
        $pro = $user->relationLoaded('profesional')
            ? $user->profesional
            : $user->profesional()->first();

        if ($pro && in_array($pro->estado, [
            EstadoProfesional::Pendiente,
            EstadoProfesional::CambiosSolicitados,
        ], true)) {
            return 'pending';
        }

        return 'none';
    }

    private function hasPendingSolicitud(User $user, TipoSolicitudRol $tipo): bool
    {
        if ($user->relationLoaded('solicitudesRol')) {
            return $user->solicitudesRol->contains(
                fn (SolicitudRol $s) => $s->rol === $tipo
                    && $s->estado === EstadoSolicitudRol::Pendiente,
            );
        }

        return $user->solicitudesRol()
            ->where('rol', $tipo)
            ->where('estado', EstadoSolicitudRol::Pendiente)
            ->exists();
    }
}
