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
    public static function rules(bool $updating): array
    {
        return [
            'zona_id' => ['nullable', 'integer', 'exists:zonas,id', 'required_without:municipio_id'],
            'municipio_id' => ['nullable', 'integer', 'exists:municipios,id', 'required_without:zona_id'],
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'titulo' => ['required', 'string', 'max:180'],
            'resumen' => ['required', 'string', 'max:500'],
            'historia' => ['required', 'array', 'min:1'],
            'historia.*' => ['required', 'string', 'max:2000'],
            'urgencia' => ['required', Rule::enum(Urgencia::class)],
            'fecha_convite' => ['nullable', 'date'],
            'fecha_limite_aportes' => ['nullable', 'date'],
            'fecha_convite_texto' => ['nullable', 'string', 'max:120'],
            'lugar_convite' => ['required', 'string', 'max:255'],
            'lugar_exacto' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'geo_fuente' => ['nullable', 'string', Rule::in(['gps', 'busqueda', 'manual'])],
            'geo_precision' => ['nullable', 'string', Rule::in(['punto', 'aproximado'])],
            'mapa_visible' => ['sometimes', 'boolean'],
            'enlace_externo_plataforma' => ['nullable', 'string', 'max:80'],
            'enlace_externo_url' => ['nullable', 'url', 'max:500'],
            'persona_responsable' => ['required', 'string', 'max:120'],
            'quien_respalda' => ['required', 'string', 'max:180'],
            'telefono_contacto' => ['required', 'string', 'max:40'],
            'version' => [$updating ? 'required' : 'prohibited', 'integer', 'min:1'],
            'items' => [$updating ? 'sometimes' : 'required', 'array', 'min:1'],
            'items.*.nombre' => ['required', 'string', 'max:120'],
            'items.*.unidad' => ['required', 'string', 'max:40'],
            'items.*.cantidad_meta' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.orden' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
