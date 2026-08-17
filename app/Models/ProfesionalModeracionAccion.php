<?php

namespace App\Models;

use App\Enums\AccionModeracionProfesional;
use App\Enums\EstadoProfesional;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora de moderación de un perfil profesional.
 *
 * @property int $id
 * @property int $profesional_id
 * @property int|null $user_id
 * @property AccionModeracionProfesional $accion
 * @property EstadoProfesional|null $estado_anterior
 * @property EstadoProfesional|null $estado_nuevo
 * @property string|null $nota
 */
class ProfesionalModeracionAccion extends Model
{
    protected $table = 'profesional_moderacion_acciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'profesional_id',
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
            'profesional_id' => 'integer',
            'user_id' => 'integer',
            'accion' => AccionModeracionProfesional::class,
            'estado_anterior' => EstadoProfesional::class,
            'estado_nuevo' => EstadoProfesional::class,
        ];
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
