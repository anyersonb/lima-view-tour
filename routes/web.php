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
use App\Support\LocalizedPages;
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

// ─────────────────────────────────────────────────────────────────────────────
// Diagnóstico SMTP temporal (2026-07-16). Protegido por token. Envía un correo
// de prueba y devuelve la config de correo + el error exacto si falla.
// QUITAR después de resolver el problema de envío de reservas.
// Uso: /_diag/mail?key=lvt-mail-diag-2026&to=tucorreo@dominio.com
// ─────────────────────────────────────────────────────────────────────────────
Route::get('/_diag/mail', function () {
    abort_unless(request('key') === 'lvt-mail-diag-2026', 404);

    $mailer = config('mail.default');
    $conn   = config("mail.mailers.{$mailer}");
    $maskedUser = ($u = config('mail.mailers.smtp.username'))
        ? substr((string) $u, 0, 3) . '***' . (str_contains((string) $u, '@') ? strstr((string) $u, '@') : '')
        : null;

    $to = request('to')
        ?: \App\Models\Setting::get('booking_notification_email')
        ?: config('mail.from.address');

    $config = [
        'mail_default'   => $mailer,
        'smtp_host'      => config('mail.mailers.smtp.host'),
        'smtp_port'      => config('mail.mailers.smtp.port'),
        'smtp_encryption'=> config('mail.mailers.smtp.encryption') ?? config('mail.mailers.smtp.scheme'),
        'smtp_username'  => $maskedUser,
        'smtp_password_set' => (bool) config('mail.mailers.smtp.password'),
        'from_address'   => config('mail.from.address'),
        'from_name'      => config('mail.from.name'),
        'app_env'        => config('app.env'),
        'test_to'        => $to,
    ];

    try {
        \Illuminate\Support\Facades\Mail::raw(
            'Prueba de envío SMTP desde Lima View Tours — ' . now()->toDateTimeString(),
            function ($m) use ($to) {
                $m->to($to)->subject('[TEST] Diagnóstico SMTP Lima View Tours');
            }
        );

        return response()->json([
            'ok'      => true,
            'message' => "Correo de prueba enviado a {$to}. Revisa bandeja y SPAM.",
            'config'  => $config,
        ], 200, [], JSON_PRETTY_PRINT);
    } catch (\Throwable $e) {
        return response()->json([
            'ok'        => false,
            'error'     => $e->getMessage(),
            'exception' => get_class($e),
            'config'    => $config,
        ], 500, [], JSON_PRETTY_PRINT);
    }
})->name('diag.mail');

