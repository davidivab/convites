<?php

namespace App\Models;

use Database\Factories\CategoriaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoría de iniciativa (vivienda, comunitario, ...).
 *
 * @property int $id
 * @property string $slug
 * @property string $nombre
 * @property string|null $descripcion
 * @property int $orden
 * @property bool $activo
 */
class Categoria extends Model
{
    /** @use HasFactory<CategoriaFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'nombre',
        'descripcion',
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

    public function iniciativas(): HasMany
    {
        return $this->hasMany(Iniciativa::class);
    }
}
