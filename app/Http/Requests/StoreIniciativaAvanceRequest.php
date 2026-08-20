<?php

namespace App\Http\Requests;

use App\Models\Iniciativa;
use App\Models\IniciativaAvance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreIniciativaAvanceRequest extends FormRequest
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
        return IniciativaAvancePayloadRules::rules($this->iniciativa());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();

            if (($data['tipo'] ?? null) !== 'item' || ! (bool) ($data['publicado'] ?? false)) {
                return;
            }

            $itemId = (int) ($data['iniciativa_item_id'] ?? 0);
            $porcentaje = (int) ($data['porcentaje'] ?? 0);

            $floor = IniciativaAvance::floorPublicado($this->iniciativa()->id, $itemId);

            if ($porcentaje < $floor) {
                $validator->errors()->add(
                    'porcentaje',
                    "El avance no puede reportar menos del {$floor}% ya publicado para este ítem."
                );
            }
        });
    }

    private function iniciativa(): Iniciativa
    {
        /** @var Iniciativa $iniciativa */
        $iniciativa = $this->route('iniciativa_uuid');

        return $iniciativa;
    }
}