Route::prefix('{locale}')
    ->where(['locale' => 'es|en|pt'])
    ->middleware('setlocale')
    ->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('home');

        Route::get('/tours', [TourController::class, 'index'])->name('tours.index');
        Route::get('/tours/categoria/{categoria}', [TourController::class, 'category'])
            ->where('categoria', 'lima|ica|cusco')
            ->name('tours.category');
        // Slug legacy (título renombrado antes del blindaje de slug) → 301 al slug vigente
        Route::get('/tours/detalle/tour-de-dia-completo-al-oasis-de-huacachina-con-buggie-privado-canam-islas-ballestas-en-paracas',
            fn (string $locale) => redirect("/{$locale}/tours/detalle/tour-privado-huacachina-islas-ballestas-atardecer-buggy-can-am", 301));
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
        // Carrito abandonado: captura de contacto (AJAX) + link de recuperación
        Route::post('/carrito/guardar-contacto', [CartController::class, 'saveContact'])
            ->middleware('throttle:30,1')
            ->name('cart.contact');
        Route::get('/carrito/recuperar/{token}', [CartController::class, 'recover'])
            ->name('cart.recover');
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

        // ── Páginas institucionales con URL traducida por idioma ──────────
        // Slug por idioma en config/localized_pages.php (propuesta ESPASEO v3).
        // La ruta acepta el slug de los 3 idiomas para no romper enlaces ya
        // publicados; el middleware canonical.page emite el 301 al slug del
        // idioma activo y comparte los hreflang reales con el layout.
        // Son DOS rutas por página a propósito:
        //  · la del slug español conserva el nombre de ruta histórico, así
        //    cualquier route('about', ['locale' => x]) que quede en el código,
        //    en un correo o en un enlace viejo sigue generando una URL válida
        //    (y de ahí sale el 301). Con una sola ruta parametrizada eso
        //    reventaba: ->defaults() no alimenta la generación de URLs cuando
        //    el parámetro es obligatorio.
        //  · la parametrizada toma los slugs de los OTROS idiomas. Lleva un
        //    nombre de parámetro propio por página porque RouteCollection
        //    indexa por URI y varias rutas '/{pageSlug}' se sobrescriben entre
        //    ellas, dejando registrada solo la última.
        Route::get('/'.LocalizedPages::slugFor('contact', 'es'), [ContactController::class, 'show'])
            ->middleware('canonical.page')
            ->name('contact');
        Route::get('/{contactSlug}', [ContactController::class, 'show'])
            ->where('contactSlug', LocalizedPages::patternExcludingBase('contact'))
            ->middleware('canonical.page')
            ->name('contact.localized');
        Route::post('/contacto', [ContactController::class, 'submit'])
            ->middleware('throttle:contact')
            ->name('contact.submit');
        Route::get('/gracias', fn () => view('gracias'))->name('contact.thanks');

        // Página de reseñas/comentarios de clientes (Google + Tripadvisor + Web)
        Route::get('/'.LocalizedPages::slugFor('reviews', 'es'), [ReviewController::class, 'index'])
            ->middleware('canonical.page')
            ->name('reviews');
        Route::get('/{reviewsSlug}', [ReviewController::class, 'index'])
            ->where('reviewsSlug', LocalizedPages::patternExcludingBase('reviews'))
            ->middleware('canonical.page')
            ->name('reviews.localized');
        // Envío de reseña propia desde la página de reseñas (moderada antes de publicar)
        Route::post('/resenas/enviar', [ReviewController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('reviews.store');

        // Blog
        Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
        Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

        // El closure se movió a PageController@about al traducir la URL: hacía
        // falta un handler nombrado para poder testearlo y para que el 301
        // canónico y los hreflang salgan del mismo lugar que los de las otras
        // páginas institucionales.
        Route::get('/'.LocalizedPages::slugFor('about', 'es'), [PageController::class, 'about'])
            ->middleware('canonical.page')
            ->name('about');
        Route::get('/{aboutSlug}', [PageController::class, 'about'])
            ->where('aboutSlug', LocalizedPages::patternExcludingBase('about'))
            ->middleware('canonical.page')
            ->name('about.localized');

        // Legal pages
        Route::get('/'.LocalizedPages::slugFor('legal.terms', 'es'), [PageController::class, 'terms'])
            ->middleware('canonical.page')
            ->name('legal.terms');
        Route::get('/{termsSlug}', [PageController::class, 'terms'])
            ->where('termsSlug', LocalizedPages::patternExcludingBase('legal.terms'))
            ->middleware('canonical.page')
            ->name('legal.terms.localized');
        Route::get('/'.LocalizedPages::slugFor('legal.privacy', 'es'), [PageController::class, 'privacy'])
            ->middleware('canonical.page')
            ->name('legal.privacy');
        Route::get('/{privacySlug}', [PageController::class, 'privacy'])
            ->where('privacySlug', LocalizedPages::patternExcludingBase('legal.privacy'))
            ->middleware('canonical.page')
            ->name('legal.privacy.localized');

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

// ─────────────────────────────────────────────────────────────────────────────
// Legacy → locale (2026-07-26).
// La estructura anterior no llevaba prefijo de idioma (/nosotros, /tours,
// /tours/detalle/city-tour-cusco). Al migrar a /es|/en|/pt esas 30 URLs
// quedaron en 404 aunque su gemelo sigue publicado, y Google las conserva
// indexadas: el usuario aterriza en un 404 y se pierde el posicionamiento.
//
// Cualquier path SIN locale que tenga ruta equivalente bajo el locale por
// defecto se redirige con 301. Lo que no tiene equivalente sigue siendo 404
// legítimo — no inventamos destinos.
//
// De paso arregla las cadenas /limaprogramacion/<path-sin-locale> → 301 → 404
// que dejó el .htaccess del 2026-07-02.
//
// IMPORTANTE: Route::fallback() debe quedar SIEMPRE al final del archivo.
// ─────────────────────────────────────────────────────────────────────────────
Route::fallback(function (\Illuminate\Http\Request $request) {
    // Solo navegación. Un POST perdido no se redirige: se cae con 404.
    abort_unless($request->isMethodSafe(), 404);

    $path = trim($request->path(), '/');
    abort_if($path === '', 404);

    $legacy = config('legacy_redirects');
    $key = strtolower($path);
    $qs = $request->getQueryString() ? '?'.$request->getQueryString() : '';

    // ── Era WordPress/WooCommerce (mapa en config/legacy_redirects.php) ──

    // 1. Producto descontinuado o basura declarada → 410 Gone.
    abort_if(in_array($key, $legacy['gone'], true), 410);

    // 2. Mapa explícito URL vieja → equivalente vivo. Va ANTES del corte por
    //    locale porque incluye claves 'en/...' de la estructura inglesa vieja.
    if (isset($legacy['map'][$key])) {
        return redirect()->to($legacy['map'][$key].$qs, 301);
    }

    // 3. Restos de WordPress: feeds, wp-content, wp-admin, xmlrpc. Se borró
    //    el CMS entero, no van a volver.
    abort_if(str_ends_with($key, '/feed'), 410);
    abort_if((bool) preg_match('#^(wp-content|wp-admin|wp-includes|wp-json|wp-login\.php|xmlrpc\.php)(/|$)#', $key), 410);

    // 4. Fichas de WooCommerce: /product/<slug>. El slug se conservó al migrar,
    //    así que si el tour sigue publicado se redirige; si no, se fue.
    if (str_starts_with($key, 'product/')) {
        $slug = explode('/', $key)[1] ?? '';
        $exists = $slug !== '' && \App\Models\Tour::published()->where('slug', $slug)->exists();
        abort_unless($exists, 410);

        return redirect()->to('/'.config('app.locale', 'es').'/tours/detalle/'.$slug.$qs, 301);
    }

    // 5. WordPress también publicaba tours y posts como slug suelto en la raíz
    //    (/laguna-humantay/, /conoce-machu-picchu-si-no-tienes-entrada/).
    //    Regla genérica: si el slug coincide con contenido publicado, va a su
    //    ficha actual. Cubre lo que no esté en el mapa explícito.
    if (! str_contains($key, '/')) {
        $default = config('app.locale', 'es');

        if (\App\Models\Tour::published()->where('slug', $key)->exists()) {
            return redirect()->to("/{$default}/tours/detalle/{$key}".$qs, 301);
        }

        if (\App\Models\BlogPost::published()->where('slug', $key)->exists()) {
            return redirect()->to("/{$default}/blog/{$key}".$qs, 301);
        }
    }

    $segments = explode('/', $path);

    // Ya viene localizado → el 404 es real, no un problema de estructura.
    abort_if(in_array($segments[0], ['es', 'en', 'pt'], true), 404);

    // Prefijos técnicos y ficheros: nunca se mandan a una vista pública.
    $excluded = ['admin', 'api', 'livewire', 'storage', 'webhooks', 'newsletter', '_diag', 'build', 'vendor', 'up'];
    abort_if(in_array($segments[0], $excluded, true), 404);
    abort_if(pathinfo($path, PATHINFO_EXTENSION) !== '', 404);

    $locale = config('app.locale', 'es');
    $candidate = "/{$locale}/{$path}";

    // ¿Existe realmente esa ruta? Se excluye el propio fallback del cotejo,
    // porque si no matchearía cualquier cosa y redirigiríamos 301 a otro 404.
    $probe = \Illuminate\Http\Request::create($candidate, 'GET');
    $exists = collect(Route::getRoutes()->getRoutes())
        ->contains(fn ($route) => ! $route->isFallback && $route->matches($probe));

    abort_unless($exists, 404);

    return redirect()->to(
        $candidate.($request->getQueryString() ? '?'.$request->getQueryString() : ''),
        301
    );
});
