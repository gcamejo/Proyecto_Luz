<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AllowReusingCancelledClassSlots extends Migration
{
    public function up()
    {
        Schema::table('clases', function (Blueprint $table) {
            $table->index(['horario_id', 'fecha'], 'clases_horario_fecha_index');
            $table->dropUnique('clases_horario_id_fecha_unique');
            $table->unique(['ciclo_id', 'horario_id', 'fecha'], 'clases_cycle_schedule_date_unique');
        });
    }

    public function down()
    {
        Schema::table('clases', function (Blueprint $table) {
            $table->unique(['horario_id', 'fecha'], 'clases_horario_id_fecha_unique');
            $table->dropIndex('clases_horario_fecha_index');
            $table->dropUnique('clases_cycle_schedule_date_unique');
        });
    }
}
