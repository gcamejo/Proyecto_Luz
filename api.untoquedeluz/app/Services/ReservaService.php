<?php

namespace App\Services;

use App\Models\Clase;
use App\Models\Configuracion;
use App\Models\Inscripcion;
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
            $noticeHours = (int) (Configuracion::where('clave', 'horas_aviso_minimas')->value('valor') ?? 24);
            $this->cancelLockedReservation($yoguini, $reservation, $class, $now, $noticeHours);

            return $reservation->fresh(['clase', 'recuperacionGenerada']);
        });
    }

    public function cancelEnrollment(Yoguini $yoguini, Inscripcion $inscripcion)
    {
        return DB::transaction(function () use ($yoguini, $inscripcion) {
            $inscripcion = Inscripcion::whereKey($inscripcion->id)->lockForUpdate()->firstOrFail();
            if ((int) $inscripcion->user_id !== (int) $yoguini->id) {
                $this->fail('inscripcion', 'This enrollment does not belong to the authenticated user.', 403);
            }
            if ($inscripcion->estado !== 'activa') {
                $this->fail('inscripcion', 'Only active enrollments can be cancelled.');
            }

            $now = Carbon::now(config('app.timezone'));
            $noticeHours = (int) (Configuracion::where('clave', 'horas_aviso_minimas')->value('valor') ?? 24);
            $classes = Clase::where('ciclo_id', $inscripcion->ciclo_id)
                ->where('estado', 'programada')
                ->where(function ($query) use ($now) {
                    $query->whereDate('fecha', '>', $now->toDateString())
                        ->orWhere(function ($today) use ($now) {
                            $today->whereDate('fecha', $now->toDateString())
                                ->where('hora_inicio', '>', $now->format('H:i:s'));
                        });
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $reservations = Reserva::where('inscripcion_id', $inscripcion->id)
                ->whereIn('clase_id', $classes->pluck('id'))
                ->where('estado', 'reservada')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $classesById = $classes->keyBy('id');
            $creditsGenerated = 0;
            $creditsReturned = 0;
            $withoutCredit = 0;
            $creditsByExpiry = [];

            foreach ($reservations as $reservation) {
                $result = $this->cancelLockedReservation(
                    $yoguini,
                    $reservation,
                    $classesById->get($reservation->clase_id),
                    $now,
                    $noticeHours
                );
                $creditsGenerated += $result['credit_generated'] ? 1 : 0;
                $creditsReturned += $result['credit_returned'] ? 1 : 0;
                $withoutCredit += $result['with_notice'] ? 0 : 1;
                if (($result['credit_generated'] || $result['credit_returned']) && $result['credit_expiry']) {
                    $expiry = $result['credit_expiry'];
                    if (!isset($creditsByExpiry[$expiry])) {
                        $creditsByExpiry[$expiry] = [
                            'vence_en' => $expiry,
                            'generados' => 0,
                            'devueltos' => 0,
                        ];
                    }
                    $creditsByExpiry[$expiry]['generados'] += $result['credit_generated'] ? 1 : 0;
                    $creditsByExpiry[$expiry]['devueltos'] += $result['credit_returned'] ? 1 : 0;
                }
            }
            ksort($creditsByExpiry);

            $inscripcion->estado = 'cancelada';
            $inscripcion->save();

            return [
                'inscripcion' => $inscripcion->fresh('ciclo'),
                'reservas_canceladas' => $reservations->count(),
                'creditos_generados' => $creditsGenerated,
                'creditos_devueltos' => $creditsReturned,
                'reservas_sin_credito' => $withoutCredit,
                'creditos_por_mes' => array_values($creditsByExpiry),
            ];
        });
    }

    private function cancelLockedReservation(Yoguini $yoguini, Reserva $reservation, Clase $class, Carbon $now, $noticeHours)
    {
        $withNotice = $this->classStart($class)->greaterThanOrEqualTo($now->copy()->addHours($noticeHours));
        $reservation->estado = $withNotice ? 'cancelada_con_aviso' : 'cancelada';
        $reservation->save();
        $creditGenerated = false;
        $creditReturned = false;
        $creditExpiry = null;

        if ($withNotice && $reservation->tipo === 'recuperacion') {
            $credit = Recuperacion::where('reserva_destino_id', $reservation->id)
                ->lockForUpdate()
                ->first();
            if ($credit && $credit->estado === 'usada' && !$credit->vence_en->lt($this->today())) {
                $credit->estado = 'disponible';
                $credit->reserva_destino_id = null;
                $credit->save();
                $creditReturned = true;
                $creditExpiry = $credit->vence_en->toDateString();
            }
        } elseif ($withNotice) {
            $expiresAt = $this->classStart($class)->endOfMonth()->toDateString();
            $credit = Recuperacion::firstOrCreate(
                ['reserva_origen_id' => $reservation->id],
                [
                    'user_id' => $yoguini->id,
                    'inscripcion_id' => $reservation->inscripcion_id,
                    'vence_en' => $expiresAt,
                    'estado' => 'disponible',
                ]
            );
            $creditGenerated = $credit->wasRecentlyCreated;
            if ($creditGenerated) {
                $creditExpiry = $expiresAt;
            }
        }

        return [
            'with_notice' => $withNotice,
            'credit_generated' => $creditGenerated,
            'credit_returned' => $creditReturned,
            'credit_expiry' => $creditExpiry,
        ];
    }

    public function cancelClass(Clase $class)
    {
        return DB::transaction(function () use ($class) {
            $class = Clase::whereKey($class->id)->lockForUpdate()->firstOrFail();
            $now = Carbon::now(config('app.timezone'));
            if (!$this->classStart($class)->gt($now)) {
                $this->fail('clase', 'A class cannot be cancelled after its scheduled start time.');
            }
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

                $expiry = $this->classStart($class)->endOfMonth()->toDateString();
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