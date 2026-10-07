<?php

namespace App\Http\Resources\Booking;

use Illuminate\Http\Resources\Json\JsonResource;

class ClaseResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'ciclo_id' => $this->ciclo_id,
            'horario_id' => $this->horario_id,
            'fecha' => $this->fecha->toDateString(),
            'hora_inicio' => $this->hora_inicio,
            'duracion_min' => $this->duracion_min,
            'nivel' => $this->nivel,
            'profesor' => $this->profesor,
            'cupo' => $this->cupo,
            'estado' => $this->estado,
            'ciclo' => $this->whenLoaded('ciclo', function () {
                return [
                    'id' => $this->ciclo->id,
                    'nombre' => $this->ciclo->nombre,
                    'fecha_inicio' => $this->ciclo->fecha_inicio->toDateString(),
                    'fecha_fin' => $this->ciclo->fecha_fin->toDateString(),
                ];
            }),
            'horario' => $this->whenLoaded('horario', function () {
                return [
                    'id' => $this->horario->id,
                    'dia_semana' => $this->horario->dia_semana,
                    'hora_inicio' => $this->horario->hora_inicio,
                    'duracion_min' => $this->horario->duracion_min,
                    'nivel' => $this->horario->nivel,
                    'profesor' => $this->horario->profesor,
                    'cupo' => $this->horario->cupo,
                ];
            }),
            'reservas' => ReservaResource::collection($this->whenLoaded('reservas')),
            'ocupados' => $this->when(isset($this->ocupados), $this->ocupados),
            'disponibles' => $this->when(isset($this->disponibles), $this->disponibles),
            'puede_completarse' => $this->when(isset($this->puede_completarse), $this->puede_completarse),
        ];
    }
}