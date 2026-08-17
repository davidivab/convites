<?php

namespace App\Http\Requests;

use App\Enums\ModalidadProfesional;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * P29: campos que el propio profesional puede tocar de su perfil.
 * NUNCA incluye `estado`, `aprobado_at`, `revisado_por`, `nota_revision`
 * — esos son exclusivos del flujo de moderación (ProfesionalController::moderar).
 */
class UpdateMiPerfilProfesionalRequest extends FormRequest
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
            'titulo' => ['sometimes', 'string', 'max:180'],
            'celular' => ['sometimes', 'nullable', 'string', 'max:40'],
            'modalidad' => ['sometimes', Rule::enum(ModalidadProfesional::class)],
            'disponibilidad' => ['sometimes', 'string', 'max:180'],
            'descripcion' => ['sometimes', 'string', 'max:2000'],
        ];
    }
}
