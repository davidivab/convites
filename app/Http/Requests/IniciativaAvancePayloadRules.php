<?php

namespace App\Http\Requests;

use App\Models\Iniciativa;
use Illuminate\Validation\Rule;

/**
 * Reglas compartidas create/update de avances de convite (P54).
 * Mirror de `IniciativaPayloadRules`.
 */
final class IniciativaAvancePayloadRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(Iniciativa $iniciativa): array
    {
        return [
            'titulo' => ['required', 'string', 'max:200'],
            'cuerpo' => ['nullable', 'string', 'max:5000'],
            'tipo' => ['required', Rule::in(['general', 'item'])],
            'iniciativa_item_id' => [
                'nullable',
                'integer',
                'required_if:tipo,item',
                'prohibited_if:tipo,general',
                Rule::exists('iniciativa_items', 'id')->where('iniciativa_id', $iniciativa->id),
            ],
            'porcentaje' => [
                'nullable',
                'integer',
                'between:0,100',
                'required_if:tipo,item',
                'prohibited_if:tipo,general',
            ],
            'enlace_externo' => ['nullable', 'url', 'max:500'],
            'notificar_aportantes' => ['sometimes', 'boolean'],
            'publicado' => ['sometimes', 'boolean'],
        ];
    }
}
