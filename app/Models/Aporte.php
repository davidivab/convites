<?php

namespace App\Models;

use App\Enums\EstadoAporte;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Compromiso de un usuario con una iniciativa (materiales y/o asistencia).
 *
 * @property int $id
 * @property int $user_id
 * @property int $iniciativa_id
 * @property EstadoAporte $estado
 * @property bool $asiste_al_convite
 * @property string|null $nota
 * @property \Illuminate\Support\Carbon|null $confirmado_at
 * @property \Illuminate\Support\Carbon|null $cancelado_at
 * @property \Illuminate\Support\Carbon|null $cumplido_at
 */
class Aporte extends Model
{
    protected $table = 'aportes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'iniciativa_id',
        'estado',
        'asiste_al_convite',
        'nota',
        'anonimo',
        'client_request_id',
        'confirmado_at',
        'cancelado_at',
        'cumplido_at',
        'evidencia_disk',
        'evidencia_path',
        'evidencia_nombre_original',
        'evidencia_mime',
        'evidencia_tamanio_bytes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'iniciativa_id' => 'integer',
            'estado' => EstadoAporte::class,
            'asiste_al_convite' => 'boolean',
            'anonimo' => 'boolean',
            'confirmado_at' => 'datetime',
            'cancelado_at' => 'datetime',
            'cumplido_at' => 'datetime',
            'evidencia_tamanio_bytes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function iniciativa(): BelongsTo
    {
        return $this->belongsTo(Iniciativa::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AporteItem::class);
    }

    /**
     * ¿Este estado debe contar hacia cantidad_aportada / asistentes?
     */
    public function cuentaParaProgreso(): bool
    {
        return in_array($this->estado, [EstadoAporte::Confirmado, EstadoAporte::Cumplido], true);
    }
}
