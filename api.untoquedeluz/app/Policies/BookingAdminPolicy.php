<?php

namespace App\Policies;

use App\Models\Yoguini;

class BookingAdminPolicy
{
    public function manage(Yoguini $yoguini)
    {
        return $yoguini->perfil === 'Admin';
    }
}