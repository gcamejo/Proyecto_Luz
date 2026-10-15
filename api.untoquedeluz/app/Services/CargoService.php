<?php

namespace App\Services;

use App\Models\Cargo;
use App\Models\Ciclo;
use App\Models\Inscripcion;
use App\Models\PagoAplicacion;
use App\Models\Yoguini;
use App\Services\Concerns\LocksStudent;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CargoService
{
    use LocksStudent;

    public function createForEnrollment(Inscripcion $inscripcion, Ciclo $ciclo)
    {
        if (Money::cents($ciclo->precio) <= 0) {
            return null;
        }

        $existing = Cargo::where('inscripcion_id', $inscripcion->id)->first();
        if ($existing) {
            return $existing;
        }

        $amount = Money::decimal(Money::cents($ciclo->precio));

        return Cargo::create([
            'user_id' => $inscripcion->user_id,
            'inscripcion_id' => $inscripcion->id,
            'ciclo_id' => $ciclo->id,
            'periodo' => Carbon::parse($ciclo->fecha_inicio->toDateString())->startOfMonth()->toDateString(),
            'concepto' => 'Ciclo: '.$ciclo->nombre,
            'monto_original' => $amount,
            'descuento' => '0.00',
            'monto_final' => $amount,
            'estado' => Cargo::PENDIENTE,
        ]);
    }

    public function generateMissingForCycle(Ciclo $ciclo)
    {
        return DB::transaction(function () use ($ciclo) {
            $cycle = Ciclo::whereKey($ciclo->id)->lockForUpdate()->firstOrFail();
            if (Money::cents($cycle->precio) <= 0) {
                return ['creados' => 0, 'omitidos' => 0, 'sin_precio' => true];
            }

            $enrollments = Inscripcion::where('ciclo_id', $cycle->id)
                ->where('estado', '!=', 'cancelada')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $created = 0;
            $skipped = 0;
            foreach ($enrollments as $enrollment) {
                if (Cargo::where('inscripcion_id', $enrollment->id)->exists()) {
                    $skipped++;
                    continue;
                }
                $this->createForEnrollment($enrollment, $cycle);
                $created++;
            }

            return ['creados' => $created, 'omitidos' => $skipped, 'sin_precio' => false];
        });
    }

    public function createManual(Yoguini $alumno, array $data)
    {
        return DB::transaction(function () use ($alumno, $data) {
            $this->lockStudent($alumno->id);
            $amount = Money::decimal(Money::cents($data['monto_original']));

            return Cargo::create([
                'user_id' => $alumno->id,
                'ciclo_id' => $data['ciclo_id'] ?? null,
                'periodo' => Carbon::parse($data['periodo'])->startOfMonth()->toDateString(),
                'concepto' => $data['concepto'],
                'monto_original' => $amount,
                'descuento' => '0.00',
                'monto_final' => $amount,
                'vence_el' => $data['vence_el'] ?? null,
                'estado' => Cargo::PENDIENTE,
            ])->fresh(['yoguini', 'ciclo', 'inscripcion', 'aplicaciones']);
        });
    }

    public function applyDiscount(Cargo $cargo, $discount, $reason)
    {
        return DB::transaction(function () use ($cargo, $discount, $reason) {
            $this->lockStudent(Cargo::findOrFail($cargo->id)->user_id);
            $locked = Cargo::whereKey($cargo->id)->lockForUpdate()->firstOrFail();
            if ($locked->estado === Cargo::ANULADO) {
                $this->fail('cargo', 'A voided charge cannot be discounted.');
            }

            $discountCents = Money::cents($discount);
            $original = Money::cents($locked->monto_original);
            if ($discountCents > 0 && trim((string) $reason) === '') {
                $this->fail('motivo_descuento', 'A reason is required for a discount.');
            }
            if ($discountCents > $original) {
                $this->fail('descuento', 'The discount cannot exceed the original amount.');
            }
            $newFinal = $original - $discountCents;
            if ($newFinal < $locked->appliedCents()) {
                $this->fail('descuento', 'The final amount cannot be lower than the amount already applied ('.Money::decimal($locked->appliedCents()).').');
            }

            $locked->descuento = Money::decimal($discountCents);
            $locked->motivo_descuento = $discountCents > 0 ? trim($reason) : null;
            $locked->monto_final = Money::decimal($newFinal);
            $locked->save();
            $this->recalculate($locked);

            return $locked->fresh(['yoguini', 'ciclo', 'inscripcion', 'aplicaciones']);
        });
    }

    public function void(Cargo $cargo, $reason, Yoguini $admin)
    {
        return DB::transaction(function () use ($cargo, $reason, $admin) {
            $this->lockStudent(Cargo::findOrFail($cargo->id)->user_id);
            $locked = Cargo::whereKey($cargo->id)->lockForUpdate()->firstOrFail();
            if ($locked->estado === Cargo::ANULADO) {
                $this->fail('cargo', 'The charge is already voided.');
            }
            $hasApplications = PagoAplicacion::where('cargo_id', $locked->id)
                ->where('estado', PagoAplicacion::VIGENTE)
                ->exists();
            if ($hasApplications) {
                $this->fail('cargo', 'Remove the applied payments from this charge before voiding it.');
            }

            $locked->estado = Cargo::ANULADO;
            $locked->anulado_en = Carbon::now(config('app.timezone'));
            $locked->anulado_por = $admin->id;
            $locked->motivo_anulacion = trim($reason);
            $locked->save();

            return $locked->fresh(['yoguini', 'ciclo', 'inscripcion', 'aplicaciones']);
        });
    }

    public function recalculate(Cargo $cargo)
    {
        $cargo->unsetRelation('aplicaciones');
        if ($cargo->estado === Cargo::ANULADO) {
            return $cargo;
        }

        $applied = $cargo->appliedCents();
        $balance = $cargo->finalCents() - $applied;
        $status = $balance <= 0 ? Cargo::PAGADO : ($applied > 0 ? Cargo::PARCIAL : Cargo::PENDIENTE);
        if ($cargo->estado !== $status) {
            $cargo->estado = $status;
            $cargo->save();
        }

        return $cargo;
    }

    public function list(Carbon $period, array $filters)
    {
        $query = Cargo::with(['yoguini', 'ciclo', 'inscripcion', 'aplicaciones'])
            ->whereDate('periodo', $period->copy()->startOfMonth()->toDateString());

        if (!empty($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }
        if (!empty($filters['ciclo_id'])) {
            $query->where('ciclo_id', $filters['ciclo_id']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (!empty($filters['q'])) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($filters['q'])).'%';
            $query->whereHas('yoguini', function ($student) use ($term) {
                $student->where('nombre', 'like', $term)
                    ->orWhere('apellido', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        return $query->orderBy('user_id')->orderBy('id')->get();
    }
}
