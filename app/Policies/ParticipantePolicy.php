<?php

namespace App\Policies;

use App\Models\Participante;
use App\Models\User;
use App\Models\Viaje;

class ParticipantePolicy
{
    public function viewAny(User $user, Viaje $viaje): bool
    {
        return $user->id === $viaje->user_id;
    }

    public function create(User $user, Viaje $viaje): bool
    {
        return $user->id === $viaje->user_id;
    }

    public function update(User $user, Participante $participante): bool
    {
        return $user->id === $participante->viaje->user_id;
    }

    public function delete(User $user, Participante $participante): bool
    {
        return $user->id === $participante->viaje->user_id;
    }
}
