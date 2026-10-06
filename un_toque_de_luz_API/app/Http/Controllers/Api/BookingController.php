<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clase;
use App\Models\Ciclo;
use App\Models\Recuperacion;
use App\Models\Reserva;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function activeCycles()
    {
        $today = Carbon::now(config('app.timezone'))->toDateString();
        return Ciclo::where('activo', true)
            ->whereDate('fecha_fin', '>=', $today)
            ->with(['clases' => function ($query) {
                $now = Carbon::now(config('app.timezone'));
                $query->where('estado', 'programada')
                    ->where(function ($future) use ($now) {
                        $future->whereDate('fecha', '>', $now->toDateString())
                            ->orWhere(function ($today) use ($now) {
                                $today->whereDate('fecha', $now->toDateString())
                                    ->where('hora_inicio', '>', $now->format('H:i:s'));
                            });
                    })
                    ->with('horario')
                    ->withCount(['reservas as ocupados' => function ($reservations) {
                    $reservations->whereIn('estado', ['reservada', 'asistio']);
                }])->orderBy('fecha')->orderBy('hora_inicio');
            }])
            ->orderBy('fecha_inicio')
            ->get()
            ->each(function ($cycle) {
                $cycle->clases->each(function ($class) {
                    $class->setAttribute('disponibles', max(0, $class->cupo - $class->ocupados));
                });
            });
    }

    public function enroll(Request $request, Ciclo $ciclo, BookingService $booking)
    {
        $validated = $request->validate([
            'horario_ids' => 'required|array|min:1',
            'horario_ids.*' => 'required|integer|distinct|exists:horarios,id',
        ]);
        $enrollment = $booking->enroll($request->user(), $ciclo, $validated['horario_ids']);
        return response()->json($enrollment, 201);
    }

    public function myBookings(Request $request)
    {
        $yoguini = $request->user();
        $now = Carbon::now(config('app.timezone'));
        $enrollments = $yoguini->inscripciones()
            ->with(['ciclo', 'horarios', 'reservas.clase.horario'])
            ->orderByDesc('created_at')->get()
            ->each(function ($enrollment) use ($now) {
                $enrollment->reservas->each(function ($reservation) use ($now) {
                    $class = $reservation->clase;
                    $reservation->setAttribute('puede_cancelar', $reservation->estado === 'reservada'
                        && $class
                        && $class->estado === 'programada'
                        && $this->classStartsInFuture($class, $now));
                });
            });

        return response()->json([
            'inscripciones' => $enrollments,
            'recuperaciones' => $yoguini->recuperaciones()
                ->with(['reservaOrigen.clase', 'reservaDestino.clase'])
                ->orderByDesc('created_at')->get(),
        ]);
    }

    public function cancelReservation(Request $request, Reserva $reserva, BookingService $booking)
    {
        $recoveryCredit = $reserva->tipo === 'recuperacion'
            ? Recuperacion::where('reserva_destino_id', $reserva->id)->first()
            : null;
        $cancelled = $booking->cancelReservation($request->user(), $reserva);

        return response()->json([
            'reserva' => $cancelled,
            'recuperacion_generada' => (bool) $cancelled->recuperacionGenerada,
            'credito_devuelto' => $recoveryCredit && $recoveryCredit->fresh()->estado === 'disponible',
        ]);
    }

    public function bookRecovery(Request $request, Recuperacion $recuperacion, BookingService $booking)
    {
        $validated = $request->validate(['clase_id' => 'required|integer|exists:clases,id']);
        $class = Clase::findOrFail($validated['clase_id']);
        return response()->json($booking->recover($request->user(), $recuperacion, $class), 201);
    }

    public function recoveryClasses(Request $request)
    {
        $now = Carbon::now(config('app.timezone'));
        return Clase::with(['ciclo', 'horario'])
            ->where('estado', 'programada')
            ->where(function ($future) use ($now) {
                $future->whereDate('fecha', '>', $now->toDateString())
                    ->orWhere(function ($today) use ($now) {
                        $today->whereDate('fecha', $now->toDateString())
                            ->where('hora_inicio', '>', $now->format('H:i:s'));
                    });
            })
            ->whereDoesntHave('reservas', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->withCount(['reservas as ocupados' => function ($reservations) {
                $reservations->whereIn('estado', ['reservada', 'asistio']);
            }])
            ->orderBy('fecha')->orderBy('hora_inicio')->get()
            ->each(function ($class) {
                $class->setAttribute('disponibles', max(0, $class->cupo - $class->ocupados));
            })
            ->filter(function ($class) {
                return $class->disponibles > 0;
            })->values();
    }

    private function classStartsInFuture(Clase $class, Carbon $now)
    {
        return Carbon::parse($class->fecha->toDateString().' '.$class->hora_inicio, config('app.timezone'))
            ->gt($now);
    }
}