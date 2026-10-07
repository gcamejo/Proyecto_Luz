<?php

namespace App\Services;

use App\Models\Ciclo;
use App\Services\GenerateCycleClasses;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CicloService
{
    private $generator;

    public function __construct(GenerateCycleClasses $generator)
    {
        $this->generator = $generator;
    }

    public function all()
    {
        return Ciclo::withCount(['clases', 'inscripciones'])->orderByDesc('fecha_inicio')->get();
    }

    public function create(array $attributes)
    {
        return Ciclo::create($attributes);
    }

    public function show(Ciclo $ciclo)
    {
        return $ciclo->loadCount(['clases', 'inscripciones']);
    }

    public function update(Ciclo $ciclo, array $attributes)
    {
        return DB::transaction(function () use ($ciclo, $attributes) {
            $ciclo = Ciclo::whereKey($ciclo->id)->lockForUpdate()->firstOrFail();
            $start = Carbon::parse($attributes['fecha_inicio'] ?? $ciclo->fecha_inicio->toDateString());
            $end = Carbon::parse($attributes['fecha_fin'] ?? $ciclo->fecha_fin->toDateString());
            if ($end->lt($start)) {
                throw ValidationException::withMessages([
                    'fecha_fin' => ['The cycle end date must be on or after its start date.'],
                ]);
            }

            if ($ciclo->clases()->exists()) {
                foreach (['fecha_inicio', 'fecha_fin', 'clases_por_semana'] as $field) {
                    $currentValue = in_array($field, ['fecha_inicio', 'fecha_fin'], true)
                        ? $ciclo->{$field}->toDateString()
                        : (string) $ciclo->{$field};
                    if (array_key_exists($field, $attributes) && (string) $attributes[$field] !== $currentValue) {
                        throw ValidationException::withMessages([
                            $field => ['Cycle dates and weekly class count cannot change after classes have been generated.'],
                        ]);
                    }
                }
            }

            $ciclo->update($attributes);
            return $ciclo->fresh()->loadCount(['clases', 'inscripciones']);
        });
    }

    public function delete(Ciclo $ciclo)
    {
        if ($ciclo->clases()->exists() || $ciclo->inscripciones()->exists()) {
            return false;
        }

        $ciclo->delete();
        return true;
    }

    public function generateClasses(Ciclo $ciclo)
    {
        return $this->generator->generate($ciclo);
    }
}