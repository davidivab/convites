<?php

namespace App\Models;

use App\Enums\EstadoCentro;
use App\Enums\TipoCentro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Centro de interés (acopio, albergue, emergencia, ...).
 *
 * @property int $id
 * @property TipoCentro $tipo
 * @property string $nombre
 * @property int $zona_id
 * @property string $direccion
 * @property string|null $telefono
 * @property string|null $horario
 * @property EstadoCentro $estado
 * @property string $descripcion
 * @property list<string>|null $necesita
 * @property list<string>|null $no_recibe
 * @property int|null $capacidad_total
 * @property int|null $capacidad_ocupada
 * @property bool $emergencia
 * @property bool $activo
 * @property int $orden
 */
class Centro extends Model
{
    use SoftDeletes;

    protected $table = 'centros';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tipo',
        'nombre',
        'zona_id',
        'direccion',
        'telefono',
        'horario',
        'estado',
        'descripcion',
        'necesita',
        'no_recibe',
        'capacidad_total',
        'capacidad_ocupada',
        'emergencia',
        'activo',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoCentro::class,
            'zona_id' => 'integer',
            'estado' => EstadoCentro::class,
            'necesita' => 'array',
            'no_recibe' => 'array',
            'capacidad_total' => 'integer',
            'capacidad_ocupada' => 'integer',
            'emergencia' => 'boolean',
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }
}
