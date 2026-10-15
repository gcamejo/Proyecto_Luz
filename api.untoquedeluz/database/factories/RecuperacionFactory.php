<?php

namespace Database\Factories;

use App\Models\Inscripcion;
use App\Models\Recuperacion;
use App\Models\Reserva;
use App\Models\Yoguini;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecuperacionFactory extends Factory
{
    protected $model = Recuperacion::class;

    public function definition()
    {
        return [
            'reserva_origen_id' => Reserva::factory(),
            'user_id' => Yoguini::factory(),
            'inscripcion_id' => Inscripcion::factory(),
            'reserva_destino_id' => null,
            'vence_en' => now()->endOfMonth()->toDateString(),
            'estado' => 'disponible',
        ];
    }
}