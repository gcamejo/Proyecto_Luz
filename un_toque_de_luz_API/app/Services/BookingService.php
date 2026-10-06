<?php

namespace App\Services;

use App\Models\Clase;
use App\Models\Ciclo;
use App\Models\Configuracion;
use App\Models\Feriado;
use App\Models\Inscripcion;
use App\Models\Recuperacion;
use App\Models\Reserva;
use App\Models\Yoguini;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
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

    public function cancelReservation(Yoguini $yoguini, Reserva $reserva)
    {
        return DB::transaction(function () use ($yoguini, $reserva) {
            $class = Clase::whereKey($reserva->clase_id)->lockForUpdate()->firstOrFail();
            $reservation = Reserva::whereKey($reserva->id)->lockForUpdate()->firstOrFail();
            if ((int) $reservation->user_id !== (int) $yoguini->id) {
                $this->fail('reserva', 'This reservation does not belong to the authenticated user.', 403);
            }
            if ($reservation->estado !== 'reservada' || $class->estado !== 'programada') {
                $this->fail('reserva', 'Only active reservations for programmed classes can be cancelled.');
            }

            $now = Carbon::now(config('app.timezone'));
            $startsAt = $this->classStart($class);
            $noticeHours = (int) (Configuracion::where('clave', 'horas_aviso_minimas')->value('valor') ?? 24);
            $withNotice = $startsAt->greaterThanOrEqualTo($now->copy()->addHours($noticeHours));
            $reservation->estado = $withNotice ? 'cancelada_con_aviso' : 'cancelada';
            $reservation->save();

            if ($withNotice) {
                if ($reservation->tipo === 'recuperacion') {
                    $credit = Recuperacion::where('reserva_destino_id', $reservation->id)
                        ->lockForUpdate()
                        ->first();
                    if ($credit && $credit->estado === 'usada' && !$credit->vence_en->lt($this->today())) {
                        $credit->estado = 'disponible';
                        $credit->reserva_destino_id = null;
                        $credit->save();
                    }
                } else {
                    Recuperacion::firstOrCreate(
                        ['reserva_origen_id' => $reservation->id],
                        [
                            'user_id' => $yoguini->id,
                            'inscripcion_id' => $reservation->inscripcion_id,
                            'vence_en' => $now->copy()->endOfMonth()->toDateString(),
                            'estado' => 'disponible',
                        ]
                    );
                }
            }

            return $reservation->fresh(['clase', 'recuperacionGenerada']);
        });
    }

    public function recover(Yoguini $yoguini, Recuperacion $credit, Clase $class)
    {
        return DB::transaction(function () use ($yoguini, $credit, $class) {
            $class = Clase::whereKey($class->id)->lockForUpdate()->firstOrFail();
            $credit = Recuperacion::whereKey($credit->id)->lockForUpdate()->firstOrFail();
            $today = $this->today();
            $now = Carbon::now(config('app.timezone'));
            $startsAt = $this->classStart($class);
            $classDate = Carbon::parse($class->fecha->toDateString(), config('app.timezone'));
            $expiry = Carbon::parse($credit->vence_en->toDateString(), config('app.timezone'));

            if ((int) $credit->user_id !== (int) $yoguini->id) {
                $this->fail('recuperacion', 'This credit does not belong to the authenticated user.', 403);
            }
            if ($credit->estado !== 'disponible' || $credit->vence_en->lt($today)) {
                $this->fail('recuperacion', 'This recovery credit is not available or has expired.');
            }
            if ($class->estado !== 'programada' || !$startsAt->gt($now)) {
                $this->fail('clase_id', 'The destination class must be programmed and in the future.');
            }
            if (!$classDate->gte($expiry->copy()->startOfMonth()) || !$classDate->lte($expiry)) {
                $this->fail('clase_id', 'The destination class must be within the credit expiry month.');
            }

            $reserved = Reserva::where('clase_id', $class->id)
                ->whereIn('estado', ['reservada', 'asistio'])
                ->count();
            if ($reserved >= $class->cupo) {
                $this->fail('clase_id', 'The destination class has no available seats.');
            }
            if (Reserva::where('user_id', $yoguini->id)->where('clase_id', $class->id)->exists()) {
                $this->fail('clase_id', 'A reservation already exists for this class.');
            }

            $reservation = Reserva::create([
                'inscripcion_id' => $credit->inscripcion_id,
                'user_id' => $yoguini->id,
                'clase_id' => $class->id,
                'tipo' => 'recuperacion',
                'estado' => 'reservada',
            ]);
            $credit->estado = 'usada';
            $credit->reserva_destino_id = $reservation->id;
            $credit->save();

            return $reservation->load(['clase', 'inscripcion.ciclo']);
        });
    }

    public function cancelClass(Clase $class)
    {
        return DB::transaction(function () use ($class) {
            $class = Clase::whereKey($class->id)->lockForUpdate()->firstOrFail();
            if ($class->estado === 'cancelada') {
                return $class->load('reservas');
            }
            if ($class->estado !== 'programada') {
                $this->fail('clase', 'Only programmed classes can be cancelled.');
            }

            $class->estado = 'cancelada';
            $class->save();
            $reservations = Reserva::where('clase_id', $class->id)
                ->whereIn('estado', ['reservada', 'asistio'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $expiry = Carbon::now(config('app.timezone'))->endOfMonth()->toDateString();

            foreach ($reservations as $reservation) {
                $reservation->estado = 'cancelada_con_aviso';
                $reservation->save();
                if ($reservation->tipo === 'recuperacion') {
                    $credit = Recuperacion::where('reserva_destino_id', $reservation->id)
                        ->lockForUpdate()
                        ->first();
                    if ($credit && $credit->estado === 'usada' && !$credit->vence_en->lt($this->today())) {
                        $credit->estado = 'disponible';
                        $credit->reserva_destino_id = null;
                        $credit->save();
                    }
                    continue;
                }

                Recuperacion::firstOrCreate(
                    ['reserva_origen_id' => $reservation->id],
                    [
                        'user_id' => $reservation->user_id,
                        'inscripcion_id' => $reservation->inscripcion_id,
                        'vence_en' => $expiry,
                        'estado' => 'disponible',
                    ]
                );
            }

            return $class->fresh(['reservas', 'reservas.recuperacionGenerada']);
        });
    }

    public function markAttendance(Reserva $reserva, $state)
    {
        if (!in_array($state, ['asistio', 'falto'], true)) {
            $this->fail('estado', 'Attendance state must be asistio or falto.');
        }

        return DB::transaction(function () use ($reserva, $state) {
            $class = Clase::whereKey($reserva->clase_id)->lockForUpdate()->firstOrFail();
            $reservation = Reserva::whereKey($reserva->id)->lockForUpdate()->firstOrFail();
            if ($class->estado !== 'realizada') {
                $this->fail('clase', 'Attendance can only be marked after the class is completed.');
            }
            if ($reservation->estado !== 'reservada') {
                $this->fail('reserva', 'Only reserved bookings can be marked for attendance.');
            }
            $reservation->estado = $state;
            $reservation->save();
            return $reservation->fresh(['clase', 'yoguini']);
        });
    }

    public function completeClass(Clase $class)
    {
        return DB::transaction(function () use ($class) {
            $class = Clase::whereKey($class->id)->lockForUpdate()->firstOrFail();
            if ($class->estado !== 'programada') {
                $this->fail('clase', 'Only programmed classes can be marked as completed.');
            }

            $endsAt = $this->classStart($class)->addMinutes($class->duracion_min);
            if ($endsAt->isFuture()) {
                $this->fail('clase', 'A class cannot be completed before its scheduled end time.');
            }

            $class->estado = 'realizada';
            $class->save();

            return $class->fresh(['reservas']);
        });
    }

    private function classStart(Clase $class)
    {
        return Carbon::parse($class->fecha->toDateString().' '.$class->hora_inicio, config('app.timezone'));
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