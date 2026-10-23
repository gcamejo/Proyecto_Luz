<?php

namespace Database\Factories;

use App\Models\Yoguini;
use App\Services\LegalConsentService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class YoguiniFactory extends Factory
{
    protected $model = Yoguini::class;

    public function definition()
    {
        return [
            'nombre' => substr($this->faker->firstName(), 0, 30),
            'apellido' => substr($this->faker->lastName(), 0, 30),
            'direccion' => substr($this->faker->streetAddress(), 0, 30),
            'numero' => $this->faker->numberBetween(1, 9999),
            'telefono' => substr($this->faker->phoneNumber(), 0, 30),
            'fechaNacimiento' => '1990-01-01',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'perfil' => 'user',
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Yoguini $yoguini) {
            app(LegalConsentService::class)->acceptCurrent($yoguini);
        });
    }
}