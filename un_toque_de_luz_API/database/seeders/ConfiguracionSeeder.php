<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use Illuminate\Database\Seeder;

class ConfiguracionSeeder extends Seeder
{
    public function run()
    {
        Configuracion::firstOrCreate(
            ['clave' => 'horas_aviso_minimas'],
            ['valor' => '24']
        );
    }
}