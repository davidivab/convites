<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preferencias de canales de notificación por usuario.
 *
 * @property int $id
 * @property int $user_id
 * @property bool $email_matching
 * @property bool $email_aportes
 * @property bool $email_avances
 * @property bool $email_moderacion
 * @property bool $email_profesionales
 * @property bool $email_digest_semanal
 * @property bool $whatsapp_habilitado
 */
class NotificacionPreferencia extends Model
{
    protected $table = 'notificacion_preferencias';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'email_matching',
        'email_aportes',
        'email_avances',
        'email_moderacion',
        'email_profesionales',
        'email_digest_semanal',
        'whatsapp_habilitado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'email_matching' => 'boolean',
            'email_aportes' => 'boolean',
            'email_avances' => 'boolean',
            'email_moderacion' => 'boolean',
            'email_profesionales' => 'boolean',
            'email_digest_semanal' => 'boolean',
            'whatsapp_habilitado' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
