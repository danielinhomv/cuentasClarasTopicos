<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Viaje;

class GastoBitacoraPolicy
{
    public function viewAny(User $user, Viaje $viaje): bool
    {
        return $user->id === $viaje->user_id;
    }
}
