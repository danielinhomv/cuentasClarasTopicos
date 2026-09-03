<?php

namespace App\Policies;

use App\Models\Gasto;
use App\Models\User;
use App\Models\Viaje;

class GastoPolicy
{
    private function canAccessViaje(User $user, Viaje $viaje): bool
    {
        return $user->id === $viaje->user_id
            || $viaje->participantes()->where('user_id', $user->id)->exists();
    }

    public function viewAny(User $user, Viaje $viaje): bool
    {
        return $this->canAccessViaje($user, $viaje);
    }

    public function view(User $user, Gasto $gasto): bool
    {
        return $this->canAccessViaje($user, $gasto->viaje);
    }

    public function create(User $user, Viaje $viaje): bool
    {
        return $this->canAccessViaje($user, $viaje);
    }

    public function update(User $user, Gasto $gasto): bool
    {
        return $this->canAccessViaje($user, $gasto->viaje);
    }

    public function delete(User $user, Gasto $gasto): bool
    {
        return $this->canAccessViaje($user, $gasto->viaje);
    }
}
