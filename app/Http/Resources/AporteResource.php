<?php

namespace App\Http\Resources;

use App\Models\Aporte;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Aporte
 */
class AporteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Aporte $aporte */
        $aporte = $this->resource;
        $viewer = $request->user();

        // Contacto (email/celular) solo para dueño, admin o moderador del municipio,
        // y nunca si el aporte es anónimo (salvo admin_reveal).
        $puedeVerContacto = $viewer && ! $aporte->anonimo && (
            $viewer->id === $aporte->user_id
            || ($aporte->relationLoaded('iniciativa') && $aporte->iniciativa
                && $viewer->id === $aporte->iniciativa->user_id)
            || $viewer->isPlatformAdmin()
            || ($aporte->relationLoaded('iniciativa') && $aporte->iniciativa
                ? $viewer->canModerateIniciativa($aporte->iniciativa)
                : false)
        );

        $mostrarNombre = ! $aporte->anonimo
            || ($viewer && (
                $viewer->id === $aporte->user_id
                || $viewer->isPlatformAdmin()
                || $request->attributes->get('admin_reveal_anonimo') === true
                || ($aporte->relationLoaded('iniciativa') && $aporte->iniciativa
                    ? $viewer->canModerateIniciativa($aporte->iniciativa)
                    : false)
            ));

        $evidenciaUrl = null;
        if ($aporte->evidencia_path && $aporte->evidencia_disk) {
            $evidenciaUrl = Storage::disk($aporte->evidencia_disk)->url($aporte->evidencia_path);
        }

        $evidenciaAportanteUrl = null;
        if ($aporte->evidencia_aportante_path && $aporte->evidencia_aportante_disk) {
            $evidenciaAportanteUrl = Storage::disk($aporte->evidencia_aportante_disk)->url($aporte->evidencia_aportante_path);
        }

        return [
            'id' => $aporte->id,
            'estado' => $aporte->estado?->value,
            'estado_label' => $aporte->estado?->label(),
            'asiste_al_convite' => $aporte->asiste_al_convite,
            'nota' => $aporte->nota,
            'anonimo' => (bool) $aporte->anonimo,
            'fecha_entrega' => $aporte->fecha_entrega?->toDateString(),
            'confirmado_at' => $aporte->confirmado_at?->toIso8601String(),
            'cancelado_at' => $aporte->cancelado_at?->toIso8601String(),
            'cumplido_at' => $aporte->cumplido_at?->toIso8601String(),
            'aportante' => $aporte->relationLoaded('user') ? (
                $mostrarNombre
                    ? [
                        'id' => $aporte->user?->id,
                        'name' => $aporte->user?->name,
                        'inicial' => $aporte->user?->inicial,
                        'email' => $puedeVerContacto ? $aporte->user?->email : null,
                        'celular' => $puedeVerContacto ? $aporte->user?->celular : null,
                    ]
                    : [
                        'id' => null,
                        'name' => 'Aporte anónimo',
                        'inicial' => 'A',
                        'email' => null,
                        'celular' => null,
                    ]
            ) : null,
            'punto_acopio' => $aporte->relationLoaded('puntoAcopio') && $aporte->puntoAcopio
                ? [
                    'id' => $aporte->puntoAcopio->id,
                    'nombre' => $aporte->puntoAcopio->nombre,
                    'direccion' => $aporte->puntoAcopio->direccion,
                    'municipio' => $aporte->puntoAcopio->relationLoaded('municipio') && $aporte->puntoAcopio->municipio
                        ? [
                            'id' => $aporte->puntoAcopio->municipio->id,
                            'slug' => $aporte->puntoAcopio->municipio->slug,
                            'nombre' => $aporte->puntoAcopio->municipio->nombre,
                        ]
                        : null,
                ]
                : null,
            'proveedor' => $aporte->relationLoaded('proveedor') && $aporte->proveedor
                ? [
                    'id' => $aporte->proveedor->id,
                    'nombre' => $aporte->proveedor->nombre,
                    'direccion' => $aporte->proveedor->direccion,
                    'ciudad' => $aporte->proveedor->ciudad,
                    'correo' => $aporte->proveedor->correo,
                    'celular' => $aporte->proveedor->celular,
                    'instrucciones_pago' => $aporte->proveedor->instrucciones_pago,
                ]
                : null,
            'evidencia' => $evidenciaUrl ? [
                'url' => $evidenciaUrl,
                'nombre' => $aporte->evidencia_nombre_original,
                'mime' => $aporte->evidencia_mime,
            ] : null,
            'evidencia_aportante_url' => $evidenciaAportanteUrl,
            'iniciativa' => $aporte->relationLoaded('iniciativa') ? [
                'id' => $aporte->iniciativa->id,
                'slug' => $aporte->iniciativa->slug,
                'titulo' => $aporte->iniciativa->titulo,
                'fecha_convite' => $aporte->iniciativa->fecha_convite?->toDateString(),
                'lugar_convite' => $aporte->iniciativa->lugar_convite,
                'lugar_exacto' => $aporte->iniciativa->lugar_exacto,
            ] : null,
            'items' => $aporte->relationLoaded('items')
                ? $aporte->items->map(fn ($item) => [
                    'id' => $item->id,
                    'iniciativa_item_id' => $item->iniciativa_item_id,
                    'nombre' => $item->relationLoaded('iniciativaItem')
                        ? $item->iniciativaItem?->nombre
                        : null,
                    'unidad' => $item->relationLoaded('iniciativaItem')
                        ? $item->iniciativaItem?->unidad
                        : null,
                    'cantidad' => $item->cantidad,
                ])->values()->all()
                : [],
            'created_at' => $aporte->created_at?->toIso8601String(),
        ];
    }
}
