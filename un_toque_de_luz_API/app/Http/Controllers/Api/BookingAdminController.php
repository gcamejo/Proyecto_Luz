<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clase;
use App\Models\Ciclo;
use App\Models\Feriado;
use App\Models\Horario;
use App\Models\Reserva;
use App\Services\BookingService;
use App\Services\GenerateCycleClasses;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingAdminController extends Controller
{
    public function schedules()
    {
        return Horario::orderBy('dia_semana')->orderBy('hora_inicio')->get();
    }

    public function createSchedule(Request $request)
    {
        return response()->json(Horario::create($this->validateSchedule($request)), 201);
    }

    public function showSchedule(Horario $horario)
    {
        return $horario;
    }

    public function updateSchedule(Request $request, Horario $horario)
    {
        $horario->update($this->validateSchedule($request, true));
        return $horario->fresh();
    }

    public function deleteSchedule(Horario $horario)
    {
        $horario->activo = false;
        $horario->save();
        return response()->json(['message' => 'Schedule deactivated.']);
    }

    public function cycles()
    {
        return Ciclo::withCount(['clases', 'inscripciones'])->orderByDesc('fecha_inicio')->get();
    }

    public function createCycle(Request $request)
    {
        return response()->json(Ciclo::create($this->validateCycle($request)), 201);
    }

    public function showCycle(Ciclo $ciclo)
    {
        return $ciclo->loadCount(['clases', 'inscripciones']);
    }

    public function updateCycle(Request $request, Ciclo $ciclo)
    {
        return DB::transaction(function () use ($request, $ciclo) {
            $ciclo = Ciclo::whereKey($ciclo->id)->lockForUpdate()->firstOrFail();
            $validated = $this->validateCycle($request, true);
            $start = Carbon::parse($validated['fecha_inicio'] ?? $ciclo->fecha_inicio->toDateString());
            $end = Carbon::parse($validated['fecha_fin'] ?? $ciclo->fecha_fin->toDateString());
            if ($end->lt($start)) {
                throw ValidationException::withMessages([
                    'fecha_fin' => ['The cycle end date must be on or after its start date.'],
                ]);
            }
            if ($ciclo->clases()->exists()) {
                foreach (['fecha_inicio', 'fecha_fin', 'clases_por_semana'] as $field) {
                    $currentValue = in_array($field, ['fecha_inicio', 'fecha_fin'], true)
                        ? $ciclo->{$field}->toDateString()
                        : (string) $ciclo->{$field};
                    if (array_key_exists($field, $validated) && (string) $validated[$field] !== $currentValue) {
                        throw ValidationException::withMessages([
                            $field => ['Cycle dates and weekly class count cannot change after classes have been generated.'],
                        ]);
                    }
                }
            }
            $ciclo->update($validated);
            return $ciclo->fresh()->loadCount(['clases', 'inscripciones']);
        });
    }

    public function deleteCycle(Ciclo $ciclo)
    {
        if ($ciclo->clases()->exists() || $ciclo->inscripciones()->exists()) {
            return response()->json(['message' => 'A cycle with generated classes or enrollments cannot be deleted.'], 409);
        }
        $ciclo->delete();
        return response()->json(null, 204);
    }

    public function generateClasses(Ciclo $ciclo, GenerateCycleClasses $generator)
    {
        return response()->json($generator->generate($ciclo));
    }

    public function classes()
    {
        return Clase::with('ciclo', 'horario', 'reservas.yoguini')
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
    }

    public function cancelClass(Clase $clase, BookingService $booking)
    {
        return $booking->cancelClass($clase);
    }

    public function completeClass(Clase $clase, BookingService $booking)
    {
        return $booking->completeClass($clase);
    }

    public function markAttendance(Request $request, Reserva $reserva, BookingService $booking)
    {
        $validated = $request->validate(['estado' => 'required|in:asistio,falto']);
        return $booking->markAttendance($reserva, $validated['estado']);
    }

    public function holidays()
    {
        return Feriado::orderBy('fecha')->get();
    }

    public function createHoliday(Request $request)
    {
        $validated = $request->validate([
            'fecha' => 'required|date|unique:feriados,fecha',
            'descripcion' => 'nullable|string|max:255',
        ]);
        if (Clase::whereDate('fecha', $validated['fecha'])->exists()) {
            throw ValidationException::withMessages([
                'fecha' => ['Classes already exist on this date. Adding a holiday will not change generated classes.'],
            ]);
        }
        return response()->json(Feriado::create($validated), 201);
    }

    public function deleteHoliday(Feriado $feriado)
    {
        $feriado->delete();
        return response()->json(null, 204);
    }

    private function validateSchedule(Request $request, $partial = false)
    {
        $required = $partial ? 'sometimes' : 'required';
        $validated = $request->validate([
            'dia_semana' => $required.'|integer|between:0,6',
            'hora_inicio' => $required.'|date_format:H:i',
            'duracion_min' => $required.'|integer|min:1|max:1440',
            'nivel' => $partial ? 'sometimes|nullable|string|max:255' : 'nullable|string|max:255',
            'profesor' => $partial ? 'sometimes|nullable|string|max:255' : 'nullable|string|max:255',
            'cupo' => $required.'|integer|min:1|max:65535',
            'activo' => 'sometimes|boolean',
        ]);
        if (isset($validated['hora_inicio']) && strlen($validated['hora_inicio']) === 5) {
            $validated['hora_inicio'] .= ':00';
        }
        return $validated;
    }

    private function validateCycle(Request $request, $partial = false)
    {
        $required = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'nombre' => $required.'|string|max:255',
            'fecha_inicio' => $required.'|date',
            'fecha_fin' => $required.'|date'.($partial ? '' : '|after_or_equal:fecha_inicio'),
            'clases_por_semana' => $required.'|integer|between:1,7',
            'precio' => $required.'|numeric|min:0|max:99999999.99',
            'activo' => 'sometimes|boolean',
        ]);
    }
}