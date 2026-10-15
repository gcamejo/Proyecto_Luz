<?php

namespace Database\Factories;

use App\Models\Clase;
use App\Models\Inscripcion;
use App\Models\Reserva;
use App\Models\Yoguini;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservaFactory extends Factory
{
    protected $model = Reserva::class;

    public function definition()
    {
        return [
            'inscripcion_id' => Inscripcion::factory(),
            'user_id' => Yoguini::factory(),
            'clase_id' => Clase::factory(),
            'tipo' => 'regular',
            'estado' => 'reservada',
        ];
    }
}