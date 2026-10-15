<?php

namespace App\Console\Commands;

use App\Models\Recuperacion;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpireRecoveryCredits extends Command
{
    protected $signature = 'booking:expire-credits';

    protected $description = 'Mark expired recovery credits as unavailable';

    public function handle()
    {
        $today = Carbon::now(config('app.timezone'))->toDateString();
        $expired = Recuperacion::where('estado', 'disponible')
            ->whereDate('vence_en', '<', $today)
            ->update(['estado' => 'vencida', 'updated_at' => now()]);

        $this->info("Expired {$expired} recovery credit(s).");
        return 0;
    }
}