@php
    $locale = app()->getLocale();
    $altLocale = $locale === 'es' ? 'en' : 'es';
    $pathWithoutLocale = ltrim(preg_replace('#^/?(es|en)(/|$)#', '', request()->path()), '/');
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
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
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
    <link rel="alternate" hreflang="es" href="{{ url('/es/' . $pathWithoutLocale) }}">
    <link rel="alternate" hreflang="en" href="{{ url('/en/' . $pathWithoutLocale) }}">
    <link rel="alternate" hreflang="x-default" href="{{ url('/es/' . $pathWithoutLocale) }}">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="{{ $locale === 'es' ? 'es_PE' : 'en_US' }}">
    <meta property="og:locale:alternate" content="{{ $locale === 'es' ? 'en_US' : 'es_PE' }}">
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
    @stack('schema')

    @if ($gtmId)
        <script>
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $gtmId }}');
        </script>
    @endif

    @if ($gaId)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ $gaId }}', { anonymize_ip: true });
        </script>
    @endif

    @if ($fbPixel)
        <script>
            !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '{{ $fbPixel }}');
            fbq('track', 'PageView');
        </script>
        <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{ $fbPixel }}&ev=PageView&noscript=1"/></noscript>
    @endif

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="bg-white">
    @if ($gtmId)
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif

    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[60] focus:bg-orange-500 focus:text-white focus:px-4 focus:py-2 focus:rounded-lg">
        {{ __('nav.skip_to_content') }}
    </a>

    <x-header />

    <main id="main" role="main">
        @yield('content')
    </main>

    <x-footer />

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js" defer></script>
    <script defer>
        document.addEventListener('DOMContentLoaded', function () {
            const initOwl = function () {
                if (typeof jQuery === 'undefined' || typeof jQuery.fn.owlCarousel === 'undefined') { return setTimeout(initOwl, 80); }
                const $ = jQuery;

                $('[data-owl-tours]').owlCarousel({
                    loop: true, margin: 24, nav: true, dots: true, autoplay: true, autoplayHoverPause: true, autoplayTimeout: 5500, smartSpeed: 700,
                    navText: ['<span aria-label="Anterior">&lsaquo;</span>', '<span aria-label="Siguiente">&rsaquo;</span>'],
                    responsive: { 0: { items: 1 }, 640: { items: 2 }, 1024: { items: 3 }, 1536: { items: 4 } },
                });

                $('[data-owl-tours-wide]').owlCarousel({
                    loop: true, margin: 24, nav: true, dots: true, autoplay: true, autoplayHoverPause: true, autoplayTimeout: 5500, smartSpeed: 700,
                    navText: ['<span aria-label="Anterior">&lsaquo;</span>', '<span aria-label="Siguiente">&rsaquo;</span>'],
                    responsive: { 0: { items: 1 }, 1024: { items: 2 } },
                });

                $('[data-owl-experiences]').owlCarousel({
                    loop: true, margin: 24, nav: false, dots: true, autoplay: true, autoplayTimeout: 6000, smartSpeed: 700,
                    responsive: { 0: { items: 1 }, 640: { items: 2 }, 1024: { items: 3 }, 1536: { items: 4 } },
                });

                $('[data-owl-testimonials]').owlCarousel({
                    loop: true, margin: 20, nav: true, dots: true, autoplay: true, autoplayTimeout: 6500, smartSpeed: 700,
                    navText: ['<span aria-label="Anterior">&lsaquo;</span>', '<span aria-label="Siguiente">&rsaquo;</span>'],
                    responsive: { 0: { items: 1 }, 640: { items: 2 }, 1024: { items: 3 }, 1280: { items: 4 } },
                });

                $('[data-owl-offers]').owlCarousel({
                    loop: true, margin: 24, nav: true, dots: true, autoplay: true, autoplayTimeout: 6500, smartSpeed: 700,
                    navText: ['<span aria-label="Anterior">&lsaquo;</span>', '<span aria-label="Siguiente">&rsaquo;</span>'],
                    responsive: { 0: { items: 1 }, 768: { items: 2 }, 1024: { items: 3 } },
                });

                $('[data-owl-related]').owlCarousel({
                    loop: true, margin: 20, nav: false, dots: true, autoplay: false, smartSpeed: 600,
                    responsive: { 0: { items: 1 }, 640: { items: 2 }, 1024: { items: 3 }, 1536: { items: 4 } },
                });

                $('[data-owl-gallery]').owlCarousel({
                    loop: true, margin: 12, nav: true, dots: false, items: 1,
                    navText: ['<span aria-label="Anterior">&lsaquo;</span>', '<span aria-label="Siguiente">&rsaquo;</span>'],
                });
            };
            initOwl();
        });
    </script>

    @stack('scripts')
</body>
</html>
