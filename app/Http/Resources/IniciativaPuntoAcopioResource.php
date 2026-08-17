<?php

namespace App\Http\Resources;

use App\Models\IniciativaPuntoAcopio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IniciativaPuntoAcopio
 */
class IniciativaPuntoAcopioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var IniciativaPuntoAcopio $punto */
        $punto = $this->resource;

        return [
            'id' => $punto->id,
            'nombre' => $punto->nombre,
            'direccion' => $punto->direccion,
            'horario' => $punto->horario,
            'contacto' => $punto->contacto,
            'notas' => $punto->notas,
            'orden' => $punto->orden,
            'lat' => $punto->lat,
            'lng' => $punto->lng,
            'centro_id' => $punto->centro_id,
            'municipio' => $punto->relationLoaded('municipio') && $punto->municipio ? [
                'id' => $punto->municipio->id,
                'slug' => $punto->municipio->slug,
                'nombre' => $punto->municipio->nombre,
                'departamento' => $punto->municipio->relationLoaded('departamento') && $punto->municipio->departamento
                    ? [
                        'id' => $punto->municipio->departamento->id,
                        'slug' => $punto->municipio->departamento->slug,
                        'nombre' => $punto->municipio->departamento->nombre,
                    ]
                    : null,
            ] : null,
        ];
    }
}
