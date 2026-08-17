<?php

namespace App\Notifications;

use App\Models\Aporte;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Nuevo aporte confirmado en un municipio — para moderadores asignados.
 */
class AporteConfirmadoNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Aporte $aporte,
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
        $iniciativa = $this->aporte->iniciativa;

        return [
            'tipo' => 'aporte_confirmado',
            'aporte_id' => $this->aporte->id,
            'iniciativa_id' => $iniciativa?->id,
            'slug' => $iniciativa?->slug,
            'titulo' => $iniciativa?->titulo,
            'municipio_id' => $iniciativa?->municipio_id,
            'mensaje' => 'Nuevo aporte confirmado en: '.($iniciativa?->titulo ?? 'convite'),
        ];
    }
}
