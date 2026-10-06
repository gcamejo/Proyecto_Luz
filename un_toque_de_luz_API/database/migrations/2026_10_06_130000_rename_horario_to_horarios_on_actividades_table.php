<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RenameHorarioToHorariosOnActividadesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('actividades')) {
            throw new RuntimeException('La tabla actividades no existe.');
        }

        if (Schema::hasColumn('actividades', 'horario') && !Schema::hasColumn('actividades', 'horarios')) {
            DB::statement('ALTER TABLE `actividades` CHANGE `horario` `horarios` VARCHAR(30) NULL');
            return;
        }

        if (!Schema::hasColumn('actividades', 'horarios')) {
            Schema::table('actividades', function ($table) {
                $table->string('horarios', 30)->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('actividades', 'horarios') && !Schema::hasColumn('actividades', 'horario')) {
            DB::statement('ALTER TABLE `actividades` CHANGE `horarios` `horario` VARCHAR(30) NULL');
        }
    }
}
