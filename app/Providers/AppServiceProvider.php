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
        // Subfolder deployment: when the app is served from
        // https://limaviewtours.com/limaprogramacion/, force the root URL so
        // asset()/url()/route() helpers emit the correct prefix. Also patch
        // Livewire's asset/update URIs (its defaults don't honor forceRootUrl).
        if (! empty($_SERVER['LIMA_SUBFOLDER'])) {
            URL::forceRootUrl('https://limaviewtours.com/limaprogramacion');
            URL::forceScheme('https');
            // Livewire's asset_url replaces the full script URL (not a prefix).
            // Pick the right file based on APP_DEBUG since Livewire registers
            // /livewire/livewire.js in debug and /livewire/livewire.min.js otherwise.
            $jsFile = config('app.debug') ? 'livewire.js' : 'livewire.min.js';
            config([
                'livewire.asset_url'  => "/limaprogramacion/livewire/{$jsFile}",
                'livewire.update_uri' => '/limaprogramacion/livewire/update',
            ]);
        }
    }
}
