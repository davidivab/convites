<?php

namespace App\Policies;

use App\Models\Iniciativa;
use App\Models\User;

/**
 * Ownership / moderación de iniciativas.
 */
class IniciativaPolicy
{
    public function view(User $user, Iniciativa $iniciativa): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('iniciativas.create');
    }

    public function update(User $user, Iniciativa $iniciativa): bool
    {
        return $user->id === $iniciativa->user_id || $user->canModerateIniciativa($iniciativa);
    }

    public function enviarRevision(User $user, Iniciativa $iniciativa): bool
    {
        return $user->id === $iniciativa->user_id && $user->can('iniciativas.create');
    }

    public function moderate(User $user, Iniciativa $iniciativa): bool
    {
        return $user->canModerateIniciativa($iniciativa);
    }

    /**
     * P43: el dueño puede cerrar/detener su propio convite (fuera del flujo
     * de moderación); un moderador de su municipio o el admin también.
     */
    public function close(User $user, Iniciativa $iniciativa): bool
    {
        return $user->id === $iniciativa->user_id || $user->canModerateIniciativa($iniciativa);
    }
}
