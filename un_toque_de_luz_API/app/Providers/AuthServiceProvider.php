<?php

namespace App\Providers;

use App\Models\Clase;
use App\Models\Inscripcion;
use App\Models\Recuperacion;
use App\Models\Reserva;
use App\Policies\BookingAdminPolicy;
use App\Policies\InscripcionPolicy;
use App\Policies\RecuperacionPolicy;
use App\Policies\ReservaPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Clase::class => BookingAdminPolicy::class,
        Inscripcion::class => InscripcionPolicy::class,
        Reserva::class => ReservaPolicy::class,
        Recuperacion::class => RecuperacionPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
