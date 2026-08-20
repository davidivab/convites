<?php

namespace App\Http\Requests;

use App\Enums\Urgencia;
use Illuminate\Validation\Rule;

/**
 * Reglas compartidas create/update de iniciativas.
 */
final class IniciativaPayloadRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $updating, ?int $wizardPaso = null): array
    {
        $paso = $wizardPaso ?? 6;

        $zonaRules = ['nullable', 'integer', 'exists:zonas,id'];
        $municipioRules = ['nullable', 'integer', 'exists:municipios,id'];
        if ($paso >= 2) {
            $zonaRules[] = 'required_without:municipio_id';
            $municipioRules[] = 'required_without:zona_id';
        }

        $itemsRules = ['array'];
        $itemsRules[] = $paso >= 3 ? 'required' : 'sometimes';
        if ($paso >= 3) {
            $itemsRules[] = 'min:1';
        }

        return [
            'zona_id' => $zonaRules,
            'municipio_id' => $municipioRules,
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'titulo' => ['required', 'string', 'max:180'],
            'resumen' => ['required', 'string', 'max:500'],
            'historia' => ['required', 'array', 'min:1'],
            'historia.*' => ['required', 'string', 'max:2000'],
            'urgencia' => ['required', Rule::enum(Urgencia::class)],
            'fecha_convite' => ['nullable', 'date'],
            'fecha_limite_aportes' => ['nullable', 'date'],
            'fecha_convite_texto' => ['nullable', 'string', 'max:120'],
            // Propio del paso 2 (Ubicación y fechas): no exigir en autosaves de pasos previos.
            'lugar_convite' => [$paso >= 2 ? 'required' : 'nullable', 'string', 'max:255'],
            'lugar_exacto' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'geo_fuente' => ['nullable', 'string', Rule::in(['gps', 'busqueda', 'manual'])],
            'geo_precision' => ['nullable', 'string', Rule::in(['punto', 'aproximado'])],
            'mapa_visible' => ['sometimes', 'boolean'],
            'enlace_externo_plataforma' => ['nullable', 'string', 'max:80'],
            'enlace_externo_url' => ['nullable', 'url', 'max:500'],
            // Propios del paso 5 (Verificación): no exigir en autosaves de pasos previos.
            'persona_responsable' => [$paso >= 5 ? 'required' : 'nullable', 'string', 'max:120'],
            'quien_respalda' => [$paso >= 5 ? 'required' : 'nullable', 'string', 'max:180'],
            'telefono_contacto' => [$paso >= 5 ? 'required' : 'nullable', 'string', 'max:40'],
            'version' => [$updating ? 'required' : 'prohibited', 'integer', 'min:1'],
            // P53: paso actual del wizard de creación (borrador front).
            'wizard_paso' => ['nullable', 'integer', 'min:1', 'max:6'],
            // Propio del paso 3 (Qué se necesita): no exigir en autosaves de pasos previos.
            'items' => $itemsRules,
            'items.*.nombre' => ['required', 'string', 'max:120'],
            'items.*.unidad' => ['required', 'string', 'max:40'],
            'items.*.descripcion' => ['nullable', 'string', 'max:1000'],
            'items.*.valor_unitario_aprox' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'items.*.cantidad_meta' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.orden' => ['nullable', 'integer', 'min:0'],
            // P33: puntos de acopio remotos (opcional; municipio puede no estar "activo")
            'puntos_acopio' => [$updating ? 'sometimes' : 'nullable', 'array', 'max:20'],
            'puntos_acopio.*.municipio_id' => ['required', 'integer', 'exists:municipios,id'],
            'puntos_acopio.*.nombre' => ['required', 'string', 'max:160'],
            'puntos_acopio.*.direccion' => ['required', 'string', 'max:255'],
            'puntos_acopio.*.horario' => ['nullable', 'string', 'max:180'],
            'puntos_acopio.*.contacto' => ['nullable', 'string', 'max:120'],
            'puntos_acopio.*.notas' => ['nullable', 'string', 'max:500'],
            'puntos_acopio.*.centro_id' => ['nullable', 'integer', 'exists:centros,id'],
            'puntos_acopio.*.lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:puntos_acopio.*.lng'],
            'puntos_acopio.*.lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:puntos_acopio.*.lat'],
            'puntos_acopio.*.orden' => ['nullable', 'integer', 'min:0'],
            // Proveedores / contactos de pago-entrega asociados al convite (hasta 20).
            'proveedores' => [$updating ? 'sometimes' : 'nullable', 'array', 'max:20'],
            'proveedores.*.nombre' => ['required', 'string', 'max:160'],
            'proveedores.*.direccion' => ['nullable', 'string', 'max:255'],
            'proveedores.*.ciudad' => ['nullable', 'string', 'max:120'],
            'proveedores.*.correo' => ['nullable', 'email', 'max:180'],
            'proveedores.*.celular' => ['nullable', 'string', 'max:40'],
            'proveedores.*.instrucciones_pago' => ['required', 'string', 'max:1000'],
            'proveedores.*.orden' => ['nullable', 'integer', 'min:0'],
            // P53 (parte 3): enlaces adicionales del convite (hasta 20).
            'enlaces' => [$updating ? 'sometimes' : 'nullable', 'array', 'max:20'],
            'enlaces.*.titulo' => ['required', 'string', 'max:160'],
            'enlaces.*.url' => ['required', 'url', 'max:500'],
            'enlaces.*.orden' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
