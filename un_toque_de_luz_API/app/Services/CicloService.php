<?php

namespace App\Services;

use App\Models\Ciclo;
use App\Models\Horario;
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
        return Ciclo::with('horarios')->withCount([
            'clases',
            'inscripciones',
            'clases as clases_programadas_count' => function ($query) {
                $query->where('estado', 'programada');
            },
        ])->orderByDesc('fecha_inicio')->get();
    }

    public function create(array $attributes)
    {
        return DB::transaction(function () use ($attributes) {
            $scheduleIds = $this->lockActiveSchedules($attributes['horario_ids']);
            $this->validateScheduleCount($scheduleIds, $attributes['clases_por_semana']);
            unset($attributes['horario_ids']);

            $ciclo = Ciclo::create($attributes);
            $ciclo->horarios()->sync($scheduleIds);

            return $ciclo->load('horarios');
        });
    }

    public function show(Ciclo $ciclo)
    {
        return $ciclo->load('horarios')->loadCount(['clases', 'inscripciones']);
    }

    public function update(Ciclo $ciclo, array $attributes)
    {
        return DB::transaction(function () use ($ciclo, $attributes) {
            $ciclo = Ciclo::whereKey($ciclo->id)->lockForUpdate()->firstOrFail();
            $start = Carbon::parse($attributes['fecha_inicio'] ?? $ciclo->fecha_inicio->toDateString());
            $end = Carbon::parse($attributes['fecha_fin'] ?? $ciclo->fecha_fin->toDateString());
            $currentScheduleIds = $ciclo->horarios()->orderBy('horarios.id')->pluck('horarios.id')->map(function ($id) {
                return (int) $id;
            })->all();
            $scheduleIds = array_key_exists('horario_ids', $attributes)
                ? $this->lockActiveSchedules($attributes['horario_ids'])
                : $currentScheduleIds;
            $weeklyClasses = (int) ($attributes['clases_por_semana'] ?? $ciclo->clases_por_semana);

            if ((array_key_exists('horario_ids', $attributes) || array_key_exists('clases_por_semana', $attributes))
                && count($scheduleIds) !== $weeklyClasses) {
                $this->fail('horario_ids', 'Select exactly the number of weekly classes required by this cycle.');
            }
            if ($end->lt($start)) {
                throw ValidationException::withMessages([
                    'fecha_fin' => ['The cycle end date must be on or after its start date.'],
                ]);
            }

            if ($ciclo->clases()->exists()) {
                if ($scheduleIds !== $currentScheduleIds) {
                    $this->fail('horario_ids', 'Cycle schedules cannot change after classes have been generated.');
                }
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

            unset($attributes['horario_ids']);
            $ciclo->update($attributes);
            if (array_key_exists('horario_ids', $attributes) || $scheduleIds !== $currentScheduleIds) {
                $ciclo->horarios()->sync($scheduleIds);
            }

            return $ciclo->fresh()->load('horarios')->loadCount(['clases', 'inscripciones']);
        });
    }

    public function delete(Ciclo $ciclo)
    {
        return DB::transaction(function () use ($ciclo) {
            $ciclo = Ciclo::whereKey($ciclo->id)->lockForUpdate()->firstOrFail();
            if ($ciclo->activo || $ciclo->clases()->where('estado', 'programada')->exists()) {
                return false;
            }

            $ciclo->delete();
            return true;
        });
    }

    public function generateClasses(Ciclo $ciclo)
    {
        return $this->generator->generate($ciclo);
    }

    private function lockActiveSchedules(array $scheduleIds)
    {
        $scheduleIds = array_values(array_unique(array_map('intval', $scheduleIds)));
        sort($scheduleIds);

        $activeIds = Horario::whereIn('id', $scheduleIds)
            ->where('activo', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        if ($activeIds !== $scheduleIds) {
            $this->fail('horario_ids', 'Every selected schedule must exist and be active.');
        }

        return $scheduleIds;
    }

    private function validateScheduleCount(array $scheduleIds, $weeklyClasses)
    {
        if (count($scheduleIds) !== (int) $weeklyClasses) {
            $this->fail('horario_ids', 'Select exactly the number of weekly classes required by this cycle.');
        }
    }

    private function fail($key, $message)
    {
        throw ValidationException::withMessages([$key => [$message]]);
    }
}