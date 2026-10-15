<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ciclo;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PublicYogaCyclesController extends Controller
{
    public function index(Request $request)
    {
        $now = Carbon::now(config('app.timezone'));
        $futureClasses = function ($query) use ($now) {
            $query->where('estado', 'programada')
                ->where(function ($future) use ($now) {
                    $future->whereDate('fecha', '>', $now->toDateString())
                        ->orWhere(function ($today) use ($now) {
                            $today->whereDate('fecha', $now->toDateString())
                                ->where('hora_inicio', '>', $now->format('H:i:s'));
                        });
                });
        };

        $cycles = Ciclo::query()
            ->where('activo', true)
            ->whereDate('fecha_fin', '>=', $now->toDateString())
            ->whereHas('clases', $futureClasses)
            ->with(['clases' => function ($query) use ($futureClasses) {
                $futureClasses($query);
                $query->withCount(['reservas as ocupados' => function ($reservations) {
                    $reservations->whereIn('estado', ['reservada', 'asistio']);
                }])->orderBy('fecha')->orderBy('hora_inicio');
            }])
            ->orderBy('fecha_inicio')
            ->get();

        return response()->json([
            'cycles' => $cycles->map(function (Ciclo $cycle) {
                $classes = $cycle->clases->each(function ($class) {
                    $class->setAttribute('disponibles', max(0, $class->cupo - $class->ocupados));
                });

                return [
                    'id' => $cycle->id,
                    'nombre' => $cycle->nombre,
                    'fecha_inicio' => $cycle->fecha_inicio->toDateString(),
                    'fecha_fin' => $cycle->fecha_fin->toDateString(),
                    'clases_por_semana' => $cycle->clases_por_semana,
                    'hay_lugares' => $classes->contains(function ($class) {
                        return $class->disponibles > 0;
                    }),
                    'horarios' => $classes->groupBy('horario_id')->map(function ($scheduleClasses) {
                        $firstClass = $scheduleClasses->first();

                        return [
                            'dia' => Carbon::parse($firstClass->fecha->toDateString(), config('app.timezone'))
                                ->locale('es')->dayName,
                            'hora_inicio' => substr($firstClass->hora_inicio, 0, 5),
                            'duracion_min' => $firstClass->duracion_min,
                            'hay_lugares' => $scheduleClasses->contains(function ($class) {
                                return $class->disponibles > 0;
                            }),
                        ];
                    })->values(),
                ];
            })->values(),
        ]);
    }
}