<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\ApplyCreditRequest;
use App\Http\Requests\Payments\ChargeDiscountRequest;
use App\Http\Requests\Payments\ChargeIndexRequest;
use App\Http\Requests\Payments\RegisterPaymentRequest;
use App\Http\Requests\Payments\StoreChargeRequest;
use App\Http\Requests\Payments\VoidWithReasonRequest;
use App\Http\Resources\Payments\CargoResource;
use App\Http\Resources\Payments\PagoAplicacionResource;
use App\Http\Resources\Payments\PagoResource;
use App\Models\Cargo;
use App\Models\Ciclo;
use App\Models\Pago;
use App\Models\PagoAplicacion;
use App\Models\Yoguini;
use App\Services\CargoService;
use App\Services\PagoService;
use App\Services\ResumenMensualService;
use App\Support\Money;
use Carbon\Carbon;

class PaymentAdminController extends Controller
{
    public function charges(ChargeIndexRequest $request, CargoService $cargos, ResumenMensualService $summary)
    {
        $charges = $cargos->list($this->period($request->input('periodo')), $request->validated());
        $credits = $summary->creditsByUser($charges->pluck('user_id')->unique()->all());
        foreach ($charges as $charge) {
            $charge->setAttribute('saldo_a_favor_alumno', Money::decimal($credits[$charge->user_id] ?? 0));
        }

        return response()->json(CargoResource::collection($charges)->resolve());
    }

    public function summary(ChargeIndexRequest $request, ResumenMensualService $summary)
    {
        return response()->json($summary->monthly($this->period($request->input('periodo'))));
    }

    public function student(Yoguini $yoguini, ResumenMensualService $summary)
    {
        $account = $summary->studentAccount($yoguini);

        return response()->json([
            'alumno' => [
                'id' => $yoguini->id,
                'nombre' => $yoguini->nombre,
                'apellido' => $yoguini->apellido,
                'email' => $yoguini->email,
            ],
            'cargos' => CargoResource::collection($account['charges'])->resolve(),
            'pagos' => PagoResource::collection($account['payments'])->resolve(),
            'saldo_por_mes' => $account['monthly'],
            'saldo_a_favor' => $account['credit'],
        ]);
    }

    public function storeCharge(StoreChargeRequest $request, CargoService $cargos)
    {
        $data = $request->validated();
        $charge = $cargos->createManual(Yoguini::findOrFail($data['user_id']), $data);

        return response()->json((new CargoResource($charge))->resolve(), 201);
    }

    public function discount(ChargeDiscountRequest $request, Cargo $cargo, CargoService $cargos)
    {
        $data = $request->validated();
        $charge = $cargos->applyDiscount($cargo, $data['descuento'], $data['motivo_descuento'] ?? '');

        return response()->json((new CargoResource($charge))->resolve());
    }

    public function generateCharges(Ciclo $ciclo, CargoService $cargos)
    {
        return response()->json($cargos->generateMissingForCycle($ciclo));
    }

    public function registerPayment(RegisterPaymentRequest $request, PagoService $pagos)
    {
        $data = $request->validated();
        $data['aplicar_excedente'] = $request->boolean('aplicar_excedente');
        $result = $pagos->registrar(Yoguini::findOrFail($data['user_id']), $data, $request->user());

        return response()->json([
            'pago' => (new PagoResource($result['pago']))->resolve(),
            'aplicado' => $result['aplicado'],
            'saldo_a_favor_generado' => $result['saldo_a_favor_generado'],
            'saldo_a_favor' => $result['saldo_a_favor'],
        ], 201);
    }

    public function applyCredit(ApplyCreditRequest $request, Cargo $cargo, PagoService $pagos)
    {
        $result = $pagos->aplicarSaldoAFavor($cargo, $request->validated()['monto'], $request->user());

        return response()->json([
            'cargo' => (new CargoResource($result['cargo']))->resolve(),
            'saldo_a_favor' => $result['saldo_a_favor'],
        ]);
    }

    public function voidApplication(VoidWithReasonRequest $request, PagoAplicacion $aplicacion, PagoService $pagos)
    {
        $result = $pagos->desaplicar($aplicacion, $request->validated()['motivo'], $request->user());

        return response()->json([
            'aplicacion' => (new PagoAplicacionResource($result['aplicacion']))->resolve(),
            'saldo_a_favor' => $result['saldo_a_favor'],
        ]);
    }

    public function voidPayment(VoidWithReasonRequest $request, Pago $pago, PagoService $pagos)
    {
        $result = $pagos->anular($pago, $request->validated()['motivo'], $request->user());

        return response()->json([
            'pago' => (new PagoResource($result['pago']))->resolve(),
            'saldo_a_favor' => $result['saldo_a_favor'],
        ]);
    }

    public function voidCharge(VoidWithReasonRequest $request, Cargo $cargo, CargoService $cargos)
    {
        $charge = $cargos->void($cargo, $request->validated()['motivo'], $request->user());

        return response()->json((new CargoResource($charge))->resolve());
    }

    private function period($value)
    {
        $timezone = config('app.timezone');

        return $value
            ? Carbon::createFromFormat('Y-m-d', $value.'-01', $timezone)->startOfDay()
            : Carbon::now($timezone)->startOfMonth();
    }
}
