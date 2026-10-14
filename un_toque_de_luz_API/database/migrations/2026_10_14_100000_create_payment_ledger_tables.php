<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentLedgerTables extends Migration
{
    public function up()
    {
        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->foreign('user_id')->references('id')->on('yoguinis');
            $table->unsignedBigInteger('inscripcion_id')->nullable();
            $table->foreign('inscripcion_id')->references('id')->on('inscripciones');
            $table->unsignedBigInteger('ciclo_id')->nullable();
            $table->foreign('ciclo_id')->references('id')->on('ciclos');
            $table->date('periodo');
            $table->string('concepto');
            $table->decimal('monto_original', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->string('motivo_descuento')->nullable();
            $table->decimal('monto_final', 12, 2);
            $table->date('vence_el')->nullable();
            $table->string('estado')->default('pendiente');
            $table->timestamp('anulado_en')->nullable();
            $table->integer('anulado_por')->nullable();
            $table->foreign('anulado_por')->references('id')->on('yoguinis');
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();
            $table->unique('inscripcion_id');
            $table->index(['periodo', 'estado']);
            $table->index(['user_id', 'periodo']);
        });

        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->foreign('user_id')->references('id')->on('yoguinis');
            $table->decimal('monto', 12, 2);
            $table->date('fecha_pago');
            $table->string('metodo');
            $table->string('referencia')->nullable();
            $table->text('nota')->nullable();
            $table->integer('registrado_por');
            $table->foreign('registrado_por')->references('id')->on('yoguinis');
            $table->string('estado')->default('vigente');
            $table->timestamp('anulado_en')->nullable();
            $table->integer('anulado_por')->nullable();
            $table->foreign('anulado_por')->references('id')->on('yoguinis');
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'estado']);
            $table->index('fecha_pago');
        });

        Schema::create('pago_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pago_id');
            $table->foreign('pago_id')->references('id')->on('pagos');
            $table->unsignedBigInteger('cargo_id');
            $table->foreign('cargo_id')->references('id')->on('cargos');
            $table->decimal('monto', 12, 2);
            $table->string('estado')->default('vigente');
            $table->timestamp('anulada_en')->nullable();
            $table->integer('anulada_por')->nullable();
            $table->foreign('anulada_por')->references('id')->on('yoguinis');
            $table->text('motivo_anulacion')->nullable();
            $table->integer('registrado_por');
            $table->foreign('registrado_por')->references('id')->on('yoguinis');
            $table->timestamps();
            $table->index(['pago_id', 'estado']);
            $table->index(['cargo_id', 'estado']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('pago_aplicaciones');
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('cargos');
    }
}
