<?php

namespace App\Http\Resources\Payments;

use App\Models\PagoAplicacion;
use App\Support\Money;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoAplicacionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'pago_id' => $this->pago_id,
            'cargo_id' => $this->cargo_id,
            'monto' => Money::decimal(Money::cents($this->monto)),
            'estado' => $this->estado,
            'anulada_en' => $this->anulada_en ? $this->anulada_en->toIso8601String() : null,
            'motivo_anulacion' => $this->motivo_anulacion,
            'cargo' => $this->whenLoaded('cargo', function () {
                return [
                    'id' => $this->cargo->id,
                    'concepto' => $this->cargo->concepto,
                    'periodo' => $this->cargo->periodo->toDateString(),
                ];
            }),
        ];
    }
}
