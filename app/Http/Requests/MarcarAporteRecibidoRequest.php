<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarcarAporteRecibidoRequest extends FormRequest
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
            'recibido' => ['required', 'boolean'],
            'evidencia' => ['nullable', 'image', 'max:5120'], // 5 MB
        ];
    }
}
