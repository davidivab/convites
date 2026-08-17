<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreAdminUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'celular' => ['nullable', 'string', 'max:40'],
            'role' => ['required', 'string', Rule::in(['moderator', 'voluntario'])],
            'municipio_ids' => ['required', 'array', 'min:1'],
            'municipio_ids.*' => ['integer', 'exists:municipios,id'],
        ];
    }
}
