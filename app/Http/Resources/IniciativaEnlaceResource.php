<?php

namespace App\Http\Resources;

use App\Models\IniciativaEnlace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IniciativaEnlace
 */
class IniciativaEnlaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var IniciativaEnlace $enlace */
        $enlace = $this->resource;

        return [
            'id' => $enlace->id,
            'titulo' => $enlace->titulo,
            'url' => $enlace->url,
            'orden' => $enlace->orden,
        ];
    }
}
