@php
    $locale = app()->getLocale();
    $altLocale = $locale === 'es' ? 'en' : 'es';
    $pathWithoutLocale = ltrim(preg_replace('#^/?(es|en|pt)(/|$)#', '', request()->path()), '/');
    // Views whose URL segment is a per-locale TRANSLATED slug (tours/detalle,
    // blog) cannot assume "same path, different locale prefix" — that would
    // point hreflang/canonical at URLs that may not exist. Those views define
    // $localizedAlternates = ['es' => url, 'en' => url, 'pt' => url] with the
    // real per-locale slug BEFORE @extends (Blade appends the parent render
    // at the end of the compiled template, so it sees variables set anywhere
    // in the child). Every other view keeps the old same-path assumption,
    // which is correct for them because their path segments are fixed
    // Spanish words (/nosotros, /contacto…), not a translatable slug column.
    $localizedAlternates = $localizedAlternates ?? null;
    $settings = $siteSettings ?? [];

    $siteName = $settings['site_name'] ?? __('seo.site_name');
    $defaultTitle = $settings['seo_default_title'] ?? __('seo.default_title');
    $defaultDescription = $settings['seo_default_description'] ?? __('seo.default_description');
    $defaultOgImage = $settings['seo_og_image'] ?? null;

    $title = trim($__env->yieldContent('title'));
    $description = trim($__env->yieldContent('description'));
    if ($title === '') { $title = $defaultTitle; }
    if ($description === '') { $description = $defaultDescription; }

    $ogImage = trim($__env->yieldContent('og_image'));
    if ($ogImage === '') {
        $ogImage = $defaultOgImage
            ? (\Illuminate\Support\Str::startsWith($defaultOgImage, ['http', '/']) ? $defaultOgImage : asset($defaultOgImage))
            : asset('assets/banners/banner-hero.jpg');
    }

    $gaId = $settings['seo_google_analytics_id'] ?? null;
    $gtmId = $settings['seo_gtm_id'] ?? null;
    $fbPixel = $settings['seo_facebook_pixel'] ?? null;
    $googleVerify = $settings['seo_google_site_verification'] ?? null;
    $bingVerify = $settings['seo_bing_site_verification'] ?? null;

    // Cookie consent — when banner is disabled by admin, analytics loads without requiring consent
    $cookieBannerEnabled = (bool) \App\Models\Setting::get('cookie_banner_enabled', true);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="ltr" class="no-js">
