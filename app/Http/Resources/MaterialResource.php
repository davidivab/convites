<?php

namespace App\Http\Resources;

use App\Models\IniciativaItem;
use App\Support\UploadDisk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Búsqueda inversa "tengo este material, ¿quién lo necesita?": ítem
 * pendiente de una iniciativa publicada, con un resumen de la iniciativa
 * para que el donante pueda volver a ese convite.
 *
 * @mixin IniciativaItem
 */
class MaterialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var IniciativaItem $item */
        $item = $this->resource;
        $iniciativa = $item->iniciativa;

        return [
            'id' => $item->id,
            'nombre' => $item->nombre,
            'unidad' => $item->unidad,
            'descripcion' => $item->descripcion,
            // Mismo motivo que en IniciativaItemResource: el cast decimal:2
            // serializa este atributo como string, forzamos float.
            'valor_unitario_aprox' => $item->valor_unitario_aprox !== null
                ? (float) $item->valor_unitario_aprox
                : null,
            'valor_meta_aprox' => $item->valorMetaAprox(),
            'valor_aportado_aprox' => $item->valorAportadoAprox(),
            'cantidad_meta' => $item->cantidad_meta,
            'cantidad_aportada' => $item->cantidad_aportada,
            'faltante' => max(0, $item->cantidad_meta - $item->cantidad_aportada),
            'progreso' => $item->progresoPorcentaje(),
            'iniciativa' => [
                'id' => $iniciativa->id,
                'slug' => $iniciativa->slug,
                'titulo' => $iniciativa->titulo,
                'urgencia' => $iniciativa->urgencia?->value,
                'urgencia_label' => $iniciativa->urgencia?->label(),
                'imagen_path' => $this->imagenUrl($iniciativa),
                'municipio' => $iniciativa->relationLoaded('municipio') && $iniciativa->municipio ? [
                    'id' => $iniciativa->municipio->id,
                    'slug' => $iniciativa->municipio->slug,
                    'nombre' => $iniciativa->municipio->nombre,
                    'departamento' => $iniciativa->municipio->relationLoaded('departamento') && $iniciativa->municipio->departamento
                        ? [
                            'id' => $iniciativa->municipio->departamento->id,
                            'slug' => $iniciativa->municipio->departamento->slug,
                            'nombre' => $iniciativa->municipio->departamento->nombre,
                        ]
                        : null,
                ] : null,
                'categoria' => $iniciativa->relationLoaded('categoria') && $iniciativa->categoria ? [
                    'id' => $iniciativa->categoria->id,
                    'slug' => $iniciativa->categoria->slug,
                    'nombre' => $iniciativa->categoria->nombre,
                ] : null,
            ],
        ];
    }

    private function imagenUrl(\App\Models\Iniciativa $iniciativa): ?string
    {
        if (! $iniciativa->imagen_path) {
            return null;
        }

        if (str_starts_with($iniciativa->imagen_path, 'http://') || str_starts_with($iniciativa->imagen_path, 'https://')) {
            return $iniciativa->imagen_path;
        }

        return Storage::disk(UploadDisk::name())->url($iniciativa->imagen_path);
    }
}
