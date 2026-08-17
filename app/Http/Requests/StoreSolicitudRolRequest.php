<?php

namespace App\Http\Requests;

use App\Enums\EstadoSolicitudRol;
use App\Enums\TipoSolicitudRol;
use App\Models\SolicitudRol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSolicitudRolRequest extends FormRequest
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
            'rol' => ['required', Rule::enum(TipoSolicitudRol::class)],
            'municipio_ids' => ['required', 'array', 'min:1'],
            'municipio_ids.*' => ['integer', 'exists:municipios,id'],
            'mensaje' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rol = $this->input('rol');
            if (! $rol) {
                return;
            }

            $user = $this->user();
            $tipo = TipoSolicitudRol::tryFrom($rol);
            if (! $tipo) {
                return;
            }

            if ($user->hasRole($tipo->rolSpatie())) {
                $validator->errors()->add('rol', 'Ya tienes ese rol asignado.');

                return;
            }

            $yaPendiente = SolicitudRol::query()
                ->where('user_id', $user->id)
                ->where('rol', $rol)
                ->where('estado', EstadoSolicitudRol::Pendiente)
                ->exists();

            if ($yaPendiente) {
                $validator->errors()->add('rol', 'Ya tienes una solicitud pendiente de este rol.');
            }
        });
    }
}
