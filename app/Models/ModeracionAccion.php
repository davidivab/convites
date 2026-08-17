<?php

namespace App\Models;

use App\Enums\AccionModeracion;
use App\Enums\EstadoIniciativa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entrada de bitácora de moderación de una iniciativa.
 *
 * @property int $id
 * @property int $iniciativa_id
 * @property int|null $user_id
 * @property AccionModeracion $accion
 * @property EstadoIniciativa|null $estado_anterior
 * @property EstadoIniciativa|null $estado_nuevo
 * @property string|null $nota
 */
class ModeracionAccion extends Model
{
    protected $table = 'moderacion_acciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'iniciativa_id',
        'user_id',
        'accion',
        'estado_anterior',
        'estado_nuevo',
        'nota',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'iniciativa_id' => 'integer',
            'user_id' => 'integer',
            'accion' => AccionModeracion::class,
            'estado_anterior' => EstadoIniciativa::class,
            'estado_nuevo' => EstadoIniciativa::class,
        ];
    }

    public function iniciativa(): BelongsTo
    {
        return $this->belongsTo(Iniciativa::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
