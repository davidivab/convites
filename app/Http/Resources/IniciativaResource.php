<?php

namespace App\Http\Resources;

use App\Models\Iniciativa;
use App\Models\User;
use App\Support\UploadDisk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Serialización pública / autenticada de una iniciativa.
 *
 * - Campos de verificación privada NUNCA salen aquí.
 * - lugar_exacto solo si el viewer tiene aporte activo.
 *
 * @mixin Iniciativa
 */
class IniciativaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Iniciativa $iniciativa */
        $iniciativa = $this->resource;
        $viewer = $request->user();

        $puedeVerLugarExacto = $this->viewerPuedeVerLugarExacto($viewer);

        return [
            'id' => $iniciativa->id,
            'slug' => $iniciativa->slug,
            'titulo' => $iniciativa->titulo,
            'resumen' => $iniciativa->resumen,
            'historia' => $iniciativa->historia,
            'urgencia' => $iniciativa->urgencia?->value,
            'urgencia_label' => $iniciativa->urgencia?->label(),
            'estado' => $iniciativa->estado?->value,
            'estado_label' => $iniciativa->estado?->label(),
            'nota_moderacion' => $this->viewerPuedeVerNotaModeracion($viewer)
                ? $iniciativa->nota_moderacion
                : null,
            'imagen_path' => $this->imagenUrl($iniciativa),
            'fecha_convite' => $iniciativa->fecha_convite?->toDateString(),
            'fecha_limite_aportes' => $iniciativa->fecha_limite_aportes?->toDateString(),
            'fecha_convite_texto' => $iniciativa->fecha_convite_texto,
            'lugar_convite' => $iniciativa->lugar_convite,
            'lugar_exacto' => $puedeVerLugarExacto ? $iniciativa->lugar_exacto : null,
            'ubicacion' => $this->ubicacionPublica($iniciativa),
            'zona' => $iniciativa->relationLoaded('zona') && $iniciativa->zona ? [
                'id' => $iniciativa->zona->id,
                'slug' => $iniciativa->zona->slug,
                'nombre' => $iniciativa->zona->nombre,
            ] : null,
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
            'categoria' => $iniciativa->relationLoaded('categoria') ? [
                'id' => $iniciativa->categoria->id,
                'slug' => $iniciativa->categoria->slug,
                'nombre' => $iniciativa->categoria->nombre,
            ] : null,
            'creador' => $iniciativa->relationLoaded('creador') ? [
                'id' => $iniciativa->creador->id,
                'name' => $iniciativa->creador->name,
                'inicial' => $iniciativa->creador->inicial,
            ] : null,
            'enlace_externo' => $iniciativa->enlace_externo_url ? [
                'plataforma' => $iniciativa->enlace_externo_plataforma,
                'url' => $iniciativa->enlace_externo_url,
            ] : null,
            'items' => IniciativaItemResource::collection($this->whenLoaded('items')),
            'puntos_acopio' => IniciativaPuntoAcopioResource::collection(
                $this->whenLoaded('puntosAcopio'),
            ),
            'asistentes_count' => $iniciativa->asistentes_count,
            'progreso' => $iniciativa->progreso_cache,
            'version' => $iniciativa->version,
            'destacada' => $iniciativa->destacada,
            'publicada_at' => $iniciativa->publicada_at?->toIso8601String(),
            'created_at' => $iniciativa->created_at?->toIso8601String(),
        ];
    }

    /**
     * `imagen_path` se guarda como path relativo al disco de uploads (P23: S3 en
     * prod, `public` en local) — resolvemos acá para que el front nunca tenga
     * que adivinar el host (P27).
     */
    private function imagenUrl(Iniciativa $iniciativa): ?string
    {
        if (! $iniciativa->imagen_path) {
            return null;
        }

        // Ya es una URL absoluta (ej. seed/demo con link externo) — no reprocesar.
        if (str_starts_with($iniciativa->imagen_path, 'http://') || str_starts_with($iniciativa->imagen_path, 'https://')) {
            return $iniciativa->imagen_path;
        }

        return Storage::disk(UploadDisk::name())->url($iniciativa->imagen_path);
    }

    private function ubicacionPublica(Iniciativa $iniciativa): ?array
    {
        if (! $iniciativa->mapa_visible || $iniciativa->lat === null || $iniciativa->lng === null) {
            return null;
        }

        return [
            'lat' => (float) $iniciativa->lat,
            'lng' => (float) $iniciativa->lng,
            'precision' => $iniciativa->geo_precision ?: 'punto',
            'mapa_visible' => true,
        ];
    }

    private function viewerPuedeVerNotaModeracion(?User $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        /** @var Iniciativa $iniciativa */
        $iniciativa = $this->resource;

        return $viewer->id === $iniciativa->user_id || $viewer->can('iniciativas.moderate');
    }

    private function viewerPuedeVerLugarExacto(?User $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        /** @var Iniciativa $iniciativa */
        $iniciativa = $this->resource;

        if ($viewer->id === $iniciativa->user_id) {
            return true;
        }

        if ($viewer->can('iniciativas.moderate')) {
            return true;
        }

        return $iniciativa->aportes()
            ->where('user_id', $viewer->id)
            ->whereIn('estado', ['confirmado', 'cumplido'])
            ->exists();
    }
}
