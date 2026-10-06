<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ciclo extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'clases_por_semana',
        'precio',
        'activo',
    ];

    protected $casts = [
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_fin' => 'date:Y-m-d',
        'clases_por_semana' => 'integer',
        'precio' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function clases()
    {
        return $this->hasMany(Clase::class);
    }

    public function inscripciones()
    {
        return $this->hasMany(Inscripcion::class);
    }
}