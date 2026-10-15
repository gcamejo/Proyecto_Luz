<?php

namespace App\Policies;

use App\Models\Recuperacion;
use App\Models\Yoguini;

class RecuperacionPolicy
{
    public function book(Yoguini $yoguini, Recuperacion $recuperacion)
    {
        return (int) $recuperacion->user_id === (int) $yoguini->id;
    }
}