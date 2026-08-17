<?php

namespace App\Models;

use App\Enums\EstadoSolicitudRol;
use App\Enums\TipoSolicitudRol;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Solicitud de un ciudadano para obtener el rol `moderador` o `voluntario`.
 * El admin aprueba/rechaza — ver AdminSolicitudRolController.
 *
 * @property int $id
 * @property int $user_id
 * @property TipoSolicitudRol $rol
 * @property string|null $mensaje
 * @property EstadoSolicitudRol $estado
 * @property string|null $nota_revision
 * @property int|null $revisado_por
 * @property \Illuminate\Support\Carbon|null $revisado_at
 */
class SolicitudRol extends Model
{
    protected $table = 'solicitudes_rol';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'rol',
        'mensaje',
        'estado',
        'nota_revision',
        'revisado_por',
        'revisado_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'rol' => TipoSolicitudRol::class,
            'estado' => EstadoSolicitudRol::class,
            'revisado_por' => 'integer',
            'revisado_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function municipios(): BelongsToMany
    {
        return $this->belongsToMany(Municipio::class, 'solicitud_rol_municipio');
    }
}
