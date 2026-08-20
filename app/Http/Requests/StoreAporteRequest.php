<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreAporteRequest extends FormRequest
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
            'asiste_al_convite' => ['sometimes', 'boolean'],
            'nota' => ['nullable', 'string', 'max:500'],
            'anonimo' => ['sometimes', 'boolean'],
            'client_request_id' => ['nullable', 'string', 'max:64'],
            'items' => ['nullable', 'array'],
            'items.*.iniciativa_item_id' => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            // P35: el punto debe pertenecer a ESTA iniciativa (route model binding).
            'punto_acopio_id' => [
                'nullable',
                'integer',
                Rule::exists('iniciativa_puntos_acopio', 'id')
                    ->where('iniciativa_id', $this->route('iniciativa')?->id),
            ],
            // El proveedor debe pertenecer a ESTA iniciativa (route model binding).
            'proveedor_id' => [
                'nullable',
                'integer',
                Rule::exists('iniciativa_proveedores', 'id')
                    ->where('iniciativa_id', $this->route('iniciativa')?->id),
            ],
            // Fecha en que el aportante se compromete a entregar/llevar su aporte.
            // Debe estar entre hoy y el límite de aportes (o la fecha del convite
            // si no hay límite de aportes definido) de ESTA iniciativa.
            'fecha_entrega' => [
                'nullable',
                'date',
                function (string $attribute, mixed $value, Closure $fail) {
                    $iniciativa = $this->route('iniciativa');
                    $hoy = Carbon::today();
                    $fechaEntrega = Carbon::parse($value)->startOfDay();

                    if ($fechaEntrega->lt($hoy)) {
                        $fail('La fecha de entrega no puede ser antes de hoy.');

                        return;
                    }

                    $fechaLimiteAportes = $iniciativa?->fecha_limite_aportes;

                    if ($fechaLimiteAportes) {
                        if ($fechaEntrega->gt($fechaLimiteAportes->copy()->startOfDay())) {
                            $fail('La fecha de entrega no puede ser después de la fecha límite de aportes de esta iniciativa.');
                        }

                        return;
                    }

                    $fechaConvite = $iniciativa?->fecha_convite;

                    if ($fechaConvite && $fechaEntrega->gt($fechaConvite->copy()->startOfDay())) {
                        $fail('La fecha de entrega no puede ser después de la fecha del convite.');
                    }
                },
            ],
        ];
    }
}
