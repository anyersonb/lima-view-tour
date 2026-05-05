@php
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>En mantenimiento — {{ __('seo.site_name') }}</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Albert+Sans:wght@300;400;500;600;700&family=Hedvig+Letters+Serif:opsz@12..24&family=Instrument+Serif:ital@0;1&display=swap">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="font-sans" x-data="{ d:27, h:23, m:59, s:11 }">
    <main class="min-h-screen flex flex-col">
        {{-- top half: image with logo + countdown --}}
        <section class="relative flex-1 min-h-[55vh] text-white">
            <img src="{{ asset('assets/banners/banner-hero.jpg') }}" alt="" class="absolute inset-0 w-full h-full object-cover" loading="eager">
            <div class="absolute inset-0 bg-black/50"></div>
            <div class="relative h-full container mx-auto px-5 lg:px-10 py-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-8">
                <a href="/" class="flex items-center gap-2">
                    <img src="{{ asset('assets/logos/logo.png') }}" alt="Lima View Tours" class="h-12 w-auto">
                </a>
                <div class="flex gap-6 md:gap-10">
                    @foreach ([['d','Days'],['h','Hours'],['m','Minutes'],['s','Seconds']] as [$key,$label])
                        <div class="text-center">
                            <p class="font-display text-5xl md:text-6xl lg:text-7xl leading-none" x-text="String({{ $key }}).padStart(2,'0')">27</p>
                            <p class="mt-2 text-xs md:text-sm opacity-80">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
        {{-- bottom half: title + follow us --}}
        <section class="bg-teal-700 text-white py-14 lg:py-20">
            <div class="container mx-auto px-5 lg:px-10 grid gap-6 lg:grid-cols-[1fr_auto] items-end">
                <div>
                    <h1 class="font-display text-5xl md:text-6xl lg:text-7xl">En Mantenimiento</h1>
                    <p class="mt-3 text-sm text-white/85">We're working hard to improve our website. Stay tuned.</p>
                </div>
                <div class="text-right">
                    <p class="font-display text-2xl">Follow us</p>
                    <ul class="mt-3 inline-flex items-center gap-4">
                        @foreach (['Instagram','Facebook','TikTok','Vimeo'] as $sn)
                            <li><a href="#" aria-label="{{ $sn }}" class="hover:text-orange-400 transition"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg></a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
