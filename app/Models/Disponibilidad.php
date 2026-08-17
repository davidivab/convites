<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Franja de disponibilidad (fines de semana, emergencias, ...).
 *
 * @property int $id
 * @property string $slug
 * @property string $nombre
 * @property int $orden
 * @property bool $activo
 */
class Disponibilidad extends Model
{
    /**
     * Plural irregular en español (Eloquent generaría "disponibilidads").
     */
    protected $table = 'disponibilidades';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'nombre',
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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
