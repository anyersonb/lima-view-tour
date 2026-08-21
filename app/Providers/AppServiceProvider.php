<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
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

        // El restablecimiento de contraseña de clientes usa rutas con prefijo de
        // idioma y nombre `customer.password.reset` (/{locale}/recuperar/{token}).
        // La notificación por defecto de Laravel apunta a la ruta `password.reset`
        // (inexistente) → daba 500 al enviar el enlace. Redirigimos la generación
        // del enlace a la ruta correcta, incluyendo locale y email.
        ResetPassword::createUrlUsing(function ($notifiable, string $token): string {
            return route('customer.password.reset', [
                'locale' => app()->getLocale(),
                'token'  => $token,
                'email'  => $notifiable->getEmailForPasswordReset(),
            ]);
        });
    }
}
