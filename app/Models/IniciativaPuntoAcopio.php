<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Punto de recolección asociado a una iniciativa (puede ser otra ciudad).
 *
 * @property int $id
 * @property int $iniciativa_id
 * @property int $municipio_id
 * @property int|null $centro_id
 * @property string $nombre
 * @property string $direccion
 * @property string|null $horario
 * @property string|null $contacto
 * @property string|null $notas
 * @property float|null $lat
 * @property float|null $lng
 * @property int $orden
 */
class IniciativaPuntoAcopio extends Model
{
    protected $table = 'iniciativa_puntos_acopio';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'iniciativa_id',
        'municipio_id',
        'centro_id',
        'nombre',
        'direccion',
        'horario',
        'contacto',
        'notas',
        'lat',
        'lng',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'iniciativa_id' => 'integer',
            'municipio_id' => 'integer',
            'centro_id' => 'integer',
            'lat' => 'float',
            'lng' => 'float',
            'orden' => 'integer',
        ];
    }

    public function iniciativa(): BelongsTo
    {
        return $this->belongsTo(Iniciativa::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }
}
