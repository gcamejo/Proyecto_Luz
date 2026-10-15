<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\Admin\ClassIndexRequest;
use App\Http\Requests\Booking\Admin\CycleRequest;
use App\Http\Requests\Booking\Admin\MarkAttendanceRequest;
use App\Http\Requests\Booking\Admin\ScheduleRequest;
use App\Http\Requests\Booking\Admin\StoreHolidayRequest;
use App\Http\Requests\Booking\Admin\UpdateHolidayRequest;
use App\Http\Resources\Booking\ClaseResource;
use App\Http\Resources\Booking\ReservaResource;
use App\Models\Clase;
use App\Models\Ciclo;
use App\Models\Feriado;
use App\Models\Horario;
use App\Models\Reserva;
use App\Services\CicloService;
use App\Services\ReservaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookingAdminController extends Controller
{
    public function schedules()
    {
        return Horario::orderBy('dia_semana')->orderBy('hora_inicio')->get();
    }

    public function createSchedule(ScheduleRequest $request)
    {
        return response()->json(Horario::create($this->validatedSchedule($request)), 201);
    }

    public function showSchedule(Horario $horario)
    {
        return $horario;
    }

    public function updateSchedule(ScheduleRequest $request, Horario $horario)
    {
        $horario->update($this->validatedSchedule($request));
        return $horario->fresh();
    }

    public function deleteSchedule(Horario $horario)
    {
        $horario->activo = false;
        $horario->save();
        return response()->json(['message' => 'Schedule deactivated.']);
    }

    public function cycles(CicloService $ciclos)
    {
        return $ciclos->all();
    }

    public function createCycle(CycleRequest $request, CicloService $ciclos)
    {
        return response()->json($ciclos->create($request->validated()), 201);
    }

    public function showCycle(Ciclo $ciclo, CicloService $ciclos)
    {
        return $ciclos->show($ciclo);
    }

    public function updateCycle(CycleRequest $request, Ciclo $ciclo, CicloService $ciclos)
    {
        return $ciclos->update($ciclo, $request->validated());
    }

    public function deleteCycle(Ciclo $ciclo, CicloService $ciclos)
    {
        if (!$ciclos->delete($ciclo)) {
            return response()->json(['message' => 'Pause the cycle and cancel all programmed classes before removing it from administration.'], 409);
        }
        return response()->json(null, 204);
    }

    public function generateClasses(Ciclo $ciclo, CicloService $ciclos)
    {
        return response()->json($ciclos->generateClasses($ciclo));
    }

    public function classes(ClassIndexRequest $request)
    {
        $filters = $request->validated();

        $classes = Clase::with('ciclo', 'horario', 'reservas.yoguini')
            ->when(isset($filters['fecha']), function ($query) use ($filters) {
                $query->whereDate('fecha', $filters['fecha']);
            })
            ->when(isset($filters['ciclo_id']), function ($query) use ($filters) {
                $query->where('ciclo_id', $filters['ciclo_id']);
            })
            ->withCount(['reservas as ocupados' => function ($query) {
                $query->whereIn('estado', ['reservada', 'asistio']);
            }])
            ->orderBy('fecha')->orderBy('hora_inicio')->get()
            ->each(function ($class) {
                $class->setAttribute('disponibles', max(0, $class->cupo - $class->ocupados));
                $endsAt = Carbon::parse($class->fecha->toDateString().' '.$class->hora_inicio, config('app.timezone'))
                    ->addMinutes($class->duracion_min);
                $class->setAttribute('puede_completarse', $class->estado === 'programada' && !$endsAt->isFuture());
            });

        return response()->json(ClaseResource::collection($classes)->resolve());
    }

    public function classReservations(Clase $clase)
    {
        return response()->json(ReservaResource::collection($clase->reservas()->with('yoguini')->orderBy('id')->get())->resolve());
    }

    public function cancelClass(Clase $clase, ReservaService $reservas)
    {
        return $reservas->cancelClass($clase);
    }

    public function completeClass(Clase $clase, ReservaService $reservas)
    {
        return $reservas->completeClass($clase);
    }

    public function markAttendance(MarkAttendanceRequest $request, Reserva $reserva, ReservaService $reservas)
    {
        $validated = $request->validated();
        return $reservas->markAttendance($reserva, $validated['estado']);
    }

    public function holidays()
    {
        return Feriado::orderBy('fecha')->get();
    }

    public function createHoliday(StoreHolidayRequest $request)
    {
        $validated = $request->validated();
        if (Clase::whereDate('fecha', $validated['fecha'])->exists()) {
            throw ValidationException::withMessages([
                'fecha' => ['Classes already exist on this date. Adding a holiday will not change generated classes.'],
            ]);
        }
        return response()->json(Feriado::create($validated), 201);
    }

    public function updateHoliday(UpdateHolidayRequest $request, Feriado $feriado)
    {
        $validated = $request->validated();
        $date = $validated['fecha'] ?? $feriado->fecha->toDateString();
        if ($date !== $feriado->fecha->toDateString() && Clase::whereDate('fecha', $date)->exists()) {
            throw ValidationException::withMessages([
                'fecha' => ['Classes already exist on this date. Updating the holiday will not change generated classes.'],
            ]);
        }

        $feriado->update($validated);
        return $feriado->fresh();
    }

    public function deleteHoliday(Feriado $feriado)
    {
        $feriado->delete();
        return response()->json(null, 204);
    }

    private function validatedSchedule(ScheduleRequest $request)
    {
        $validated = $request->validated();
        if (isset($validated['hora_inicio']) && strlen($validated['hora_inicio']) === 5) {
            $validated['hora_inicio'] .= ':00';
        }
        return $validated;
    }
}