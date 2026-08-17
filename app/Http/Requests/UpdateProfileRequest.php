<?php

namespace App\Http\Requests;

use App\Enums\AptitudFisica;
use App\Enums\Genero;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'celular' => ['nullable', 'string', 'max:40'],
            'zona_id' => ['nullable', 'integer', 'exists:zonas,id'],
            'municipio_id' => ['nullable', 'integer', 'exists:municipios,id'],
            'genero' => ['nullable', Rule::enum(Genero::class)],
            'edad' => ['nullable', 'integer', 'min:14', 'max:110'],
            'aptitud_fisica' => ['nullable', Rule::enum(AptitudFisica::class)],
            'notas_salud' => ['nullable', 'string', 'max:1000'],
            'habilidad_ids' => ['sometimes', 'array'],
            'habilidad_ids.*' => ['integer', 'exists:habilidades,id'],
            'disponibilidad_ids' => ['sometimes', 'array'],
            'disponibilidad_ids.*' => ['integer', 'exists:disponibilidades,id'],
        ];
    }
}
