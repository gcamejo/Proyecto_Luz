<?php

namespace App\Services;

use App\Models\Consentimiento;
use App\Models\Yoguini;
use Carbon\Carbon;

class LegalConsentService
{
    public const TYPES = ['privacidad', 'terminos'];

    public function status(Yoguini $user): array
    {
        $version = (string) config('legal.policy_version');
        $accepted = Consentimiento::query()
            ->where('user_id', $user->id)
            ->where('version', $version)
            ->pluck('tipo')
            ->all();
        $pending = array_values(array_diff(self::TYPES, $accepted));

        return [
            'version' => $version,
            'updated_at' => (string) config('legal.updated_at'),
            'accepted' => $pending === [],
            'pending' => $pending,
        ];
    }

    public function acceptCurrent(Yoguini $user, ?array $types = null): void
    {
        $version = (string) config('legal.policy_version');
        $acceptedAt = Carbon::now(config('app.timezone'));
        $types = $types === null ? self::TYPES : array_values(array_intersect(self::TYPES, $types));

        foreach ($types as $type) {
            Consentimiento::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'tipo' => $type,
                    'version' => $version,
                ],
                ['aceptado_en' => $acceptedAt]
            );
        }
    }
}