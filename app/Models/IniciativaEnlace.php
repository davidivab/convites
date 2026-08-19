<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Enlace adicional de una iniciativa (P53, parte 3).
 *
 * @property int $id
 * @property int $iniciativa_id
 * @property string $titulo
 * @property string $url
 * @property int $orden
 */
class IniciativaEnlace extends Model
{
    protected $table = 'iniciativa_enlaces';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'iniciativa_id',
        'titulo',
        'url',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'iniciativa_id' => 'integer',
            'orden' => 'integer',
        ];
    }

    public function iniciativa(): BelongsTo
    {
        return $this->belongsTo(Iniciativa::class);
    }
}
