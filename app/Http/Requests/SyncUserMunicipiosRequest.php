<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncUserMunicipiosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'municipio_ids' => ['required', 'array', 'min:1'],
            'municipio_ids.*' => ['integer', 'exists:municipios,id'],
        ];
    }
}
