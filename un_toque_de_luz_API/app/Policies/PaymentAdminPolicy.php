<?php

namespace App\Policies;

use App\Models\Yoguini;

class PaymentAdminPolicy
{
    public function manage(Yoguini $yoguini)
    {
        return $yoguini->perfil === 'Admin';
    }
}
