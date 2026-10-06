<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clase extends Model
{
    use HasFactory;

    protected $fillable = [
        'ciclo_id',
        'horario_id',
        'fecha',
        'hora_inicio',
        'duracion_min',
        'nivel',
        'profesor',
        'cupo',
        'estado',
    ];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
        'duracion_min' => 'integer',
        'cupo' => 'integer',
    ];

    public function ciclo()
    {
        return $this->belongsTo(Ciclo::class);
    }

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class);
    }
}