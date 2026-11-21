@php
    // El 404 se renderiza fuera del middleware de idioma: el locale sale del primer segmento de la URL.
    $seg = request()->segment(1);
    $locale = in_array($seg, ['es', 'en', 'pt'], true) ? $seg : app()->getLocale();
    app()->setLocale($locale);
    // Texto por clave (errors.404_*) si backend la añade en lang/; si no, el fallback es/en/pt de acá.
    $t = fn (string $key, array $fb) => trans()->has($key) ? __($key) : ($fb[$locale] ?? $fb['es']);
    $e404Title = $t('errors.404_title', [
        'es' => 'No encontramos esa página',
        'en' => "We couldn't find that page",
        'pt' => 'Não encontramos esta página',
    ]);
    $e404Body = $t('errors.404_body', [
        'es' => 'Puede que el enlace haya cambiado o que la página ya no exista. Vuelve al inicio o explora nuestros tours en Lima, Cusco, Ica y más.',
        'en' => 'The link may have changed or the page may no longer exist. Go back home or browse our tours in Lima, Cusco, Ica and more.',
        'pt' => 'O link pode ter mudado ou a página pode não existir mais. Volte ao início ou conheça nossos tours em Lima, Cusco, Ica e mais.',
    ]);
    $e404Home = $t('errors.404_home', ['es' => 'Volver a inicio', 'en' => 'Back to home', 'pt' => 'Voltar ao início']);
    $e404Tours = $t('errors.404_tours', ['es' => 'Ver tours', 'en' => 'Browse tours', 'pt' => 'Ver tours']);
    $e404Page = $t('errors.404_page_title', ['es' => 'Página no encontrada', 'en' => 'Page not found', 'pt' => 'Página não encontrada']);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 · {{ $e404Page }} — {{ __('seo.site_name') }}</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Albert+Sans:wght@300;400;500;600;700&family=Hedvig+Letters+Serif:opsz@12..24&family=Instrument+Serif:ital@0;1&display=swap">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/cro-fixes.css') }}?v=20261119b">
    <link rel="stylesheet" href="{{ asset('css/redesign.css') }}?v=20261121h">
</head>
<body class="bg-cream-100 text-teal-800 font-sans">
    {{-- 18-nov: ahora con el header y el footer reales del sitio (mismas redes, métodos de pago y datos); se quitaron el
         logo duplicado/caja punteada y el footer propio con redes en "#" (incluida Vimeo, que no existe en el sitio). --}}
    {{-- Fail-safe: si la BD no responde (o está vacía), la 404 se pinta igual sin header/footer en vez de dar 500. --}}
    @php $lvtChromeOk = rescue(fn () => \Illuminate\Support\Facades\Schema::hasTable('settings'), false, false); @endphp
    @if ($lvtChromeOk)
        <x-header variant="solid" />
    @endif

    <main id="main" role="main" class="lvt-404 relative overflow-hidden px-5 py-14 text-center flex flex-col items-center justify-center">
        {{-- decorative bg lines --}}
        <svg class="absolute inset-0 w-full h-full opacity-[0.06] text-teal-700" viewBox="0 0 1200 600" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
            <path d="M0 460 Q200 380 400 420 T800 380 T1200 410" fill="none" stroke="currentColor" stroke-width="1.5"/>
            <path d="M0 500 Q200 430 400 470 T800 420 T1200 460" fill="none" stroke="currentColor" stroke-width="1.5"/>
            <path d="M120 200 q40 -120 200 -100 q160 20 200 120 q40 100 -100 140 q-160 40 -240 -40 q-80 -80 -60 -120z" fill="none" stroke="currentColor" stroke-width="1.5"/>
        </svg>
        <p class="lvt-404__code relative z-10 font-display leading-none text-teal-800" aria-hidden="true">404</p>
        <h1 class="relative z-10 mt-2 font-display text-3xl md:text-4xl text-teal-800">{{ $e404Title }}</h1>
        <p class="relative z-10 mt-4 max-w-xl text-base text-teal-800/80 leading-relaxed">
            {{ $e404Body }}
        </p>
        <div class="relative z-10 mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ route('home', ['locale' => $locale]) }}" class="btn--primary" data-ga-cta="volver_inicio" data-ga-location="404">{{ $e404Home }}</a>
            <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="btn--primary-outline" data-ga-cta="ver_tours" data-ga-location="404">{{ $e404Tours }}</a>
        </div>
    </main>

    @if ($lvtChromeOk)
        <x-footer />
    @endif
</body>
</html>
