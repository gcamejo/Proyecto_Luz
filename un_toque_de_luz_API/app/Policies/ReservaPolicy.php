<?php

namespace App\Policies;

use App\Models\Reserva;
use App\Models\Yoguini;

class ReservaPolicy
{
    public function cancel(Yoguini $yoguini, Reserva $reserva)
    {
        return (int) $reserva->user_id === (int) $yoguini->id;
    }

    public function attend(Yoguini $yoguini, Reserva $reserva)
    {
        return $yoguini->perfil === 'Admin';
    }
}