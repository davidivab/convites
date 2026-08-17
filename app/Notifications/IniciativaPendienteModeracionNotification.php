<?php

namespace App\Notifications;

use App\Models\Iniciativa;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Convite enviado a revisión — para moderadores del municipio.
 */
class IniciativaPendienteModeracionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Iniciativa $iniciativa,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'iniciativa_pendiente_moderacion',
            'iniciativa_id' => $this->iniciativa->id,
            'slug' => $this->iniciativa->slug,
            'titulo' => $this->iniciativa->titulo,
            'municipio_id' => $this->iniciativa->municipio_id,
            'mensaje' => 'Hay un convite pendiente de moderación: '.$this->iniciativa->titulo,
        ];
    }
}
