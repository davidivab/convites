<?php

namespace App\Http\Resources;

use App\Models\IniciativaAvanceMedia;
use App\Support\UploadDisk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Mirror de `IniciativaGaleriaResource` (resolución de URL) + `tipo`/`duracion_segundos`.
 *
 * @mixin IniciativaAvanceMedia
 */
class IniciativaAvanceMediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var IniciativaAvanceMedia $item */
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
