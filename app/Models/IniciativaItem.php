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
 * @property string|null $descripcion
 * @property float|null $valor_unitario_aprox
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
        'descripcion',
        'valor_unitario_aprox',
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
            'valor_unitario_aprox' => 'decimal:2',
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

    /**
     * Valor monetario aproximado (COP) de la meta completa.
     * Null si no hay estimado de valor unitario (nunca defaultea a 0).
     */
    public function valorMetaAprox(): ?float
    {
        if ($this->valor_unitario_aprox === null) {
            return null;
        }

        return (float) $this->valor_unitario_aprox * $this->cantidad_meta;
    }

    /**
     * Valor monetario aproximado (COP) de lo aportado hasta ahora.
     * Null si no hay estimado de valor unitario (nunca defaultea a 0).
     */
    public function valorAportadoAprox(): ?float
    {
        if ($this->valor_unitario_aprox === null) {
            return null;
        }

        return (float) $this->valor_unitario_aprox * $this->cantidad_aportada;
    }
}
