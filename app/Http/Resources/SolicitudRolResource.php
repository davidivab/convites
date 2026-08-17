<?php

namespace App\Http\Resources;

use App\Models\SolicitudRol;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SolicitudRol
 */
class SolicitudRolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SolicitudRol $solicitud */
        $solicitud = $this->resource;

        return [
            'id' => $solicitud->id,
            'rol' => $solicitud->rol?->value,
            'rol_label' => $solicitud->rol?->label(),
            'mensaje' => $solicitud->mensaje,
            'estado' => $solicitud->estado?->value,
            'estado_label' => $solicitud->estado?->label(),
            'nota_revision' => $solicitud->nota_revision,
            'municipios' => $solicitud->relationLoaded('municipios')
                ? $solicitud->municipios->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->nombre])->values()->all()
                : [],
            'user' => $solicitud->relationLoaded('user') && $solicitud->user ? [
                'id' => $solicitud->user->id,
                'name' => $solicitud->user->name,
                'email' => $solicitud->user->email,
            ] : null,
            'created_at' => $solicitud->created_at?->toIso8601String(),
            'revisado_at' => $solicitud->revisado_at?->toIso8601String(),
        ];
    }
}
