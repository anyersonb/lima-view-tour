<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer('*', function ($view) {
            try {
                if (Schema::hasTable('settings')) {
                    $settings = cache()->rememberForever('settings.all_view', function () {
                        return Setting::all()->pluck('value', 'key')->toArray();
                    });
                    $view->with('siteSettings', $settings);
                }
            } catch (\Throwable $e) {
                $view->with('siteSettings', []);
            }
        });
    }
}
