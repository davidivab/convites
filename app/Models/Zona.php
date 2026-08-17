<?php

namespace App\Models;

use Database\Factories\ZonaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Zona geográfica (catálogo).
 *
 * @property int $id
 * @property string $slug
 * @property string $nombre
 * @property string|null $municipio
 * @property int $orden
 * @property bool $activo
 */
class Zona extends Model
{
    /** @use HasFactory<ZonaFactory> */
    use HasFactory;

    /**
     * Campos asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'nombre',
        'municipio',
        'orden',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function iniciativas(): HasMany
    {
        return $this->hasMany(Iniciativa::class);
    }

    public function centros(): HasMany
    {
        return $this->hasMany(Centro::class);
    }

    public function profesionales(): HasMany
    {
        return $this->hasMany(Profesional::class);
    }
}
