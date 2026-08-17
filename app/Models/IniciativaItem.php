<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ítem necesario de una iniciativa (meta vs aportado cacheado).
 *
 * @property int $id
 * @property int $iniciativa_id
 * @property string $nombre
 * @property string $unidad
 * @property int $cantidad_meta
 * @property int $cantidad_aportada
 * @property int $orden
 */
class IniciativaItem extends Model
{
    protected $table = 'iniciativa_items';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'iniciativa_id',
        'nombre',
        'unidad',
        'cantidad_meta',
        'cantidad_aportada',
        'orden',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'iniciativa_id' => 'integer',
            'cantidad_meta' => 'integer',
            'cantidad_aportada' => 'integer',
            'orden' => 'integer',
            'version' => 'integer',
        ];
    }

    public function iniciativa(): BelongsTo
    {
        return $this->belongsTo(Iniciativa::class);
    }

    public function aporteItems(): HasMany
    {
        return $this->hasMany(AporteItem::class);
    }

    /**
     * Porcentaje 0–100 hacia la meta.
     */
    public function progresoPorcentaje(): int
    {
        if ($this->cantidad_meta <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->cantidad_aportada / $this->cantidad_meta) * 100));
    }
}
