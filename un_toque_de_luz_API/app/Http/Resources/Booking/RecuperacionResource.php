<?php

namespace App\Http\Resources\Booking;

use Illuminate\Http\Resources\Json\JsonResource;

class RecuperacionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'inscripcion_id' => $this->inscripcion_id,
            'reserva_origen_id' => $this->reserva_origen_id,
            'reserva_destino_id' => $this->reserva_destino_id,
            'vence_en' => $this->vence_en->toDateString(),
            'estado' => $this->estado,
            'reserva_origen' => $this->whenLoaded('reservaOrigen', function () {
                return (new ReservaResource($this->reservaOrigen))->resolve();
            }),
            'reserva_destino' => $this->whenLoaded('reservaDestino', function () {
                return (new ReservaResource($this->reservaDestino))->resolve();
            }),
        ];
    }
}