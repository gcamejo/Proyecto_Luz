<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Payments\CargoResource;
use App\Http\Resources\Payments\PagoResource;
use App\Services\ResumenMensualService;
use Illuminate\Http\Request;

class PaymentStudentController extends Controller
{
    // Solo lectura y siempre del usuario autenticado: no recibe ningún id.
    public function show(Request $request, ResumenMensualService $summary)
    {
        $account = $summary->studentAccount($request->user());

        return response()->json([
            'cargos' => CargoResource::collection($account['charges'])->resolve(),
            'pagos' => PagoResource::collection($account['payments'])->resolve(),
            'saldo_por_mes' => $account['monthly'],
            'saldo_a_favor' => $account['credit'],
        ]);
    }
}
