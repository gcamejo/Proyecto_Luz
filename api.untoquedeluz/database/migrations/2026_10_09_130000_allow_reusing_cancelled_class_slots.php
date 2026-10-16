<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AllowReusingCancelledClassSlots extends Migration
{
    public function up()
    {
        if (!Schema::hasIndex('clases', 'clases_horario_fecha_index')) {
            Schema::table('clases', fn (Blueprint $table) => $table->index(['horario_id', 'fecha'], 'clases_horario_fecha_index'));
        }

        if (Schema::hasIndex('clases', ['horario_id', 'fecha'], 'unique')) {
            $oldUnique = collect(Schema::getIndexes('clases'))
                ->first(fn (array $index) => $index['unique'] && $index['columns'] === ['horario_id', 'fecha']);

            Schema::table('clases', fn (Blueprint $table) => $table->dropUnique($oldUnique['name']));
        }

        if (!Schema::hasIndex('clases', ['ciclo_id', 'horario_id', 'fecha'], 'unique')) {
            Schema::table('clases', fn (Blueprint $table) => $table->unique(['ciclo_id', 'horario_id', 'fecha'], 'clases_cycle_schedule_date_unique'));
        }
    }

    public function down()
    {
        if (!Schema::hasIndex('clases', ['horario_id', 'fecha'], 'unique')) {
            Schema::table('clases', fn (Blueprint $table) => $table->unique(['horario_id', 'fecha'], 'clases_horario_id_fecha_unique'));
        }

        if (Schema::hasIndex('clases', 'clases_horario_fecha_index')) {
            Schema::table('clases', fn (Blueprint $table) => $table->dropIndex('clases_horario_fecha_index'));
        }

        if (Schema::hasIndex('clases', 'clases_cycle_schedule_date_unique')) {
            Schema::table('clases', fn (Blueprint $table) => $table->dropUnique('clases_cycle_schedule_date_unique'));
        }
    }
}
