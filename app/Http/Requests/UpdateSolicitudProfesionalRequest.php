<?php

namespace App\Http\Requests;

use App\Enums\EstadoSolicitudProfesional;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSolicitudProfesionalRequest extends FormRequest
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
            'estado' => ['sometimes', Rule::enum(EstadoSolicitudProfesional::class)],
            'nota' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
