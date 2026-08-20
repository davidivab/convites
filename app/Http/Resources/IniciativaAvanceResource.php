<?php

namespace App\Http\Resources;

use App\Models\IniciativaAvance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialización pública / autenticada de un avance de convite (P54).
 *
 * `notificar_aportantes` / `notificado_at` se omiten del shape público —
 * solo el dueño/moderador tiene por qué saber a quién se notificó.
 *
 * @mixin IniciativaAvance
 */
class IniciativaAvanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var IniciativaAvance $avance */
        $avance = $this->resource;
        $viewer = $request->user();

        return [
            'id' => $avance->id,
            'slug' => $avance->slug,
            'titulo' => $avance->titulo,
            'cuerpo' => $avance->cuerpo,
            'tipo' => $avance->esGeneral() ? 'general' : 'item',
            'item' => $avance->relationLoaded('item') && $avance->item ? [
                'id' => $avance->item->id,
                'nombre' => $avance->item->nombre,
                'unidad' => $avance->item->unidad,
            ] : null,
            'porcentaje' => $avance->porcentaje,
            'enlace_externo' => $avance->enlace_externo,
            'media' => IniciativaAvanceMediaResource::collection($this->whenLoaded('media')),
            'autor' => $avance->relationLoaded('autor') && $avance->autor ? [
                'id' => $avance->autor->id,
                'name' => $avance->autor->name,
                'inicial' => $avance->autor->inicial,
            ] : null,
            'publicado_at' => $avance->publicado_at?->toIso8601String(),
            'created_at' => $avance->created_at?->toIso8601String(),
            'notificar_aportantes' => $this->viewerPuedeVerNotificacion($viewer, $avance) ? $avance->notificar_aportantes : null,
            'notificado_at' => $this->viewerPuedeVerNotificacion($viewer, $avance) ? $avance->notificado_at?->toIso8601String() : null,
        ];
    }

    private function viewerPuedeVerNotificacion(?User $viewer, IniciativaAvance $avance): bool
    {
        if (! $viewer) {
            return false;
        }

        return $viewer->id === $avance->user_id || $viewer->can('iniciativas.moderate');
    }
}
