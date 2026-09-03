<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Viaje;

class ViajePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Viaje $viaje): bool
    {
        return $user->id === $viaje->user_id
            || $viaje->participantes()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Viaje $viaje): bool
    {
        return $user->id === $viaje->user_id;
    }

    public function delete(User $user, Viaje $viaje): bool
    {
        return $user->id === $viaje->user_id;
    }
}
