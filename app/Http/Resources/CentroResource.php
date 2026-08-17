<?php

namespace App\Http\Resources;

use App\Models\Centro;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Centro
 */
class CentroResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Centro $centro */
        $centro = $this->resource;

        return [
            'id' => $centro->id,
            'tipo' => $centro->tipo?->value,
            'tipo_label' => $centro->tipo?->label(),
            'nombre' => $centro->nombre,
            'direccion' => $centro->direccion,
            'telefono' => $centro->telefono,
            'horario' => $centro->horario,
            'estado' => $centro->estado?->value,
            'estado_label' => $centro->estado?->label(),
            'descripcion' => $centro->descripcion,
            'necesita' => $centro->necesita,
            'no_recibe' => $centro->no_recibe,
            'capacidad_total' => $centro->capacidad_total,
            'capacidad_ocupada' => $centro->capacidad_ocupada,
            'emergencia' => $centro->emergencia,
            'zona' => $centro->relationLoaded('zona') && $centro->zona ? [
                'id' => $centro->zona->id,
                'slug' => $centro->zona->slug,
                'nombre' => $centro->zona->nombre,
            ] : null,
            'municipio' => $centro->relationLoaded('municipio') && $centro->municipio ? [
                'id' => $centro->municipio->id,
                'slug' => $centro->municipio->slug,
                'nombre' => $centro->municipio->nombre,
                'departamento' => $centro->municipio->relationLoaded('departamento') && $centro->municipio->departamento
                    ? [
                        'id' => $centro->municipio->departamento->id,
                        'slug' => $centro->municipio->departamento->slug,
                        'nombre' => $centro->municipio->departamento->nombre,
                    ]
                    : null,
            ] : null,
        ];
    }
}
