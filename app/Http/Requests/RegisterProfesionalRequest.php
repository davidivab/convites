<?php

namespace App\Http\Requests;

use App\Enums\AreaProfesional;
use App\Enums\ModalidadProfesional;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterProfesionalRequest extends FormRequest
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
            'zona_id' => ['nullable', 'integer', 'exists:zonas,id', 'required_without:municipio_id'],
            'municipio_id' => ['nullable', 'integer', 'exists:municipios,id', 'required_without:zona_id'],
            'area' => ['required', Rule::enum(AreaProfesional::class)],
            'nombre' => ['required', 'string', 'max:120'],
            'titulo' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:255', 'unique:profesionales,email'],
            'celular' => ['nullable', 'string', 'max:40'],
            'tarjeta_profesional' => ['nullable', 'string', 'max:80'],
            'modalidad' => ['required', Rule::enum(ModalidadProfesional::class)],
            'disponibilidad' => ['required', 'string', 'max:180'],
            'descripcion' => ['required', 'string', 'max:2000'],
            'documentos' => ['sometimes', 'array', 'max:5'],
            'documentos.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
