<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'asiste_al_convite' => ['sometimes', 'boolean'],
            'nota' => ['nullable', 'string', 'max:500'],
            'anonimo' => ['sometimes', 'boolean'],
            'client_request_id' => ['nullable', 'string', 'max:64'],
            'items' => ['nullable', 'array'],
            'items.*.iniciativa_item_id' => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            // P35: el punto debe pertenecer a ESTA iniciativa (route model binding).
            'punto_acopio_id' => [
                'nullable',
                'integer',
                Rule::exists('iniciativa_puntos_acopio', 'id')
                    ->where('iniciativa_id', $this->route('iniciativa')?->id),
            ],
        ];
    }
}
