<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inscripcion extends Model
{
    use HasFactory;

    protected $table = 'inscripciones';

    protected $fillable = ['user_id', 'ciclo_id', 'estado', 'precio'];

    protected $casts = ['precio' => 'decimal:2'];

    public function yoguini()
    {
        return $this->belongsTo(Yoguini::class, 'user_id');
    }

    public function ciclo()
    {
        return $this->belongsTo(Ciclo::class);
    }

    public function horarios()
    {
        return $this->belongsToMany(Horario::class, 'inscripcion_horarios')->withTimestamps();
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class);
    }

    public function recuperaciones()
    {
        return $this->hasMany(Recuperacion::class);
    }
}