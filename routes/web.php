<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TourController;
use Illuminate\Support\Facades\Route;

// SEO automático
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Newsletter (sin locale)
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');

// Redirección por idioma del navegador
Route::get('/', function () {
    $supported = config('app.supported_locales', ['es', 'en']);
    $locale = request()->getPreferredLanguage($supported) ?: config('app.locale');
    return redirect("/{$locale}");
});

Route::get('/mantenimiento', fn () => view('errors.maintenance'))->name('maintenance');

Route::prefix('{locale}')
    ->where(['locale' => 'es|en'])
    ->middleware('setlocale')
    ->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('home');

        Route::get('/tours', [TourController::class, 'index'])->name('tours.index');
        Route::get('/tours/categoria/{categoria}', [TourController::class, 'category'])
            ->where('categoria', 'lima|ica|cusco')
            ->name('tours.category');
        Route::get('/tours/detalle/{slug}', [TourController::class, 'show'])->name('tours.show');
        Route::get('/buscar', [TourController::class, 'search'])->name('tours.results');

        Route::get('/checkout', fn () => view('checkout'))->name('checkout');

        Route::get('/contacto', [ContactController::class, 'show'])->name('contact');
        Route::post('/contacto', [ContactController::class, 'submit'])->name('contact.submit');
        Route::get('/gracias', fn () => view('gracias'))->name('contact.thanks');

        Route::get('/nosotros', fn () => view('about'))->name('about');
    });
