<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

/**
 * P51: rango de fechas opcional para GET /api/admin/estadisticas.
 *
 * Autorización: cubierta por el middleware de ruta (`permission:users.manage`)
 * del grupo admin — no hay lógica extra acá.
 */
class AdminEstadisticasRequest extends FormRequest
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
            'start_date' => ['sometimes', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $start = $this->input('start_date');
            $end = $this->input('end_date');

            if ($start && $end && $start > $end) {
                $validator->errors()->add('start_date', 'start_date no puede ser posterior a end_date.');
            }
        });
    }
}
