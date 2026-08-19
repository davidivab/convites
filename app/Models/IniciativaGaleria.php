<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Imagen de galería de una iniciativa (P53, parte 3).
 *
 * @property int $id
 * @property int $iniciativa_id
 * @property string $path
 * @property int $orden
 * @property int|null $ancho
 * @property int|null $alto
 */
class IniciativaGaleria extends Model
{
    protected $table = 'iniciativa_galeria';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'iniciativa_id',
        'path',
        'orden',
        'ancho',
        'alto',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'iniciativa_id' => 'integer',
            'orden' => 'integer',
            'ancho' => 'integer',
            'alto' => 'integer',
        ];
    }

    public function iniciativa(): BelongsTo
    {
        return $this->belongsTo(Iniciativa::class);
    }
}
