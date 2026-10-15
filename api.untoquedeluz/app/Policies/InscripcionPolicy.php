<?php

namespace App\Policies;

use App\Models\Inscripcion;
use App\Models\Yoguini;

class InscripcionPolicy
{
    public function cancel(Yoguini $yoguini, Inscripcion $inscripcion)
    {
        return (int) $inscripcion->user_id === (int) $yoguini->id;
    }
}