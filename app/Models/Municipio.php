<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Municipio de Colombia (catálogo CSC).
 *
 * @property int $id
 * @property int $departamento_id
 * @property int $external_id
 * @property string $nombre
 * @property string $slug
 * @property bool $activo
 * @property bool $emergencia
 * @property int $orden
 */
class Municipio extends Model
{
    protected $fillable = [
        'departamento_id',
        'external_id',
        'nombre',
        'slug',
        'activo',
        'emergencia',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'departamento_id' => 'integer',
            'external_id' => 'integer',
            'activo' => 'boolean',
            'emergencia' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function iniciativas(): HasMany
    {
        return $this->hasMany(Iniciativa::class);
    }

    public function usuariosAsignados(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'usuario_municipio')
            ->withTimestamps();
    }
}
