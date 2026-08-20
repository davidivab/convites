<?php

namespace App\Http\Resources;

use App\Models\IniciativaItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IniciativaItem
 */
class IniciativaItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var IniciativaItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'nombre' => $item->nombre,
            'unidad' => $item->unidad,
            'descripcion' => $item->descripcion,
            // El cast `decimal:2` del modelo serializa este atributo como
            // string (p. ej. "25000.00"); forzamos float para que el JSON
            // coincida con el contrato del frontend (number|null).
            'valor_unitario_aprox' => $item->valor_unitario_aprox !== null
                ? (float) $item->valor_unitario_aprox
                : null,
            'valor_meta_aprox' => $item->valorMetaAprox(),
            'valor_aportado_aprox' => $item->valorAportadoAprox(),
            'cantidad_meta' => $item->cantidad_meta,
            'cantidad_aportada' => $item->cantidad_aportada,
            'faltante' => max(0, $item->cantidad_meta - $item->cantidad_aportada),
            'progreso' => $item->progresoPorcentaje(),
            'orden' => $item->orden,
        ];
    }
}
