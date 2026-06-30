@php
    $locale = app()->getLocale();

    // Gracias page — editable texts with fallbacks
    $graciasBadge    = \App\Models\Setting::get('gracias_badge_' . $locale)    ?: 'Tu mensaje fue enviado';
    $graciasTitle    = \App\Models\Setting::get('gracias_title_' . $locale)    ?: "Gracias por\ncontactarnos";
    $graciasBody     = \App\Models\Setting::get('gracias_body_' . $locale)     ?: 'Hemos captado tus datos de forma segura, pronto nos pondremos en contacto contigo.';
    $graciasCta      = \App\Models\Setting::get('gracias_cta_' . $locale)      ?: 'Volver a inicio';

    // Gracias page — editable images with fallbacks
    $mediaOr = function ($key, $fallback) {
        $v = \App\Models\Setting::get($key);
        if (is_array($v)) { $v = $v[0] ?? ''; }
        $v = trim((string) $v);
        return ($v !== '' && $v !== '[]' && $v !== '""')
            ? \Illuminate\Support\Facades\Storage::disk('media')->url($v)
            : asset($fallback);
    };
    $graciasBannerUrl = $mediaOr('gracias_banner_image', 'assets/banners/Rectangle 19218.jpg');
    $graciasLogoUrl   = $mediaOr('gracias_logo_image', 'assets/logos/logo.png');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gracias por contactarnos — {{ __('seo.site_name') }}</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Albert+Sans:wght@300;400;500;600;700&family=Hedvig+Letters+Serif:opsz@12..24&family=Instrument+Serif:ital@0;1&display=swap">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="font-sans">
    <main class="min-h-screen grid lg:grid-cols-2">
        <section class="bg-cream-100 flex flex-col justify-center px-8 lg:px-20 py-16">
            <p class="text-[11px] uppercase tracking-[0.25em] text-teal-800/70 font-semibold">{{ $graciasBadge }}</p>
            <h1 class="mt-4 font-display text-5xl lg:text-7xl text-teal-800 leading-[1.05]">{!! nl2br(e($graciasTitle)) !!}</h1>
            <p class="mt-5 text-sm text-teal-800/75 leading-relaxed max-w-md">{{ $graciasBody }}</p>
            <a href="{{ url('/' . $locale) }}" class="btn--primary mt-8 self-start">{{ $graciasCta }}</a>
            <div class="mt-12">
                <p class="font-display text-xl text-teal-800">Follow us</p>
                <ul class="mt-3 flex items-center gap-4 text-orange-500">
                    @foreach (['Instagram','Facebook','TikTok','Vimeo'] as $sn)
                        <li><a href="#" aria-label="{{ $sn }}" class="hover:text-orange-600 transition"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg></a></li>
                    @endforeach
                </ul>
            </div>
        </section>
        <section class="relative min-h-[40vh] lg:min-h-screen">
            <img src="{{ $graciasBannerUrl }}" alt="" class="absolute inset-0 w-full h-full object-cover" loading="lazy">
            <div class="absolute inset-0 bg-gradient-to-b from-black/0 to-black/30"></div>
            <div class="relative h-full grid place-items-center text-white">
                <img src="{{ $graciasLogoUrl }}" alt="Lima View Tours" class="h-16 w-auto">
            </div>
        </section>
    </main>
</body>
</html>
