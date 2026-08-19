<?php

namespace App\Http\Requests;

use App\Enums\AptitudFisica;
use App\Enums\Genero;
use Illuminate\Validation\Rule;

/**
 * [P53] Reglas del perfil comunitario opcional, compartidas entre registro
 * local (`AuthController::register`), registro con Google
 * (`GoogleAuthController::completarRegistro`) y edición de perfil
 * (`UpdateProfileRequest`).
 *
 * Todos estos campos son opcionales al registrarse: el usuario puede
 * completarlos después desde el editor de perfil.
 */
final class ProfileFieldRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'celular' => ['nullable', 'string', 'max:40'],
            'municipio_id' => ['nullable', 'integer', 'exists:municipios,id'],
            'barrio' => ['nullable', 'string', 'max:255'],
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
