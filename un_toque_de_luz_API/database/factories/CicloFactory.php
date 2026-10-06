<?php

namespace Database\Factories;

use App\Models\Ciclo;
use Illuminate\Database\Eloquent\Factories\Factory;

class CicloFactory extends Factory
{
    protected $model = Ciclo::class;

    public function definition()
    {
        return [
            'nombre' => 'Cycle '.$this->faker->unique()->word(),
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addMonth()->toDateString(),
            'clases_por_semana' => 2,
            'precio' => '1000.00',
            'activo' => true,
        ];
    }
}