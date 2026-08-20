<?php

namespace App\Http\Requests;

use App\Models\Iniciativa;
use App\Models\IniciativaAvance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateIniciativaAvanceRequest extends FormRequest
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
            $avanceId = (int) $this->route('avance');

            // D-C: absence of `porcentaje` in the payload is NOT the same as
            // null — resolve the persisted value so a publish-only PATCH
            // doesn't get compared against 0.
            $porcentaje = array_key_exists('porcentaje', $data)
                ? (int) $data['porcentaje']
                : (int) (IniciativaAvance::query()->whereKey($avanceId)->value('porcentaje') ?? 0);

            $floor = IniciativaAvance::floorPublicado($this->iniciativa()->id, $itemId, $avanceId);

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
