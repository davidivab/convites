<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Entrega notificaciones solo a moderadores del municipio (admin recibe todas).
 */
class ModeratorNotificationService
{
    public function notifyForMunicipio(?int $municipioId, Notification $notification): void
    {
        if ($municipioId === null) {
            return;
        }

        $moderators = User::query()
            ->role('moderator')
            ->whereHas(
                'municipiosAsignados',
                fn ($q) => $q->where('municipios.id', $municipioId),
            )
            ->get();

        $admins = User::query()->role('admin')->get();

        $recipients = $moderators->merge($admins)->unique('id')->values();

        if ($recipients->isEmpty()) {
            return;
        }

        NotificationFacade::send($recipients, $notification);
    }
}
