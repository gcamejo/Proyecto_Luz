<?php

namespace Database\Factories;

use App\Models\Ciclo;
use App\Models\Inscripcion;
use App\Models\Yoguini;
use Illuminate\Database\Eloquent\Factories\Factory;

class InscripcionFactory extends Factory
{
    protected $model = Inscripcion::class;

    public function definition()
    {
        return [
            'user_id' => Yoguini::factory(),
            'ciclo_id' => Ciclo::factory(),
            'estado' => 'activa',
            'precio' => '1000.00',
        ];
    }
}