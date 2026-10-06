<?php

namespace Database\Factories;

use App\Models\Horario;
use Illuminate\Database\Eloquent\Factories\Factory;

class HorarioFactory extends Factory
{
    protected $model = Horario::class;

    public function definition()
    {
        return [
            'dia_semana' => $this->faker->numberBetween(0, 6),
            'hora_inicio' => '10:00:00',
            'duracion_min' => 60,
            'nivel' => 'General',
            'profesor' => 'Instructor',
            'cupo' => 10,
            'activo' => true,
        ];
    }
}