<?php

// Registra los servicios y comportamientos de la aplicación.

namespace App\Providers;

use App\Support\People\Rut;
use App\View\Composers\AppLayoutComposer;
use App\View\Composers\TopbarComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        View::composer('layouts.app', AppLayoutComposer::class);
        View::composer('partials.topbar', TopbarComposer::class);

        if (app()->environment('production')) {
            if (config('app.debug')) {
                throw new \LogicException('APP_DEBUG must be false in production.');
            }

            if (! config('session.secure')) {
                throw new \LogicException('SESSION_SECURE_COOKIE must be true in production.');
            }

            $trustedProxies = config('trustedproxy.proxies', []);
            if ($trustedProxies === []) {
                throw new \LogicException('Set TRUSTED_PROXIES to the private frontend proxy addresses in production.');
            }

            if (array_intersect(['*', '**', '0.0.0.0/0', '::/0', 'REMOTE_ADDR'], $trustedProxies) !== []) {
                throw new \LogicException('TRUSTED_PROXIES must not trust all IP addresses.');
            }
        }

        RateLimiter::for('login', static function (Request $request): array {
            $ip = $request->ip();
            $limits = [Limit::perMinute(60)->by('login-ip:'.$ip)];
            $rut = $request->input('rut');

            if (is_string($rut) && strlen($rut) <= 32) {
                $canonicalRut = Rut::limpiarRut($rut);
                if ($canonicalRut !== '') {
                    $limits[] = Limit::perMinute(10)->by('login-account-ip:'.$canonicalRut.'|'.$ip);
                }
            }

            return $limits;
        });

    }
}
