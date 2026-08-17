<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Departamento de Colombia (catálogo CSC).
 *
 * @property int $id
 * @property int $external_id
 * @property string $nombre
 * @property string $slug
 * @property string|null $codigo
 * @property bool $activo
 * @property int $orden
 */
class Departamento extends Model
{
    protected $fillable = [
        'external_id',
        'nombre',
        'slug',
        'codigo',
        'activo',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'external_id' => 'integer',
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function municipios(): HasMany
    {
        return $this->hasMany(Municipio::class);
    }
}
