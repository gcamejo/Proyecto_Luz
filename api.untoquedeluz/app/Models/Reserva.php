<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    use HasFactory;

    protected $fillable = ['inscripcion_id', 'user_id', 'clase_id', 'tipo', 'estado'];

    public function inscripcion()
    {
        return $this->belongsTo(Inscripcion::class);
    }

    public function yoguini()
    {
        return $this->belongsTo(Yoguini::class, 'user_id');
    }

    public function clase()
    {
        return $this->belongsTo(Clase::class);
    }

    public function recuperacionGenerada()
    {
        return $this->hasOne(Recuperacion::class, 'reserva_origen_id');
    }
}