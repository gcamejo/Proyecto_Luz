<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    const VIGENTE = 'vigente';
    const ANULADO = 'anulado';

    protected $table = 'pagos';

    protected $fillable = [
        'user_id',
        'monto',
        'fecha_pago',
        'metodo',
        'referencia',
        'nota',
        'registrado_por',
        'estado',
        'anulado_en',
        'anulado_por',
        'motivo_anulacion',
    ];

    protected $casts = [
        'fecha_pago' => 'date:Y-m-d',
        'anulado_en' => 'datetime',
    ];

    public function yoguini()
    {
        return $this->belongsTo(Yoguini::class, 'user_id');
    }

    public function aplicaciones()
    {
        return $this->hasMany(PagoAplicacion::class);
    }

    public function amountCents()
    {
        return Money::cents($this->monto);
    }

    public function appliedCents()
    {
        $applications = $this->relationLoaded('aplicaciones') ? $this->aplicaciones : $this->aplicaciones()->get();

        return Money::sum($applications->where('estado', PagoAplicacion::VIGENTE)->pluck('monto'));
    }

    public function remainingCents()
    {
        if ($this->estado !== self::VIGENTE) {
            return 0;
        }

        return max(0, $this->amountCents() - $this->appliedCents());
    }
}
