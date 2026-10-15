<?php

namespace App\Services;

use App\Models\Ciclo;
use App\Models\Clase;
use App\Models\Feriado;
use App\Models\Horario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GenerateCycleClasses
{
    public function generate(Ciclo $ciclo)
    {
        return DB::transaction(function () use ($ciclo) {
            $ciclo = Ciclo::whereKey($ciclo->id)->lockForUpdate()->firstOrFail();
            if ($ciclo->fecha_inicio->gt($ciclo->fecha_fin)) {
                throw ValidationException::withMessages([
                    'fecha_fin' => ['The cycle end date must be on or after its start date.'],
                ]);
            }

            $timezone = config('app.timezone');
            $start = Carbon::parse($ciclo->fecha_inicio->toDateString(), $timezone)->startOfDay();
            $end = Carbon::parse($ciclo->fecha_fin->toDateString(), $timezone)->startOfDay();
            $holidays = Feriado::whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
                ->pluck('fecha')
                ->map(function ($date) {
                    return substr((string) $date, 0, 10);
                })
                ->flip();
            $scheduleIds = $ciclo->horarios()->orderBy('horarios.id')->pluck('horarios.id');
            if ($scheduleIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'horario_ids' => ['Select the schedules for this cycle before generating classes.'],
                ]);
            }

            $schedules = Horario::whereIn('id', $scheduleIds)
                ->where('activo', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($schedules->count() !== $scheduleIds->count()) {
                throw ValidationException::withMessages([
                    'horario_ids' => ['All selected schedules must be active before generating classes.'],
                ]);
            }
            $schedules = $schedules->groupBy('dia_semana');
            $created = 0;
            $skipped = 0;

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $dateString = $date->toDateString();
                if ($holidays->has($dateString)) {
                    continue;
                }

                foreach ($schedules->get($date->dayOfWeek, collect()) as $schedule) {
                    $existingInCycle = Clase::where('ciclo_id', $ciclo->id)
                        ->where('horario_id', $schedule->id)
                        ->whereDate('fecha', $dateString)
                        ->lockForUpdate()
                        ->first();

                    if ($existingInCycle) {
                        $skipped++;
                        continue;
                    }

                    $activeConflict = Clase::where('horario_id', $schedule->id)
                        ->whereDate('fecha', $dateString)
                        ->where('estado', '!=', 'cancelada')
                        ->lockForUpdate()
                        ->first();

                    if ($activeConflict) {
                        throw ValidationException::withMessages([
                            'horarios' => ["Schedule {$schedule->id} on {$dateString} already belongs to another cycle."],
                        ]);
                    }

                    Clase::create([
                        'ciclo_id' => $ciclo->id,
                        'horario_id' => $schedule->id,
                        'fecha' => $dateString,
                        'hora_inicio' => $schedule->hora_inicio,
                        'duracion_min' => $schedule->duracion_min,
                        'nivel' => $schedule->nivel,
                        'profesor' => $schedule->profesor,
                        'cupo' => $schedule->cupo,
                        'estado' => 'programada',
                    ]);
                    $created++;
                }
            }

            return ['created' => $created, 'skipped' => $skipped];
        });
    }
}