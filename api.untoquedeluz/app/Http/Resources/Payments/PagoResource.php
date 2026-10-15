<?php

namespace App\Http\Resources\Payments;

use App\Models\PagoAplicacion;
use App\Support\Money;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'monto' => Money::decimal($this->amountCents()),
            'fecha_pago' => $this->fecha_pago->toDateString(),
            'metodo' => $this->metodo,
            'referencia' => $this->referencia,
            'nota' => $this->nota,
            'estado' => $this->estado,
            'anulado_en' => $this->anulado_en ? $this->anulado_en->toIso8601String() : null,
            'motivo_anulacion' => $this->motivo_anulacion,
            'aplicado' => Money::decimal($this->appliedCents()),
            'remanente' => Money::decimal($this->remainingCents()),
            'aplicaciones' => PagoAplicacionResource::collection($this->whenLoaded('aplicaciones')),
            'cargos_afectados' => $this->when($this->relationLoaded('aplicaciones'), function () {
                return $this->affectedCharges();
            }),
        ];
    }

    private function affectedCharges()
    {
        $grouped = [];
        foreach ($this->aplicaciones->where('estado', PagoAplicacion::VIGENTE) as $application) {
            $key = (int) $application->cargo_id;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'cargo_id' => $key,
                    'concepto' => $application->relationLoaded('cargo') ? $application->cargo->concepto : null,
                    'periodo' => $application->relationLoaded('cargo') ? $application->cargo->periodo->toDateString() : null,
                    'monto' => 0,
                ];
            }
            $grouped[$key]['monto'] += Money::cents($application->monto);
        }

        return array_values(array_map(function ($row) {
            $row['monto'] = Money::decimal($row['monto']);
            return $row;
        }, $grouped));
    }
}
