<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->route('locale');

        if (in_array($locale, config('app.supported_locales', ['es', 'en']), true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
