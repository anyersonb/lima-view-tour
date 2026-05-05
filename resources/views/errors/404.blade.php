@php
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — {{ __('seo.site_name') }}</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Albert+Sans:wght@300;400;500;600;700&family=Hedvig+Letters+Serif:opsz@12..24&family=Instrument+Serif:ital@0;1&display=swap">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-cream-100 text-teal-800 font-sans">
    <main class="min-h-[80vh] flex flex-col items-center justify-center px-5 py-16 text-center relative overflow-hidden">
        {{-- decorative bg lines --}}
        <svg class="absolute inset-0 w-full h-full opacity-[0.06] -z-0 text-teal-700" viewBox="0 0 1200 600" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
            <path d="M0 460 Q200 380 400 420 T800 380 T1200 410" fill="none" stroke="currentColor" stroke-width="1.5"/>
            <path d="M0 500 Q200 430 400 470 T800 420 T1200 460" fill="none" stroke="currentColor" stroke-width="1.5"/>
            <path d="M120 200 q40 -120 200 -100 q160 20 200 120 q40 100 -100 140 q-160 40 -240 -40 q-80 -80 -60 -120z" fill="none" stroke="currentColor" stroke-width="1.5"/>
        </svg>
        <a href="{{ route('home', ['locale' => $locale]) }}" class="relative z-10 inline-flex items-center gap-2">
            <img src="{{ asset('assets/logos/logo-pair.png') }}" alt="Lima View Tours" class="h-10 w-auto">
        </a>
        <h1 class="relative z-10 mt-12 font-display text-[180px] md:text-[220px] leading-none text-teal-800">404</h1>
        <p class="relative z-10 mt-2 font-display text-2xl md:text-3xl text-teal-800">Oops! Hubo un error al cargar la página</p>
        <p class="relative z-10 mt-4 max-w-xl text-sm text-teal-800/75 leading-relaxed">
            Dictum adipiscing sed euismod eget. Eget ullamcorper eget hac ultrices laoreet venenatis. Tortor nibh eget malesuada ullamcorper feugiat integer nam ultricies.
        </p>
        <a href="{{ route('home', ['locale' => $locale]) }}" class="btn--primary relative z-10 mt-8">Volver a inicio</a>
    </main>
    <footer class="bg-teal-700 text-white py-8 text-center">
        <p class="text-sm font-medium tracking-wide">Follow us</p>
        <ul class="mt-3 inline-flex items-center gap-4">
            <li><a href="#" aria-label="Instagram"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.2c3.2 0 3.6 0 4.8.1 1.2.1 1.8.2 2.2.4.6.2 1 .5 1.4.9.4.4.7.9.9 1.4.2.5.4 1.1.4 2.2.1 1.2.1 1.6.1 4.8s0 3.6-.1 4.8c-.1 1.2-.2 1.8-.4 2.2-.2.6-.5 1-.9 1.4-.4.4-.9.7-1.4.9-.5.2-1.1.4-2.2.4-1.2.1-1.6.1-4.8.1s-3.6 0-4.8-.1c-1.2-.1-1.8-.2-2.2-.4-.6-.2-1-.5-1.4-.9-.4-.4-.7-.9-.9-1.4-.2-.5-.4-1.1-.4-2.2-.1-1.2-.1-1.6-.1-4.8s0-3.6.1-4.8c.1-1.2.2-1.8.4-2.2.2-.6.5-1 .9-1.4.4-.4.9-.7 1.4-.9.5-.2 1.1-.4 2.2-.4 1.2-.1 1.6-.1 4.8-.1zm0 5.5a4.3 4.3 0 100 8.6 4.3 4.3 0 000-8.6zm0 7.1a2.8 2.8 0 110-5.6 2.8 2.8 0 010 5.6zm5.5-7.3a1 1 0 11-2 0 1 1 0 012 0z"/></svg></a></li>
            <li><a href="#" aria-label="Facebook" class="text-orange-400"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="11"/><path fill="#fff" d="M13.4 17.6v-5h1.7l.3-2h-2v-1.3c0-.6.2-1 1-1h1V6.4s-.5-.1-1.5-.1c-1.7 0-2.7 1-2.7 2.7v1.6H9.5v2h1.7v5h2.2z"/></svg></a></li>
            <li><a href="#" aria-label="TikTok"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19.6 6.7a4.8 4.8 0 01-2.7-1.7 4.8 4.8 0 01-1-2.7h-3.4v13.4a2.7 2.7 0 11-2.7-2.7c.3 0 .6 0 .9.1V9.6a6.1 6.1 0 00-.9-.1 6.1 6.1 0 106.1 6.1V9.4a8.2 8.2 0 003.7.9V6.7z"/></svg></a></li>
            <li><a href="#" aria-label="Vimeo"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M22 7.4c-.1 2-1.5 4.7-4.2 8.2-2.8 3.6-5.2 5.4-7.1 5.4-1.2 0-2.2-1.1-3-3.3-.5-1.9-1.1-3.8-1.6-5.7-.6-2.2-1.2-3.3-1.9-3.3-.1 0-.6.3-1.4.9l-.8-1.1c.9-.8 1.8-1.6 2.6-2.4 1.2-1 2.1-1.5 2.7-1.6 1.4-.1 2.3.9 2.6 3 .4 2.3.6 3.7.8 4.2.4 1.6.9 2.5 1.4 2.5.4 0 1-.6 1.8-1.9.8-1.2 1.2-2.2 1.3-2.8.1-1.1-.4-1.7-1.3-1.7-.5 0-.9.1-1.4.3 1-3.1 2.8-4.6 5.4-4.5 2 .1 3 1.4 2.9 3.8z"/></svg></a></li>
        </ul>
    </footer>
</body>
</html>
