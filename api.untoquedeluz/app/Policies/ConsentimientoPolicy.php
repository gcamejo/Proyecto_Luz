<?php

namespace App\Policies;

use App\Models\Yoguini;

class ConsentimientoPolicy
{
    public function viewAny(Yoguini $user): bool
    {
        return $user->perfil === 'Admin';
    }
}