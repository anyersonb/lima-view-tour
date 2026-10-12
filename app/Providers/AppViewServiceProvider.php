<?php

namespace App\Providers;

use App\Models\Setting;
use App\Support\MetaText;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
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

        // <title>, og:title, twitter:title and the descriptions are read by
        // layouts/app.blade.php with yieldContent(). Child views fill them with
        // the inline @section('title', $x), which Blade already escapes, and
        // the layout escapes again: "&" showed as "&amp;amp;". Normalise to
        // plain text here (once, for every page) so the layout's {{ }} is the
        // only escape. See MetaText.
        View::composer('layouts.app', function ($view) {
            $factory = $view->getFactory();
            $sections = $factory->getSections();

            if (! isset($sections['title']) && ! isset($sections['description'])) {
                return;
            }

            // A section holding a View object (not rendered text) cannot go
            // through HtmlString: leave the whole table alone in that case.
            foreach ($sections as $content) {
                if (! is_string($content)) {
                    return;
                }
            }

            // Factory::inject() escapes plain strings again and cannot overwrite
            // an existing section, so rebuild the section table: HtmlString is
            // stored untouched by e().
            $factory->flushSections();

            foreach ($sections as $name => $content) {
                $isMeta = in_array($name, ['title', 'description'], true) && is_string($content);

                $factory->inject($name, new HtmlString($isMeta ? MetaText::plain($content) : $content));
            }
        });
    }
}
