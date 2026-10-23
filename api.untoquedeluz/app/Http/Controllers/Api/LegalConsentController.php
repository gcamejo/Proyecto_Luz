<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\Consentimiento;
use App\Models\Yoguini;
use App\Services\LegalConsentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LegalConsentController extends Controller
{
    public function publicParameters()
    {
        $noticeHours = Configuracion::where('clave', 'horas_aviso_minimas')->value('valor');

        if ($noticeHours === null) {
            return response()->json(['message' => 'Legal parameters are not configured.'], 503);
        }

        return response()->json([
            'horas_aviso_minimas' => (int) $noticeHours,
        ]);
    }

    public function status(Request $request, LegalConsentService $consents)
    {
        return response()->json($consents->status($request->user()));
    }

    public function accept(Request $request, LegalConsentService $consents)
    {
        $validated = $request->validate([
            'acepta' => 'accepted',
            'tipos' => 'sometimes|required|array|min:1',
            'tipos.*' => 'required|in:privacidad,terminos',
        ]);

        DB::transaction(function () use ($request, $consents, $validated) {
            $consents->acceptCurrent($request->user(), $validated['tipos'] ?? null);
        });

        return $this->status($request, $consents);
    }

    public function forStudent(Yoguini $yoguini)
    {
        $this->authorize('viewAny', Consentimiento::class);

        return response()->json(Consentimiento::query()
            ->where('user_id', $yoguini->id)
            ->orderBy('tipo')
            ->orderByDesc('aceptado_en')
            ->get(['tipo', 'version', 'aceptado_en']));
    }
}