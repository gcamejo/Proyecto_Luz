<?php

namespace Database\Factories;

use App\Models\Yoguini;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class YoguiniFactory extends Factory
{
    protected $model = Yoguini::class;

    public function definition()
    {
        return [
            'nombre' => $this->faker->firstName(),
            'apellido' => $this->faker->lastName(),
            'direccion' => $this->faker->streetAddress(),
            'numero' => $this->faker->numberBetween(1, 9999),
            'telefono' => $this->faker->phoneNumber(),
            'fechaNacimiento' => '1990-01-01',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'perfil' => 'user',
        ];
    }
}