@php
    $locale = app()->getLocale();
    $altLocale = $locale === 'es' ? 'en' : 'es';
    $pathWithoutLocale = ltrim(preg_replace('#^/?(es|en)(/|$)#', '', request()->path()), '/');
    $title = trim($__env->yieldContent('title'));
    $description = trim($__env->yieldContent('description'));
    if ($title === '') { $title = __('seo.default_title'); }
    if ($description === '') { $description = __('seo.default_description'); }
    $ogImage = trim($__env->yieldContent('og_image'));
    if ($ogImage === '') { $ogImage = asset('images/og-default.jpg'); }
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <meta name="theme-color" content="#0ea5e9">
    <meta name="author" content="{{ __('seo.site_name') }}">

    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="alternate" hreflang="es" href="{{ url('/es/' . $pathWithoutLocale) }}">
    <link rel="alternate" hreflang="en" href="{{ url('/en/' . $pathWithoutLocale) }}">
    <link rel="alternate" hreflang="x-default" href="{{ url('/es/' . $pathWithoutLocale) }}">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="{{ $locale === 'es' ? 'es_PE' : 'en_US' }}">
    <meta property="og:locale:alternate" content="{{ $locale === 'es' ? 'en_US' : 'es_PE' }}">
    <meta property="og:site_name" content="{{ __('seo.site_name') }}">
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

    <x-jsonld />
    @stack('schema')

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="bg-white">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[60] focus:bg-brand-600 focus:text-white focus:px-4 focus:py-2 focus:rounded-lg">
        {{ __('nav.skip_to_content') }}
    </a>

    <x-header />

    <main id="main" role="main">
        @yield('content')
    </main>

    <x-footer />

    @stack('scripts')
</body>
</html>
