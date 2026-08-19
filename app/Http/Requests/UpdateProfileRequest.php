<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        return array_merge(
            [
                'name' => ['sometimes', 'string', 'max:255'],
                'zona_id' => ['nullable', 'integer', 'exists:zonas,id'],
            ],
            ProfileFieldRules::rules(),
        );
    }
}
