<?php

namespace App\Services;

use App\Models\Cargo;
use App\Models\Pago;
use App\Models\PagoAplicacion;
use App\Models\Yoguini;
use App\Support\Money;
use Carbon\Carbon;

class ResumenMensualService
{
    public function monthly(Carbon $period)
    {
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();

        $charges = Cargo::whereDate('periodo', $start->toDateString())
            ->where('estado', '!=', Cargo::ANULADO)
            ->get(['id', 'monto_final']);
        $billed = Money::sum($charges->pluck('monto_final'));
        $applied = Money::sum(PagoAplicacion::whereIn('cargo_id', $charges->pluck('id')->all())
            ->where('estado', PagoAplicacion::VIGENTE)
            ->pluck('monto'));
        $collected = Money::sum(Pago::where('estado', Pago::VIGENTE)
            ->whereDate('fecha_pago', '>=', $start->toDateString())
            ->whereDate('fecha_pago', '<=', $end->toDateString())
            ->pluck('monto'));

        return [
            'periodo' => $start->format('Y-m'),
            'facturado' => Money::decimal($billed),
            'aplicado_al_periodo' => Money::decimal($applied),
            'pendiente' => Money::decimal($billed - $applied),
            'cobrado_en_mes' => Money::decimal($collected),
            'saldo_a_favor_total' => Money::decimal($this->totalCreditCents()),
        ];
    }

    public function totalCreditCents()
    {
        $credits = $this->creditsByUser(null);

        return array_sum($credits);
    }

    // Saldo a favor por alumno: pagos vigentes menos sus aplicaciones vigentes.
    public function creditsByUser($userIds)
    {
        $query = Pago::where('estado', Pago::VIGENTE);
        if ($userIds !== null) {
            $query->whereIn('user_id', $userIds);
        }
        $pagos = $query->get(['id', 'user_id', 'monto']);
        $applied = PagoAplicacion::whereIn('pago_id', $pagos->pluck('id')->all())
            ->where('estado', PagoAplicacion::VIGENTE)
            ->get(['pago_id', 'monto'])
            ->groupBy('pago_id');

        $credits = [];
        foreach ($pagos as $pago) {
            $used = Money::sum(($applied->get($pago->id) ?: collect())->pluck('monto'));
            $credits[$pago->user_id] = ($credits[$pago->user_id] ?? 0) + max(0, Money::cents($pago->monto) - $used);
        }

        return $credits;
    }

    public function studentAccount(Yoguini $student)
    {
        $charges = Cargo::where('user_id', $student->id)
            ->with(['ciclo', 'inscripcion', 'aplicaciones'])
            ->orderByDesc('periodo')
            ->orderByDesc('id')
            ->get();
        $payments = Pago::where('user_id', $student->id)
            ->with(['aplicaciones.cargo'])
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->get();

        $byMonth = [];
        foreach ($charges->where('estado', '!=', Cargo::ANULADO) as $charge) {
            $key = $charge->periodo->format('Y-m');
            $byMonth[$key] = $byMonth[$key] ?? ['periodo' => $key, 'facturado' => 0, 'aplicado' => 0];
            $byMonth[$key]['facturado'] += $charge->finalCents();
            $byMonth[$key]['aplicado'] += $charge->appliedCents();
        }
        krsort($byMonth);
        $monthly = array_map(function ($row) {
            return [
                'periodo' => $row['periodo'],
                'facturado' => Money::decimal($row['facturado']),
                'aplicado' => Money::decimal($row['aplicado']),
                'saldo' => Money::decimal($row['facturado'] - $row['aplicado']),
            ];
        }, array_values($byMonth));

        $credit = 0;
        foreach ($payments as $payment) {
            $credit += $payment->remainingCents();
        }

        return [
            'charges' => $charges,
            'payments' => $payments,
            'monthly' => $monthly,
            'credit' => Money::decimal($credit),
        ];
    }
}
