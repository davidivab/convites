<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIniciativaRequest extends FormRequest
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
        return IniciativaPayloadRules::rules(
            updating: true,
            wizardPaso: $this->filled('wizard_paso') ? (int) $this->input('wizard_paso') : null,
        );
    }
}
