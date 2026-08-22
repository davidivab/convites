<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Proveedor / contacto de pago-entrega asociado a una iniciativa.
 *
 * @property int $id
 * @property int $iniciativa_id
 * @property string $nombre
 * @property string|null $direccion
 * @property string|null $ciudad
 * @property string|null $correo
 * @property string|null $celular
 * @property string $instrucciones_pago
 * @property int $orden
 */
class IniciativaProveedor extends Model
{
    use SoftDeletes;

    protected $table = 'iniciativa_proveedores';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'iniciativa_id',
        'nombre',
        'direccion',
        'ciudad',
        'correo',
        'celular',
        'instrucciones_pago',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'iniciativa_id' => 'integer',
            'orden' => 'integer',
        ];
    }

    public function iniciativa(): BelongsTo
    {
        return $this->belongsTo(Iniciativa::class);
    }
}
