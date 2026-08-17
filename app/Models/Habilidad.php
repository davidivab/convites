<?php

namespace App\Models;

use App\Enums\TipoHabilidad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Habilidad catalogada que un usuario puede ofrecer.
 *
 * @property int $id
 * @property string $slug
 * @property string $nombre
 * @property TipoHabilidad $tipo
 * @property int $orden
 * @property bool $activo
 */
class Habilidad extends Model
{
    /**
     * Plural irregular en español (Eloquent generaría "habilidads").
     */
    protected $table = 'habilidades';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'nombre',
        'tipo',
        'orden',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoHabilidad::class,
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
