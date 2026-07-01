<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Customer\AccountController;
use App\Http\Controllers\Customer\ForgotPasswordController;
use App\Http\Controllers\Customer\LoginController;
use App\Http\Controllers\Customer\LogoutController;
use App\Http\Controllers\Customer\RegisterController;
use App\Http\Controllers\Customer\ResetPasswordController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// SEO automático
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
Route::get('/llms.txt', [SitemapController::class, 'llms'])->name('llms');

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
    // Map pt-BR and pt-PT to our 'pt' locale
    $preferred = request()->getPreferredLanguage($supported);
    if (! $preferred) {
        $rawLang = substr(request()->server('HTTP_ACCEPT_LANGUAGE', ''), 0, 2);
        $preferred = ($rawLang === 'pt') ? 'pt' : config('app.locale');
    }
    $locale = $preferred;
    return redirect("/{$locale}");
});

Route::get('/mantenimiento', fn () => view('errors.maintenance'))->name('maintenance');

Route::prefix('{locale}')
    ->where(['locale' => 'es|en|pt'])
    ->middleware('setlocale')
    ->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('home');

        Route::get('/tours', [TourController::class, 'index'])->name('tours.index');
        Route::get('/tours/categoria/{categoria}', [TourController::class, 'category'])
            ->where('categoria', 'lima|ica|cusco')
            ->name('tours.category');
        Route::get('/tours/detalle/{slug}', [TourController::class, 'show'])->name('tours.show');
        Route::post('/tours/detalle/{slug}/resena', [TourController::class, 'storeReview'])
            ->middleware('throttle:6,1')
            ->name('tours.review.store');
        Route::get('/buscar', [TourController::class, 'search'])->name('tours.results');

        // Cart routes (Fase 2)
        Route::get('/carrito', [CartController::class, 'index'])->name('cart.index');
        Route::post('/carrito/agregar', [CartController::class, 'store'])->name('cart.store');
        Route::post('/carrito/cupon', [CartController::class, 'applyCoupon'])
            ->middleware('throttle:30,1')
            ->name('cart.coupon');
        Route::delete('/carrito/vaciar', [CartController::class, 'clear'])->name('cart.clear');
        Route::patch('/carrito/{rowId}', [CartController::class, 'updateItem'])->name('cart.update');
        Route::delete('/carrito/{rowId}', [CartController::class, 'destroy'])->name('cart.destroy');

        // Legacy /checkout alias → redirect 301 to cart.index
        Route::get('/checkout', fn (string $locale) => redirect()->route('cart.index', ['locale' => $locale], 301))->name('checkout');

        // Checkout Phase 3 — payment flow
        Route::get('/checkout/pago', [CheckoutController::class, 'showPaymentForm'])->name('checkout.pay');
        Route::post('/checkout/procesar', [CheckoutController::class, 'processPayment'])
            ->middleware('throttle:checkout')
            ->name('checkout.process');
        Route::get('/checkout/gracias', [CheckoutController::class, 'thanks'])->name('checkout.thanks');
        Route::post('/checkout/paypal/create',  [CheckoutController::class, 'paypalCreateOrder'])
            ->middleware('throttle:checkout')
            ->name('checkout.paypal.create');
        Route::post('/checkout/paypal/capture', [CheckoutController::class, 'paypalCaptureOrder'])
            ->middleware('throttle:checkout')
            ->name('checkout.paypal.capture');

        Route::get('/contacto', [ContactController::class, 'show'])->name('contact');
        Route::post('/contacto', [ContactController::class, 'submit'])
            ->middleware('throttle:contact')
            ->name('contact.submit');
        Route::get('/gracias', fn () => view('gracias'))->name('contact.thanks');

        // Página de reseñas/comentarios de clientes (Google + Tripadvisor + Web)
        Route::get('/resenas', [ReviewController::class, 'index'])->name('reviews');

        // Blog
        Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
        Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

        Route::get('/nosotros', function () {
            $page = \App\Models\Page::where('slug', 'nosotros')->first();

            // Active testimonials — 4 cards + 1 featured Tripadvisor quote
            $testimonials = \App\Models\Testimonial::active()->latest('order')->take(4)->get();
            $featured     = \App\Models\Testimonial::active()
                ->where('source', 'tripadvisor')
                ->latest()
                ->first()
                ?? \App\Models\Testimonial::active()->latest()->first();

            return view('about', compact('page', 'testimonials', 'featured'));
        })->name('about');

        // Legal pages
        Route::get('/terminos', [PageController::class, 'terms'])->name('legal.terms');
        Route::get('/privacidad', [PageController::class, 'privacy'])->name('legal.privacy');

        // ── Customer portal (Fase 1) ──────────────────────────────────────
        Route::get('/ingresar', [LoginController::class, 'showForm'])->name('customer.login');
        Route::post('/ingresar', [LoginController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('customer.login.post');

        Route::get('/registro', [RegisterController::class, 'showForm'])->name('customer.register');
        Route::post('/registro', [RegisterController::class, 'register'])
            ->middleware('throttle:10,1')
            ->name('customer.register.post');

        Route::post('/salir', [LogoutController::class, 'logout'])
            ->name('customer.logout');

        Route::get('/recuperar', [ForgotPasswordController::class, 'showForm'])->name('customer.password.request');
        Route::post('/recuperar', [ForgotPasswordController::class, 'sendResetLink'])
            ->middleware('throttle:5,1')
            ->name('customer.password.email');

        Route::get('/recuperar/{token}', [ResetPasswordController::class, 'showForm'])->name('customer.password.reset');
        Route::post('/recuperar/reset', [ResetPasswordController::class, 'reset'])
            ->middleware('throttle:5,1')
            ->name('customer.password.update');

        // Protected customer routes
        Route::middleware('auth:customer')->group(function () {
            Route::get('/mi-cuenta', [AccountController::class, 'dashboard'])->name('customer.account');
            Route::patch('/mi-cuenta/perfil', [AccountController::class, 'updateProfile'])->name('customer.profile.update');
        });
    });

// Culqi Webhook — outside locale group, CSRF exempt
Route::post('/webhooks/culqi', [WebhookController::class, 'culqi'])
    ->name('webhooks.culqi')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
