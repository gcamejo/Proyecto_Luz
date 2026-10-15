<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCicloHorariosTable extends Migration
{
    public function up()
    {
        Schema::create('ciclo_horarios', function (Blueprint $table) {
            $table->foreignId('ciclo_id')->constrained('ciclos');
            $table->foreignId('horario_id')->constrained('horarios');
            $table->primary(['ciclo_id', 'horario_id']);
        });

        DB::table('clases')
            ->select('ciclo_id', 'horario_id')
            ->distinct()
            ->get()
            ->each(function ($classSchedule) {
                DB::table('ciclo_horarios')->insertOrIgnore([
                    'ciclo_id' => $classSchedule->ciclo_id,
                    'horario_id' => $classSchedule->horario_id,
                ]);
            });
    }

    public function down()
    {
        Schema::dropIfExists('ciclo_horarios');
    }
}