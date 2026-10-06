<?php

namespace Database\Factories;

use App\Models\Feriado;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeriadoFactory extends Factory
{
    protected $model = Feriado::class;

    public function definition()
    {
        return [
            'fecha' => $this->faker->unique()->date(),
            'descripcion' => 'Holiday',
        ];
    }
}