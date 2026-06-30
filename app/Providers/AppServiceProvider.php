<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        // La app se sirve en la RAÍZ del dominio (limaviewtours.com). La generación
        // de URLs se basa en APP_URL + TrustProxies; si se llegara a necesitar HTTPS
        // forzado, hacerlo con APP_FORCE_HTTPS (ya en .env), no con rutas fijas.
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }
    }
}
