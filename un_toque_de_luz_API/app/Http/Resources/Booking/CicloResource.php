<?php

namespace App\Http\Resources\Booking;

use Illuminate\Http\Resources\Json\JsonResource;

class CicloResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'fecha_inicio' => $this->fecha_inicio->toDateString(),
            'fecha_fin' => $this->fecha_fin->toDateString(),
            'clases_por_semana' => $this->clases_por_semana,
            'precio' => $this->precio,
            'activo' => $this->activo,
            'clases_count' => $this->when(isset($this->clases_count), $this->clases_count),
            'inscripciones_count' => $this->when(isset($this->inscripciones_count), $this->inscripciones_count),
            'clases' => ClaseResource::collection($this->whenLoaded('clases')),
        ];
    }
}