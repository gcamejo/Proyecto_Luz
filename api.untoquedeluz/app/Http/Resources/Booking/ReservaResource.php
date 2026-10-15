<?php

namespace App\Http\Resources\Booking;

use Illuminate\Http\Resources\Json\JsonResource;

class ReservaResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'inscripcion_id' => $this->inscripcion_id,
            'user_id' => $this->user_id,
            'clase_id' => $this->clase_id,
            'tipo' => $this->tipo,
            'estado' => $this->estado,
            'yoguini' => $this->whenLoaded('yoguini', function () {
                return [
                    'id' => $this->yoguini->id,
                    'nombre' => $this->yoguini->nombre,
                    'apellido' => $this->yoguini->apellido,
                ];
            }),
            'clase' => $this->whenLoaded('clase', function () {
                return [
                    'id' => $this->clase->id,
                    'ciclo_id' => $this->clase->ciclo_id,
                    'fecha' => $this->clase->fecha->toDateString(),
                    'hora_inicio' => $this->clase->hora_inicio,
                    'estado' => $this->clase->estado,
                ];
            }),
            'puede_cancelar' => $this->when(isset($this->puede_cancelar), $this->puede_cancelar),
            'genera_credito' => $this->when(isset($this->genera_credito), $this->genera_credito),
        ];
    }
}