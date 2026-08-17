<?php

namespace App\Http\Resources;

use App\Models\ProfesionalSolicitud;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProfesionalSolicitud
 */
class ProfesionalSolicitudResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProfesionalSolicitud $solicitud */
        $solicitud = $this->resource;

        return [
            'id' => $solicitud->id,
            'nombre' => $solicitud->nombre,
            'celular' => $solicitud->celular,
            'email' => $solicitud->email,
            'preferencia_contacto' => $solicitud->preferencia_contacto?->value,
            'mensaje' => $solicitud->mensaje,
            'estado' => $solicitud->estado?->value,
            'created_at' => $solicitud->created_at?->toIso8601String(),
        ];
    }
}
