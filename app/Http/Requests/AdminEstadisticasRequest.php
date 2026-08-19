<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * P51: rango de fechas opcional para GET /api/admin/estadisticas.
 *
 * Autorización: cubierta por el middleware de ruta (`permission:users.manage`)
 * del grupo admin — no hay lógica extra acá.
 */
class AdminEstadisticasRequest extends FormRequest
{
    /**
     * Rango máximo permitido entre start_date y end_date (inclusive).
     *
     * Evita que `AdminEstadisticasController::zeroFilledCountByDay()` itere
     * un `CarbonPeriod` día a día sin límite y agote la memoria en rangos
     * gigantes (ej. `1900-01-01` a hoy, ~46k días).
     */
    private const MAX_RANGE_DAYS = 366;

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

                return;
            }

            // Se aplican los mismos defaults que usa el controller
            // (AdminEstadisticasController::index) para que un solo extremo
            // explícito con el otro implícito no evada el límite de rango
            // (ej. start_date=1900-01-01 sin end_date, que por default cae en
            // "hoy").
            $effectiveEnd = $end ? Carbon::createFromFormat('Y-m-d', $end) : Carbon::now();
            $effectiveStart = $start ? Carbon::createFromFormat('Y-m-d', $start) : Carbon::now()->subWeeks(2);

            if ($effectiveStart->diffInDays($effectiveEnd) > self::MAX_RANGE_DAYS) {
                $validator->errors()->add(
                    'start_date',
                    'El rango no puede superar '.self::MAX_RANGE_DAYS.' días.'
                );
            }
        });
    }
}
