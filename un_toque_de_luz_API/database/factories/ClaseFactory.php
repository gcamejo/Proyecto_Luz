<?php

namespace Database\Factories;

use App\Models\Clase;
use App\Models\Ciclo;
use App\Models\Horario;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClaseFactory extends Factory
{
    protected $model = Clase::class;

    public function definition()
    {
        return [
            'ciclo_id' => Ciclo::factory(),
            'horario_id' => Horario::factory(),
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '10:00:00',
            'duracion_min' => 60,
            'nivel' => 'General',
            'profesor' => 'Instructor',
            'cupo' => 10,
            'estado' => 'programada',
        ];
    }
}