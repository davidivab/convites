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
            'cantidad_meta' => $item->cantidad_meta,
            'cantidad_aportada' => $item->cantidad_aportada,
            'faltante' => max(0, $item->cantidad_meta - $item->cantidad_aportada),
            'progreso' => $item->progresoPorcentaje(),
            'orden' => $item->orden,
        ];
    }
}
