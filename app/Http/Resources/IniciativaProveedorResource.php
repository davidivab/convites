<?php

namespace App\Http\Resources;

use App\Models\IniciativaProveedor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IniciativaProveedor
 */
class IniciativaProveedorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var IniciativaProveedor $proveedor */
        $proveedor = $this->resource;

        return [
            'id' => $proveedor->id,
            'nombre' => $proveedor->nombre,
            'direccion' => $proveedor->direccion,
            'ciudad' => $proveedor->ciudad,
            'correo' => $proveedor->correo,
            'celular' => $proveedor->celular,
            'instrucciones_pago' => $proveedor->instrucciones_pago,
            'orden' => $proveedor->orden,
        ];
    }
}
