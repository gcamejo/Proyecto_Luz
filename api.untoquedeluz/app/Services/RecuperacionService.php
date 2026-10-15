<?php

namespace App\Services;

use App\Models\Clase;
use App\Models\Recuperacion;
use App\Models\Reserva;
use App\Models\Yoguini;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecuperacionService
{
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