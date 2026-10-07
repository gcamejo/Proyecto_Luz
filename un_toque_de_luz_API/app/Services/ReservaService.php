<?php

namespace App\Services;

use App\Models\Clase;
use App\Models\Configuracion;
use App\Models\Recuperacion;
use App\Models\Reserva;
use App\Models\Yoguini;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservaService
{
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