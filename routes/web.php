<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// SEO automático
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Newsletter (sin locale)
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])
    ->middleware('throttle:newsletter')
    ->name('newsletter.subscribe');

Route::get('/newsletter/confirm/{token}', [NewsletterController::class, 'confirm'])
    ->name('newsletter.confirm');

Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])
    ->name('newsletter.unsubscribe');

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

        // Cart routes (Fase 2)
        Route::get('/carrito', [CartController::class, 'index'])->name('cart.index');
        Route::post('/carrito/agregar', [CartController::class, 'store'])->name('cart.store');
        Route::post('/carrito/cupon', [CartController::class, 'applyCoupon'])->name('cart.coupon');
        Route::delete('/carrito/vaciar', [CartController::class, 'clear'])->name('cart.clear');
        Route::patch('/carrito/{rowId}', [CartController::class, 'updateItem'])->name('cart.update');
        Route::delete('/carrito/{rowId}', [CartController::class, 'destroy'])->name('cart.destroy');

        // Legacy /checkout alias → redirect 301 to cart.index
        Route::get('/checkout', fn (string $locale) => redirect()->route('cart.index', ['locale' => $locale], 301))->name('checkout');

        // Checkout Phase 3 — payment flow
        Route::get('/checkout/pago', [CheckoutController::class, 'showPaymentForm'])->name('checkout.pay');
        Route::post('/checkout/procesar', [CheckoutController::class, 'processPayment'])->name('checkout.process');
        Route::get('/checkout/gracias', [CheckoutController::class, 'thanks'])->name('checkout.thanks');

        Route::get('/contacto', [ContactController::class, 'show'])->name('contact');
        Route::post('/contacto', [ContactController::class, 'submit'])
            ->middleware('throttle:contact')
            ->name('contact.submit');
        Route::get('/gracias', fn () => view('gracias'))->name('contact.thanks');

        Route::get('/nosotros', fn () => view('about'))->name('about');

        // Legal pages
        Route::get('/terminos', [PageController::class, 'terms'])->name('legal.terms');
        Route::get('/privacidad', [PageController::class, 'privacy'])->name('legal.privacy');
    });

// Culqi Webhook — outside locale group, CSRF exempt
Route::post('/webhooks/culqi', [WebhookController::class, 'culqi'])
    ->name('webhooks.culqi')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
