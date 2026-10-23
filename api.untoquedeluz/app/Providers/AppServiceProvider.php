<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            $emailKey = hash('sha256', mb_strtolower(trim((string) $request->input('email'))));

            return [
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
                Limit::perMinute(5)->by('login-email:'.$emailKey.'|'.$request->ip()),
            ];
        });

        RateLimiter::for('registration', function (Request $request) {
            return Limit::perMinute(5)->by('registration|'.$request->ip());
        });

        RateLimiter::for('password-change', function (Request $request) {
            if ($request->filled('password')) {
                return Limit::perMinute(3)->by('password-change-user:'.$request->user()->getAuthIdentifier());
            }

            return Limit::perMinute(60)->by('profile-user:'.$request->user()->getAuthIdentifier());
        });

        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute(30)->by('public|'.$request->ip());
        });

        RateLimiter::for('public-yoga-cycles', function (Request $request) {
            return Limit::perMinute(30)->by('public-yoga-cycles|'.$request->ip());
        });
    }
}
