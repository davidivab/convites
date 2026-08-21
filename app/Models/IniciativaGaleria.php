<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Imagen o video de galería de una iniciativa (P53, parte 3 + P54).
 * `tipo` se infiere server-side desde el MIME (D-H), nunca se acepta como
 * input crudo del cliente — mirror de `IniciativaAvanceMedia`.
 *
 * @property int $id
 * @property int $iniciativa_id
 * @property string $path
 * @property string $tipo
 * @property int $orden
 * @property int|null $ancho
 * @property int|null $alto
 * @property int|null $duracion_segundos
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
            'iniciativa_id' => 'integer',
            'orden' => 'integer',
            'ancho' => 'integer',
            'alto' => 'integer',
            'duracion_segundos' => 'integer',
        ];
    }

    public function iniciativa(): BelongsTo
    {
        return $this->belongsTo(Iniciativa::class);
    }
}
