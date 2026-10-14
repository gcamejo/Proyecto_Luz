<?php

namespace App\Services;

use App\Models\Cargo;
use App\Models\Pago;
use App\Models\PagoAplicacion;
use App\Models\Yoguini;
use App\Services\Concerns\LocksStudent;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PagoService
{
    use LocksStudent;

    private $cargos;

    public function __construct(CargoService $cargos)
    {
        $this->cargos = $cargos;
    }

    public function registrar(Yoguini $alumno, array $data, Yoguini $admin)
    {
        return DB::transaction(function () use ($alumno, $data, $admin) {
            $this->lockStudent($alumno->id);

            $today = Carbon::now(config('app.timezone'))->toDateString();
            if ($data['fecha_pago'] > $today) {
                $this->fail('fecha_pago', 'The payment date cannot be in the future.');
            }
            $amount = Money::cents($data['monto']);
            if ($amount <= 0) {
                $this->fail('monto', 'The amount must be greater than zero.');
            }
            $cargoId = $data['cargo_id'] ?? null;
            $spill = !empty($data['aplicar_excedente']);
            if ($spill && !$cargoId) {
                $this->fail('aplicar_excedente', 'Select a charge to apply the surplus to other charges.');
            }

            $cargo = null;
            if ($cargoId) {
                $cargo = Cargo::whereKey($cargoId)->lockForUpdate()->firstOrFail();
                if ((int) $cargo->user_id !== (int) $alumno->id) {
                    $this->fail('cargo_id', 'The charge does not belong to this student.');
                }
                if ($cargo->estado === Cargo::ANULADO) {
                    $this->fail('cargo_id', 'A voided charge cannot receive payments.');
                }
            }

            $pago = Pago::create([
                'user_id' => $alumno->id,
                'monto' => Money::decimal($amount),
                'fecha_pago' => $data['fecha_pago'],
                'metodo' => $data['metodo'],
                'referencia' => $data['referencia'] ?? null,
                'nota' => $data['nota'] ?? null,
                'registrado_por' => $admin->id,
                'estado' => Pago::VIGENTE,
            ]);

            $remaining = $amount;
            if ($cargo) {
                $take = min($remaining, $cargo->balanceCents());
                $remaining -= $this->apply($pago, $cargo, $take, $admin);
            }
            if ($spill && $remaining > 0) {
                $open = Cargo::where('user_id', $alumno->id)
                    ->whereIn('estado', [Cargo::PENDIENTE, Cargo::PARCIAL])
                    ->where('id', '!=', $cargo->id)
                    ->orderBy('periodo')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                foreach ($open as $other) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $remaining -= $this->apply($pago, $other, min($remaining, $other->balanceCents()), $admin);
                }
            }

            return [
                'pago' => $pago->fresh(['aplicaciones.cargo']),
                'aplicado' => Money::decimal($amount - $remaining),
                'saldo_a_favor_generado' => Money::decimal($remaining),
                'saldo_a_favor' => Money::decimal($this->creditCents($alumno->id)),
            ];
        });
    }

    public function aplicarSaldoAFavor(Cargo $cargo, $monto, Yoguini $admin)
    {
        return DB::transaction(function () use ($cargo, $monto, $admin) {
            $userId = Cargo::findOrFail($cargo->id)->user_id;
            $this->lockStudent($userId);
            $locked = Cargo::whereKey($cargo->id)->lockForUpdate()->firstOrFail();
            if (!in_array($locked->estado, [Cargo::PENDIENTE, Cargo::PARCIAL], true)) {
                $this->fail('cargo', 'Credit can only be applied to pending or partially paid charges.');
            }

            $amount = Money::cents($monto);
            if ($amount <= 0) {
                $this->fail('monto', 'The amount must be greater than zero.');
            }
            $remainders = $this->remainders($userId, true);
            $credit = array_sum(array_column($remainders, 'remaining'));
            $maximum = min($locked->balanceCents(), $credit);
            if ($amount > $maximum) {
                $this->fail('monto', 'The amount exceeds the maximum applicable ('.Money::decimal($maximum).').');
            }

            $left = $amount;
            foreach ($remainders as $item) {
                if ($left <= 0) {
                    break;
                }
                $left -= $this->apply($item['pago'], $locked, min($left, $item['remaining']), $admin);
            }

            return [
                'cargo' => $locked->fresh(['yoguini', 'ciclo', 'inscripcion', 'aplicaciones']),
                'saldo_a_favor' => Money::decimal($this->creditCents($userId)),
            ];
        });
    }

    public function desaplicar(PagoAplicacion $aplicacion, $reason, Yoguini $admin)
    {
        return DB::transaction(function () use ($aplicacion, $reason, $admin) {
            $userId = Pago::findOrFail(PagoAplicacion::findOrFail($aplicacion->id)->pago_id)->user_id;
            $this->lockStudent($userId);
            $locked = PagoAplicacion::whereKey($aplicacion->id)->lockForUpdate()->firstOrFail();
            if ($locked->estado !== PagoAplicacion::VIGENTE) {
                $this->fail('aplicacion', 'The application is already voided.');
            }
            $cargo = Cargo::whereKey($locked->cargo_id)->lockForUpdate()->firstOrFail();

            $this->voidApplication($locked, trim($reason), $admin);
            $this->cargos->recalculate($cargo);

            return [
                'aplicacion' => $locked->fresh(['cargo']),
                'saldo_a_favor' => Money::decimal($this->creditCents($userId)),
            ];
        });
    }

    public function anular(Pago $pago, $reason, Yoguini $admin)
    {
        return DB::transaction(function () use ($pago, $reason, $admin) {
            $userId = Pago::findOrFail($pago->id)->user_id;
            $this->lockStudent($userId);
            $locked = Pago::whereKey($pago->id)->lockForUpdate()->firstOrFail();
            if ($locked->estado !== Pago::VIGENTE) {
                $this->fail('pago', 'The payment is already voided.');
            }

            $applications = PagoAplicacion::where('pago_id', $locked->id)
                ->where('estado', PagoAplicacion::VIGENTE)
                ->orderBy('cargo_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $cargos = Cargo::whereIn('id', $applications->pluck('cargo_id')->unique()->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($applications as $application) {
                $this->voidApplication($application, trim($reason), $admin);
            }
            $locked->estado = Pago::ANULADO;
            $locked->anulado_en = Carbon::now(config('app.timezone'));
            $locked->anulado_por = $admin->id;
            $locked->motivo_anulacion = trim($reason);
            $locked->save();
            foreach ($cargos as $cargo) {
                $this->cargos->recalculate($cargo);
            }

            return [
                'pago' => $locked->fresh(['aplicaciones.cargo']),
                'saldo_a_favor' => Money::decimal($this->creditCents($userId)),
            ];
        });
    }

    public function creditCents($userId)
    {
        return array_sum(array_column($this->remainders($userId, false), 'remaining'));
    }

    // Pagos vigentes con remanente, del más antiguo al más nuevo.
    public function remainders($userId, $lock)
    {
        $query = Pago::where('user_id', $userId)
            ->where('estado', Pago::VIGENTE)
            ->orderBy('fecha_pago')
            ->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $pagos = $query->get();
        $applied = PagoAplicacion::whereIn('pago_id', $pagos->pluck('id')->all())
            ->where('estado', PagoAplicacion::VIGENTE)
            ->get()
            ->groupBy('pago_id');

        $result = [];
        foreach ($pagos as $pago) {
            $used = Money::sum(($applied->get($pago->id) ?: collect())->pluck('monto'));
            $remaining = $pago->amountCents() - $used;
            if ($remaining > 0) {
                $result[] = ['pago' => $pago, 'remaining' => $remaining];
            }
        }

        return $result;
    }

    private function apply(Pago $pago, Cargo $cargo, $cents, Yoguini $admin)
    {
        if ($cents <= 0) {
            return 0;
        }

        PagoAplicacion::create([
            'pago_id' => $pago->id,
            'cargo_id' => $cargo->id,
            'monto' => Money::decimal($cents),
            'estado' => PagoAplicacion::VIGENTE,
            'registrado_por' => $admin->id,
        ]);
        $this->cargos->recalculate($cargo);

        return $cents;
    }

    private function voidApplication(PagoAplicacion $application, $reason, Yoguini $admin)
    {
        $application->estado = PagoAplicacion::ANULADA;
        $application->anulada_en = Carbon::now(config('app.timezone'));
        $application->anulada_por = $admin->id;
        $application->motivo_anulacion = $reason;
        $application->save();
    }
}
