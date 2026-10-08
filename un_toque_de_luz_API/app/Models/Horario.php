<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Horario extends Model
{
    use HasFactory;

    protected $fillable = [
        'dia_semana',
        'hora_inicio',
        'duracion_min',
        'nivel',
        'profesor',
        'cupo',
        'activo',
    ];

    protected $casts = [
        'dia_semana' => 'integer',
        'duracion_min' => 'integer',
        'cupo' => 'integer',
        'activo' => 'boolean',
    ];

    public function clases()
    {
        return $this->hasMany(Clase::class);
    }

    public function ciclos()
    {
        return $this->belongsToMany(Ciclo::class, 'ciclo_horarios');
    }

    public function inscripciones()
    {
        return $this->belongsToMany(Inscripcion::class, 'inscripcion_horarios')->withTimestamps();
    }
}
