<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftDeletesToCiclosTable extends Migration
{
    public function up()
    {
        Schema::table('ciclos', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::table('ciclos', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}