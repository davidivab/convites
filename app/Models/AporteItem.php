<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de material dentro de un aporte.
 *
 * @property int $id
 * @property int $aporte_id
 * @property int $iniciativa_item_id
 * @property int $cantidad
 */
class AporteItem extends Model
{
    protected $table = 'aporte_items';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'aporte_id',
        'iniciativa_item_id',
        'cantidad',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aporte_id' => 'integer',
            'iniciativa_item_id' => 'integer',
            'cantidad' => 'integer',
        ];
    }

    public function aporte(): BelongsTo
    {
        return $this->belongsTo(Aporte::class);
    }

    public function iniciativaItem(): BelongsTo
    {
        return $this->belongsTo(IniciativaItem::class);
    }
}
