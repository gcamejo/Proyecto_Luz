<?php

namespace App\Http\Resources\Payments;

use App\Support\Money;
use Illuminate\Http\Resources\Json\JsonResource;

class CargoResource extends JsonResource
{
    public function toArray($request)
    {
        $applied = $this->appliedCents();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'alumno' => $this->whenLoaded('yoguini', function () {
                return [
                    'id' => $this->yoguini->id,
                    'nombre' => $this->yoguini->nombre,
                    'apellido' => $this->yoguini->apellido,
                    'email' => $this->yoguini->email,
                ];
            }),
            'inscripcion_id' => $this->inscripcion_id,
            'ciclo_id' => $this->ciclo_id,
            'ciclo' => $this->whenLoaded('ciclo', function () {
                return $this->ciclo ? ['id' => $this->ciclo->id, 'nombre' => $this->ciclo->nombre] : null;
            }),
            'periodo' => $this->periodo->toDateString(),
            'concepto' => $this->concepto,
            'monto_original' => Money::decimal(Money::cents($this->monto_original)),
            'descuento' => Money::decimal(Money::cents($this->descuento)),
            'motivo_descuento' => $this->motivo_descuento,
            'monto_final' => Money::decimal($this->finalCents()),
            'aplicado' => Money::decimal($applied),
            'saldo' => Money::decimal($this->balanceCents()),
            'vence_el' => $this->vence_el ? $this->vence_el->toDateString() : null,
            'estado' => $this->estado,
            'anulado_en' => $this->anulado_en ? $this->anulado_en->toIso8601String() : null,
            'motivo_anulacion' => $this->motivo_anulacion,
            'inscripcion_baja' => $this->whenLoaded('inscripcion', function () {
                return $this->inscripcion !== null && $this->inscripcion->estado === 'cancelada';
            }),
            'saldo_a_favor_alumno' => $this->when(isset($this->saldo_a_favor_alumno), function () {
                return $this->saldo_a_favor_alumno;
            }),
        ];
    }
}
