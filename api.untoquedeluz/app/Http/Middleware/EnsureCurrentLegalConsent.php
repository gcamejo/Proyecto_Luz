<?php

namespace App\Http\Middleware;

use App\Services\LegalConsentService;
use Closure;
use Illuminate\Http\Request;

class EnsureCurrentLegalConsent
{
    public function __construct(private LegalConsentService $consents)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $status = $this->consents->status($request->user());

        if (!$status['accepted']) {
            return response()->json([
                'code' => 'legal_consent_required',
                'version' => $status['version'],
                'pending' => $status['pending'],
            ], 428);
        }

        return $next($request);
    }
}