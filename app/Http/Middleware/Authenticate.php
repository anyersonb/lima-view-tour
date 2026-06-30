<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // No existe ruta 'login' por defecto: las rutas con guard 'auth'/'customer'
        // son el portal de cliente. Filament admin usa su propio login.
        $locale = app()->getLocale() ?: 'es';

        return route('customer.login', ['locale' => $locale]);
    }
}