<head>
    <meta charset="UTF-8">
    <script>document.documentElement.classList.remove('no-js');document.documentElement.classList.add('js');</script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    {{-- Evita el "flash" de elementos Alpine (menú/drawer) antes de inicializar --}}
    <style>[x-cloak]{display:none!important}</style>

    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    @if (env('NOINDEX', false))
        <meta name="robots" content="noindex,nofollow">
    @else
        <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
    @endif
    <meta name="theme-color" content="#15474B">
    <meta name="author" content="{{ $siteName }}">
    @isset($settings['seo_default_keywords'])
        <meta name="keywords" content="{{ $settings['seo_default_keywords'] }}">
    @endisset
    @if ($googleVerify)
        <meta name="google-site-verification" content="{{ $googleVerify }}">
    @endif
    @if ($bingVerify)
        <meta name="msvalidate.01" content="{{ $bingVerify }}">
    @endif

    <link rel="canonical" href="{{ url()->current() }}">
    @if ($localizedAlternates)
        <link rel="alternate" hreflang="es"      href="{{ $localizedAlternates['es'] }}">
        <link rel="alternate" hreflang="en"      href="{{ $localizedAlternates['en'] }}">
        <link rel="alternate" hreflang="pt"      href="{{ $localizedAlternates['pt'] }}">
        <link rel="alternate" hreflang="x-default" href="{{ $localizedAlternates['es'] }}">
    @else
        <link rel="alternate" hreflang="es"      href="{{ url('/es/' . $pathWithoutLocale) }}">
        <link rel="alternate" hreflang="en"      href="{{ url('/en/' . $pathWithoutLocale) }}">
        <link rel="alternate" hreflang="pt"      href="{{ url('/pt/' . $pathWithoutLocale) }}">
        <link rel="alternate" hreflang="x-default" href="{{ url('/es/' . $pathWithoutLocale) }}">
    @endif

    {{-- GEO meta tags (solo si hay coordenadas configuradas) --}}
    @php
        $geoRegionCode = $settings['geo_region_code'] ?? 'PE-LIM';
        $geoPlacename  = ($settings['geo_city'] ?? 'Lima') . ', ' . ($settings['geo_country'] ?? 'PE');
        $geoLat        = $settings['geo_latitude']  ?? null;
        $geoLong       = $settings['geo_longitude'] ?? null;
    @endphp
    <meta name="geo.region"    content="{{ $geoRegionCode }}">
    <meta name="geo.placename" content="{{ $geoPlacename }}">
    @if ($geoLat && $geoLong)
        <meta name="geo.position" content="{{ $geoLat }};{{ $geoLong }}">
        <meta name="ICBM"         content="{{ $geoLat }}, {{ $geoLong }}">
    @endif

    <meta property="og:type" content="website">
    @php
        $ogLocaleMap = ['es' => 'es_PE', 'en' => 'en_US', 'pt' => 'pt_BR'];
        $ogLocale    = $ogLocaleMap[$locale] ?? 'es_PE';
    @endphp
    <meta property="og:locale" content="{{ $ogLocale }}">
    @foreach ($ogLocaleMap as $l => $ol)
        @if ($l !== $locale)
            <meta property="og:locale:alternate" content="{{ $ol }}">
        @endif
    @endforeach
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Albert+Sans:wght@300;400;500;600;700&family=Hedvig+Letters+Serif:opsz@12..24&family=Instrument+Serif:ital@0;1&display=swap">

    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css">

    <x-jsonld />
    @php
        // Configuración → SEO → "Schema global del sitio". Vacío por defecto:
        // solo imprime si el equipo SEO lo carga a mano. Convive con
        // <x-jsonld /> (Organization/WebSite automático) — el lote B decide
        // si lo reemplaza.
        $globalSchemaJsonLd = \App\Models\Setting::getLocalized('schema_jsonld_global');
    @endphp
    @if ($globalSchemaJsonLd)
        <script type="application/ld+json">{!! $globalSchemaJsonLd !!}</script>
    @endif
    @stack('schema')

    {{--
        =====================================================================
        ANALYTICS — Google Consent Mode v2 + Facebook Pixel gating
        =====================================================================
        When $cookieBannerEnabled is true:
          - GTM/GA4 load with consent default = denied (Consent Mode v2).
            They fire only conversion/analytics events after the user grants
            consent via lvt-consent-granted or a stored 'granted' value.
          - Facebook Pixel is NOT initialized at all until consent is granted.

        When $cookieBannerEnabled is false (admin disabled the banner):
          - Everything loads unconditionally, exactly as before.

        Reference: https://developers.google.com/tag-platform/security/guides/consent
        =====================================================================
    --}}
    @if ($gtmId || $gaId)
        @if ($cookieBannerEnabled)
            {{-- Step 1: initialize dataLayer and set Consent Mode v2 DEFAULTS to denied
                 This must happen BEFORE the GTM/gtag scripts load. --}}
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                // Default: all consent types denied until user explicitly accepts
                gtag('consent', 'default', {
                    ad_storage:            'denied',
                    analytics_storage:     'denied',
                    ad_user_data:          'denied',
                    ad_personalization:    'denied',
                    wait_for_update:       500
                });
            </script>
        @else
            {{-- Banner disabled: initialize dataLayer without consent restrictions --}}
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
            </script>
        @endif

        {{-- Step 2: load GTM (it reads the consent state set above) --}}
        @if ($gtmId)
            <script>
                (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $gtmId }}');
            </script>
        @endif

        {{-- Step 3: load GA4 (inherits consent state from dataLayer above) --}}
        @if ($gaId)
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
            <script>
                gtag('js', new Date());
                gtag('config', '{{ $gaId }}', { anonymize_ip: true });
            </script>
        @endif

        {{-- Step 4: consent UPDATE logic — runs on page load and on user accept event.
             When banner is enabled: update consent to 'granted' if already stored or
             when the user clicks Accept (lvt-consent-granted event).
             When banner is disabled: skip (already loaded without restriction). --}}
        @if ($cookieBannerEnabled)
            <script>
                (function () {
                    function grantConsent() {
                        gtag('consent', 'update', {
                            ad_storage:         'granted',
                            analytics_storage:  'granted',
                            ad_user_data:       'granted',
                            ad_personalization: 'granted'
                        });
                    }
                    // If user already accepted in a previous session, update immediately
                    if (localStorage.getItem('lvt_cookie_consent') === 'granted') {
                        grantConsent();
                    }
                    // Listen for Accept click fired by the cookie banner component
                    window.addEventListener('lvt-consent-granted', grantConsent);
                })();
            </script>
        @endif
    @endif

    {{--
        Facebook Pixel — no Consent Mode API; must NOT fire until consent is given.
        When banner is enabled: register a loader function and call it only on consent.
        When banner is disabled: load unconditionally as before.
    --}}
    @if ($fbPixel)
        @if ($cookieBannerEnabled)
            <script>
                (function () {
                    function loadFbPixel() {
                        if (window._fbPixelLoaded) return;
                        window._fbPixelLoaded = true;
                        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
                        fbq('init', '{{ $fbPixel }}');
                        fbq('track', 'PageView');
                    }
                    // Load immediately if consent already granted in a previous session
                    if (localStorage.getItem('lvt_cookie_consent') === 'granted') {
                        loadFbPixel();
                    }
                    // Load when the user accepts via the banner
                    window.addEventListener('lvt-consent-granted', loadFbPixel);
                })();
            </script>
        @else
            {{-- Banner disabled: load unconditionally --}}
            <script>
                !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
                fbq('init', '{{ $fbPixel }}');
                fbq('track', 'PageView');
            </script>
            <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{ $fbPixel }}&ev=PageView&noscript=1"/></noscript>
        @endif
    @endif

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="bg-white">
    @if ($gtmId)
        {{-- GTM noscript fallback — only render when consent has been granted
             (or banner disabled). A noscript fallback without JS-based consent
             gating could set cookies without user action; rendering it here is
             acceptable because users without JS cannot interact with the banner
             either. Consent Mode v2 handles the JS path above. --}}
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif

    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[60] focus:bg-orange-500 focus:text-white focus:px-4 focus:py-2 focus:rounded-lg">
        {{ __('nav.skip_to_content') }}
    </a>

    <x-header :variant="trim($__env->yieldContent('header_variant')) ?: 'solid'" />

    <main id="main" role="main">
        @yield('content')
    </main>

    <x-footer />

    {{-- Botón flotante WhatsApp — posición 100% inline para funcionar sin rebuild de Tailwind --}}
    @php $waNumber = \App\Models\Setting::get('whatsapp') ?: '51925886725'; @endphp
    <a href="https://wa.me/{{ $waNumber }}"
       target="_blank"
       rel="noopener noreferrer"
       aria-label="WhatsApp"
       style="position:fixed;bottom:24px;right:20px;z-index:9000;width:56px;height:56px;border-radius:9999px;background-color:#25D366;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(0,0,0,0.3);transition:transform .2s ease;color:#fff;text-decoration:none;"
       onmouseover="this.style.transform='scale(1.1)'"
       onmouseout="this.style.transform='scale(1)'">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.978-1.045zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.148-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.017-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.247-.694.247-1.289.173-1.413z"/>
        </svg>
    </a>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js" defer></script>

    @stack('scripts')

    {{-- Cookie consent banner (shown when no prior decision + admin has it enabled) --}}
    @if ($cookieBannerEnabled)
        <x-cookie-banner />
    @endif
</body>
</html>
