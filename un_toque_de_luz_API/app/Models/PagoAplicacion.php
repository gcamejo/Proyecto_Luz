<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoAplicacion extends Model
{
    const VIGENTE = 'vigente';
    const ANULADA = 'anulada';

    protected $table = 'pago_aplicaciones';

    protected $fillable = [
        'pago_id',
        'cargo_id',
        'monto',
        'estado',
        'anulada_en',
        'anulada_por',
        'motivo_anulacion',
        'registrado_por',
    ];

    protected $casts = [
        'anulada_en' => 'datetime',
    ];

    public function pago()
    {
        return $this->belongsTo(Pago::class);
    }

    public function cargo()
    {
        return $this->belongsTo(Cargo::class);
    }
}
