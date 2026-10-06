<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCycleBookingTables extends Migration
{
    public function up()
    {
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_inicio');
            $table->unsignedSmallInteger('duracion_min');
            $table->string('nivel')->nullable();
            $table->string('profesor')->nullable();
            $table->unsignedSmallInteger('cupo');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['activo', 'dia_semana']);
        });

        Schema::create('ciclos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->unsignedTinyInteger('clases_por_semana');
            $table->decimal('precio', 10, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['activo', 'fecha_inicio', 'fecha_fin']);
        });

        Schema::create('clases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciclo_id')->constrained('ciclos');
            $table->foreignId('horario_id')->constrained('horarios');
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->unsignedSmallInteger('duracion_min');
            $table->string('nivel')->nullable();
            $table->string('profesor')->nullable();
            $table->unsignedSmallInteger('cupo');
            $table->string('estado')->default('programada');
            $table->timestamps();
            $table->unique(['horario_id', 'fecha']);
            $table->index('fecha');
            $table->index(['ciclo_id', 'fecha', 'estado']);
        });

        Schema::create('inscripciones', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->foreign('user_id')->references('id')->on('yoguinis');
            $table->foreignId('ciclo_id')->constrained('ciclos');
            $table->string('estado')->default('activa');
            $table->decimal('precio', 10, 2);
            $table->timestamps();
            $table->unique(['user_id', 'ciclo_id']);
            $table->index(['ciclo_id', 'estado']);
        });

        Schema::create('inscripcion_horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscripcion_id')->constrained('inscripciones');
            $table->foreignId('horario_id')->constrained('horarios');
            $table->timestamps();
            $table->unique(['inscripcion_id', 'horario_id']);
            $table->index('horario_id');
        });

        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscripcion_id')->constrained('inscripciones')->cascadeOnDelete();
            $table->integer('user_id');
            $table->foreign('user_id')->references('id')->on('yoguinis');
            $table->foreignId('clase_id')->constrained('clases');
            $table->string('tipo')->default('regular');
            $table->string('estado')->default('reservada');
            $table->timestamps();
            $table->unique(['user_id', 'clase_id']);
            $table->index(['clase_id', 'estado']);
            $table->index(['user_id', 'estado']);
        });

        Schema::create('recuperaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_origen_id')->constrained('reservas');
            $table->integer('user_id');
            $table->foreign('user_id')->references('id')->on('yoguinis');
            $table->foreignId('inscripcion_id')->constrained('inscripciones');
            $table->foreignId('reserva_destino_id')->nullable()->unique()->constrained('reservas');
            $table->date('vence_en');
            $table->string('estado')->default('disponible');
            $table->timestamps();
            $table->unique('reserva_origen_id');
            $table->index(['user_id', 'estado', 'vence_en']);
        });

        Schema::create('feriados', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('configuracion', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();
            $table->string('valor');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('configuracion');
        Schema::dropIfExists('feriados');
        Schema::dropIfExists('recuperaciones');
        Schema::dropIfExists('reservas');
        Schema::dropIfExists('inscripcion_horarios');
        Schema::dropIfExists('inscripciones');
        Schema::dropIfExists('clases');
        Schema::dropIfExists('ciclos');
        Schema::dropIfExists('horarios');
    }
}