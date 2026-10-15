<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\BookRecoveryRequest;
use App\Http\Requests\Booking\EnrollCycleRequest;
use App\Http\Resources\Booking\ClaseResource;
use App\Http\Resources\Booking\CicloResource;
use App\Http\Resources\Booking\RecuperacionResource;
use App\Http\Resources\Booking\ReservaResource;
use App\Models\Clase;
use App\Models\Ciclo;
use App\Models\Configuracion;
use App\Models\Inscripcion;
use App\Models\Recuperacion;
use App\Models\Reserva;
use App\Services\InscripcionService;
use App\Services\RecuperacionService;
use App\Services\ReservaService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function activeCycles()
    {
        $today = Carbon::now(config('app.timezone'))->toDateString();
        $cycles = Ciclo::where('activo', true)
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

        return response()->json(CicloResource::collection($cycles)->resolve());
    }

    public function enroll(EnrollCycleRequest $request, Ciclo $ciclo, InscripcionService $inscripciones)
    {
        $validated = $request->validated();
        $enrollment = $inscripciones->enroll($request->user(), $ciclo, $validated['horario_ids']);
        return response()->json($enrollment, 201);
    }

    public function myBookings(Request $request)
    {
        $yoguini = $request->user();
        $now = Carbon::now(config('app.timezone'));
        $noticeHours = (int) (Configuracion::where('clave', 'horas_aviso_minimas')->value('valor') ?? 24);
        $enrollments = $yoguini->inscripciones()
            ->with(['ciclo', 'horarios', 'reservas.clase.horario'])
            ->orderByDesc('created_at')->get()
            ->each(function ($enrollment) use ($now, $noticeHours) {
                $enrollment->reservas->each(function ($reservation) use ($now, $noticeHours) {
                    $class = $reservation->clase;
                    $isUpcoming = $reservation->estado === 'reservada'
                        && $class
                        && $class->estado === 'programada'
                        && $this->classStartsInFuture($class, $now);
                    $reservation->setAttribute('es_proxima', $isUpcoming);
                    $reservation->setAttribute('puede_cancelar', $reservation->estado === 'reservada'
                        && $class
                        && $class->estado === 'programada'
                        && $this->classStartsInFuture($class, $now));
                    $reservation->setAttribute('genera_credito', $reservation->estado === 'reservada'
                        && $class
                        && $class->estado === 'programada'
                        && $this->classStart($class)->greaterThanOrEqualTo($now->copy()->addHours($noticeHours)));
                });
            });

        return response()->json([
            'inscripciones' => $enrollments,
            'recuperaciones' => $yoguini->recuperaciones()
                ->with(['reservaOrigen.clase', 'reservaDestino.clase'])
                ->orderByDesc('created_at')->get(),
        ]);
    }

    public function myCredits(Request $request)
    {
        $credits = $request->user()->recuperaciones()
            ->with(['reservaOrigen.clase', 'reservaDestino.clase'])
            ->orderByDesc('created_at')->get();

        return response()->json(RecuperacionResource::collection($credits)->resolve());
    }

    public function cancelReservation(Request $request, Reserva $reserva, ReservaService $reservas)
    {
        $this->authorize('cancel', $reserva);
        $recoveryCredit = $reserva->tipo === 'recuperacion'
            ? Recuperacion::where('reserva_destino_id', $reserva->id)->first()
            : null;
        $cancelled = $reservas->cancelReservation($request->user(), $reserva);

        return response()->json([
            'reserva' => (new ReservaResource($cancelled))->resolve(),
            'recuperacion_generada' => (bool) $cancelled->recuperacionGenerada,
            'credito_devuelto' => $recoveryCredit && $recoveryCredit->fresh()->estado === 'disponible',
        ]);
    }

    public function cancelEnrollment(Request $request, Inscripcion $inscripcion, ReservaService $reservas)
    {
        $this->authorize('cancel', $inscripcion);

        return response()->json($reservas->cancelEnrollment($request->user(), $inscripcion));
    }

    public function bookRecovery(BookRecoveryRequest $request, Recuperacion $recuperacion, RecuperacionService $recuperaciones)
    {
        $this->authorize('book', $recuperacion);
        $validated = $request->validated();
        $class = Clase::findOrFail($validated['clase_id']);
        return response()->json($recuperaciones->recover($request->user(), $recuperacion, $class), 201);
    }

    public function recoveryClasses(Request $request)
    {
        $now = Carbon::now(config('app.timezone'));
        $classes = Clase::with(['ciclo', 'horario'])
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

        return response()->json(ClaseResource::collection($classes)->resolve());
    }

    private function classStartsInFuture(Clase $class, Carbon $now)
    {
        return $this->classStart($class)->gt($now);
    }

    private function classStart(Clase $class)
    {
        return Carbon::parse($class->fecha->toDateString().' '.$class->hora_inicio, config('app.timezone'));
    }
}