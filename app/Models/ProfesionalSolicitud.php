<?php

namespace App\Models;

use App\Enums\EstadoSolicitudProfesional;
use App\Enums\PreferenciaContacto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitud de contacto hacia un profesional (PII del solicitante).
 *
 * @property int $id
 * @property int $profesional_id
 * @property int|null $user_id
 * @property string $nombre
 * @property string $celular
 * @property string|null $email
 * @property int|null $zona_id
 * @property PreferenciaContacto $preferencia_contacto
 * @property string $mensaje
 * @property EstadoSolicitudProfesional $estado
 * @property \Illuminate\Support\Carbon|null $notificada_at
 * @property \Illuminate\Support\Carbon|null $leida_at
 * @property \Illuminate\Support\Carbon|null $cerrada_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
class ProfesionalSolicitud extends Model
{
    protected $table = 'profesional_solicitudes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'profesional_id',
        'user_id',
        'nombre',
        'celular',
        'email',
        'zona_id',
        'municipio_id',
        'preferencia_contacto',
        'mensaje',
        'estado',
        'notificada_at',
        'leida_at',
        'cerrada_at',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'profesional_id' => 'integer',
            'user_id' => 'integer',
            'zona_id' => 'integer',
            'municipio_id' => 'integer',
            'preferencia_contacto' => PreferenciaContacto::class,
            'estado' => EstadoSolicitudProfesional::class,
            'notificada_at' => 'datetime',
            'leida_at' => 'datetime',
            'cerrada_at' => 'datetime',
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

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }
}
