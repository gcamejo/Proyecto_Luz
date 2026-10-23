<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consentimientos', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->enum('tipo', ['privacidad', 'terminos']);
            $table->string('version', 64);
            $table->timestamp('aceptado_en');
            $table->foreign('user_id')->references('id')->on('yoguinis');
            $table->unique(['user_id', 'tipo', 'version']);
            $table->index(['user_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consentimientos');
    }
};