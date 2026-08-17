<?php

namespace App\Http\Resources;

use App\Models\Profesional;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Perfil público de profesional (sin celular/email salvo contacto propio o moderación).
 *
 * @mixin Profesional
 */
class ProfesionalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Profesional $pro */
        $pro = $this->resource;
        $viewer = $request->user();
        $puedeVerContacto = $viewer
            && (
                $viewer->id === $pro->user_id
                || $viewer->can('profesionales.moderate')
            );

        return [
            'id' => $pro->id,
            'area' => $pro->area?->value,
            'area_label' => $pro->area?->label(),
            'nombre' => $pro->nombre,
            'titulo' => $pro->titulo,
            'inicial' => $pro->inicial,
            'modalidad' => $pro->modalidad?->value,
            'modalidad_label' => $pro->modalidad?->label(),
            'disponibilidad' => $pro->disponibilidad,
            'descripcion' => $pro->descripcion,
            'estado' => $pro->estado?->value,
            'estado_label' => $pro->estado?->label(),
            'email' => $puedeVerContacto ? $pro->email : null,
            'celular' => $puedeVerContacto ? $pro->celular : null,
            'zona' => $pro->relationLoaded('zona') ? [
                'id' => $pro->zona->id,
                'slug' => $pro->zona->slug,
                'nombre' => $pro->zona->nombre,
            ] : null,
            // P31: certificados — mismo gate que el contacto (dueño o moderador),
            // pueden contener datos personales (cédula, tarjeta profesional, etc.).
            'documentos' => $puedeVerContacto && $pro->relationLoaded('documentos')
                ? $pro->documentos->map(fn ($doc) => [
                    'id' => $doc->id,
                    'nombre_original' => $doc->nombre_original,
                    'mime' => $doc->mime,
                    'url' => \Illuminate\Support\Facades\Storage::disk($doc->disk)->url($doc->path),
                ])->values()->all()
                : null,
        ];
    }
}
