<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recuperacion extends Model
{
    use HasFactory;

    protected $table = 'recuperaciones';

    protected $fillable = [
        'reserva_origen_id',
        'user_id',
        'inscripcion_id',
        'reserva_destino_id',
        'vence_en',
        'estado',
    ];

    protected $casts = ['vence_en' => 'date:Y-m-d'];

    public function reservaOrigen()
    {
        return $this->belongsTo(Reserva::class, 'reserva_origen_id');
    }

    public function reservaDestino()
    {
        return $this->belongsTo(Reserva::class, 'reserva_destino_id');
    }

    public function yoguini()
    {
        return $this->belongsTo(Yoguini::class, 'user_id');
    }

    public function inscripcion()
    {
        return $this->belongsTo(Inscripcion::class);
    }
}