<?php

namespace App\Services;

use App\Models\Clase;
use App\Models\Ciclo;
use App\Models\Feriado;
use App\Models\Inscripcion;
use App\Models\Reserva;
use App\Models\Yoguini;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InscripcionService
{
    public function enroll(Yoguini $yoguini, Ciclo $ciclo, array $scheduleIds)
    {
        return DB::transaction(function () use ($yoguini, $ciclo, $scheduleIds) {
            $ciclo = Ciclo::whereKey($ciclo->id)->lockForUpdate()->firstOrFail();
            $scheduleIds = array_values(array_unique(array_map('intval', $scheduleIds)));
            if (!$ciclo->activo || $ciclo->fecha_fin->lt($this->today())) {
                $this->fail('ciclo_id', 'This cycle is not open for enrollment.');
            }
            if (count($scheduleIds) !== (int) $ciclo->clases_por_semana) {
                $this->fail('horario_ids', 'Select exactly the number of weekly classes required by this cycle.');
            }
            if (Inscripcion::where('user_id', $yoguini->id)->where('ciclo_id', $ciclo->id)->exists()) {
                $this->fail('ciclo_id', 'You are already enrolled in this cycle.');
            }

            $schedules = DB::table('horarios')
                ->whereIn('id', $scheduleIds)
                ->where('activo', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($schedules->count() !== count($scheduleIds)) {
                $this->fail('horario_ids', 'Every selected schedule must exist and be active.');
            }

            $now = Carbon::now(config('app.timezone'));
            $futureClasses = Clase::where('ciclo_id', $ciclo->id)
                ->whereIn('horario_id', $scheduleIds)
                ->where('estado', 'programada')
                ->where(function ($query) use ($now) {
                    $query->whereDate('fecha', '>', $now->toDateString())
                        ->orWhere(function ($today) use ($now) {
                            $today->whereDate('fecha', $now->toDateString())
                                ->where('hora_inicio', '>', $now->format('H:i:s'));
                        });
                })
                ->orderBy('id')
                ->get();
            foreach ($scheduleIds as $scheduleId) {
                if (!$futureClasses->contains('horario_id', $scheduleId)) {
                    $this->fail('horario_ids', "Schedule {$scheduleId} has no generated future classes in this cycle.");
                }
            }

            $lockedClasses = Clase::whereIn('id', $futureClasses->pluck('id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if (Inscripcion::where('user_id', $yoguini->id)->where('ciclo_id', $ciclo->id)->exists()) {
                $this->fail('ciclo_id', 'You are already enrolled in this cycle.');
            }

            $expectedClasses = $this->futureScheduleDates($ciclo, $schedules, $now);
            $generatedKeys = $lockedClasses->map(function ($class) {
                return $class->horario_id.':'.$class->fecha->toDateString();
            })->flip();
            $missingClasses = array_values(array_filter($expectedClasses, function ($key) use ($generatedKeys) {
                return !$generatedKeys->has($key);
            }));
            if ($missingClasses) {
                $this->fail('clases', 'Some future classes have not been generated: '.implode(', ', $missingClasses).'.');
            }

            $insufficient = [];
            foreach ($lockedClasses as $class) {
                $reserved = Reserva::where('clase_id', $class->id)
                    ->whereIn('estado', ['reservada', 'asistio'])
                    ->count();
                if ($reserved >= $class->cupo) {
                    $insufficient[] = [
                        'clase_id' => $class->id,
                        'fecha' => $class->fecha->toDateString(),
                        'hora_inicio' => $class->hora_inicio,
                        'cupo' => $class->cupo,
                        'disponibles' => max(0, $class->cupo - $reserved),
                    ];
                }
                if (Reserva::where('user_id', $yoguini->id)->where('clase_id', $class->id)->exists()) {
                    $this->fail('horario_ids', "A reservation already exists for class {$class->id}.");
                }
            }
            if ($insufficient) {
                $messages = array_map(function ($class) {
                    return "Class {$class['clase_id']} on {$class['fecha']} at {$class['hora_inicio']} is full ({$class['disponibles']} seats available).";
                }, $insufficient);
                throw ValidationException::withMessages([
                    'clases_sin_cupo' => ['One or more classes do not have enough seats.'],
                    'clases' => $messages,
                ]);
            }

            $enrollment = Inscripcion::create([
                'user_id' => $yoguini->id,
                'ciclo_id' => $ciclo->id,
                'estado' => 'activa',
                'precio' => $ciclo->precio,
            ]);
            $enrollment->horarios()->sync($scheduleIds);
            foreach ($lockedClasses as $class) {
                Reserva::create([
                    'inscripcion_id' => $enrollment->id,
                    'user_id' => $yoguini->id,
                    'clase_id' => $class->id,
                    'tipo' => 'regular',
                    'estado' => 'reservada',
                ]);
            }

            return $enrollment->load(['ciclo', 'horarios', 'reservas.clase']);
        });
    }

    private function futureScheduleDates(Ciclo $ciclo, $schedules, Carbon $now)
    {
        $timezone = config('app.timezone');
        $start = Carbon::parse($ciclo->fecha_inicio->toDateString(), $timezone)->startOfDay();
        $end = Carbon::parse($ciclo->fecha_fin->toDateString(), $timezone)->startOfDay();
        $holidayDates = array_flip(Feriado::whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
            ->pluck('fecha')
            ->map(function ($date) {
                return substr((string) $date, 0, 10);
            })
            ->all());
        $expected = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateString = $date->toDateString();
            if (isset($holidayDates[$dateString])) {
                continue;
            }

            foreach ($schedules->where('dia_semana', $date->dayOfWeek) as $schedule) {
                $startsAt = Carbon::parse($dateString.' '.$schedule->hora_inicio, $timezone);
                if ($startsAt->gt($now)) {
                    $expected[] = $schedule->id.':'.$dateString;
                }
            }
        }

        return $expected;
    }

    private function today()
    {
        return Carbon::now(config('app.timezone'))->startOfDay();
    }

    private function fail($key, $message, $status = 422)
    {
        if ($status !== 422) {
            abort($status, $message);
        }
        throw ValidationException::withMessages([$key => [$message]]);
    }
}