<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

class Cargo extends Model
{
    const PENDIENTE = 'pendiente';
    const PARCIAL = 'parcial';
    const PAGADO = 'pagado';
    const ANULADO = 'anulado';

    protected $table = 'cargos';

    protected $fillable = [
        'user_id',
        'inscripcion_id',
        'ciclo_id',
        'periodo',
        'concepto',
        'monto_original',
        'descuento',
        'motivo_descuento',
        'monto_final',
        'vence_el',
        'estado',
        'anulado_en',
        'anulado_por',
        'motivo_anulacion',
    ];

    protected $casts = [
        'periodo' => 'date:Y-m-d',
        'vence_el' => 'date:Y-m-d',
        'anulado_en' => 'datetime',
    ];

    public function yoguini()
    {
        return $this->belongsTo(Yoguini::class, 'user_id');
    }

    public function inscripcion()
    {
        return $this->belongsTo(Inscripcion::class);
    }

    public function ciclo()
    {
        return $this->belongsTo(Ciclo::class)->withTrashed();
    }

    public function aplicaciones()
    {
        return $this->hasMany(PagoAplicacion::class);
    }

    public function finalCents()
    {
        return Money::cents($this->monto_final);
    }

    public function appliedCents()
    {
        $applications = $this->relationLoaded('aplicaciones') ? $this->aplicaciones : $this->aplicaciones()->get();

        return Money::sum($applications->where('estado', PagoAplicacion::VIGENTE)->pluck('monto'));
    }

    public function balanceCents()
    {
        if ($this->estado === self::ANULADO) {
            return 0;
        }

        return max(0, $this->finalCents() - $this->appliedCents());
    }
}
