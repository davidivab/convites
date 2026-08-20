<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Media (imagen|video) de un avance de convite (P54). Mirror de
 * `IniciativaGaleria`. `tipo` se infiere server-side desde el MIME (D-H),
 * nunca se acepta como input crudo del cliente.
 *
 * @property int $id
 * @property int $iniciativa_avance_id
 * @property string $path
 * @property string $tipo
 * @property int $orden
 * @property int|null $ancho
 * @property int|null $alto
 * @property int|null $duracion_segundos
 */
class IniciativaAvanceMedia extends Model
{
    protected $table = 'iniciativa_avance_media';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'iniciativa_avance_id',
        'path',
        'tipo',
        'orden',
        'ancho',
        'alto',
        'duracion_segundos',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'iniciativa_avance_id' => 'integer',
            'orden' => 'integer',
            'ancho' => 'integer',
            'alto' => 'integer',
            'duracion_segundos' => 'integer',
        ];
    }

    public function avance(): BelongsTo
    {
        return $this->belongsTo(IniciativaAvance::class, 'iniciativa_avance_id');
    }
}
