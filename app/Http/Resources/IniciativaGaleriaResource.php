<?php

namespace App\Http\Resources;

use App\Models\IniciativaGaleria;
use App\Support\UploadDisk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Item de galería de una iniciativa (imagen o video, P54).
 *
 * NOTA: no incluye `version` — la respuesta del endpoint de upload de
 * galería agrega ese campo aparte (es la versión de la Iniciativa, no del
 * item de galería).
 *
 * @mixin IniciativaGaleria
 */
class IniciativaGaleriaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var IniciativaGaleria $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'tipo' => $item->tipo,
            'url' => $this->urlFromPath($item->path),
            'orden' => $item->orden,
            'ancho' => $item->ancho,
            'alto' => $item->alto,
            'duracion_segundos' => $item->duracion_segundos,
        ];
    }

    private function urlFromPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk(UploadDisk::name())->url($path);
    }
}
