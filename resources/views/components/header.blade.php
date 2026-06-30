@php
    $locale      = app()->getLocale();
    $variant     = $variant ?? 'solid'; // 'solid' | 'transparent'
    $contactPhone = \App\Models\Setting::get('contact_phone') ?: '+51 925 886 725';
    $supportPhone = $contactPhone;
    $regions     = ['lima', 'ica', 'cusco'];

    // Datos para el menú móvil
    $whatsapp      = \App\Models\Setting::get('whatsapp') ?: preg_replace('/\D/', '', $contactPhone);
    $tripadvisor   = \App\Models\Setting::get('social_tripadvisor') ?: 'https://www.tripadvisor.com';
    $googleReviews = \App\Models\Setting::get('social_google_reviews') ?: '#';
    $trivago       = \App\Models\Setting::get('social_trivago') ?: '#';
    $isEn        = $locale === 'en';
    // Helper de traducción inline ES / EN / PT
    $L = fn (string $es, string $en, string $pt): string => $locale === 'pt' ? $pt : ($locale === 'en' ? $en : $es);
    $labelOpen   = $L('Abrir menú', 'Open menu', 'Abrir menu');
    $labelClose  = $L('Cerrar menú', 'Close menu', 'Fechar menu');

    // Contador del carrito
    try {
        $cartCount = app(\App\Services\CartService::class)->count();
    } catch (\Throwable $e) {
        $cartCount = 0;
    }
@endphp

<header class="site-header" data-variant="{{ $variant }}" role="banner"
        x-data="{ open: false, lang: false, regions: { lima: false, ica: false, cusco: false }, mTours: false, mResenas: false, scrolled: false }"
        @keydown.escape.window="open = false"
        x-init="scrolled = window.scrollY > 40"
        @scroll.window.passive="scrolled = window.scrollY > 40"
        :class="{ 'is-scrolled': scrolled, 'menu-open': open }">

    {{-- ── Barra principal ── --}}
    <div class="site-header__bar flex items-center px-3 sm:px-5 lg:px-10 gap-3 sm:gap-4">

        {{-- Logo: ícono espiral + wordmark --}}
        <a href="{{ route('home', ['locale' => $locale]) }}"
           class="site-header__logo flex items-center shrink-0 py-3"
           aria-label="Lima View Tours — Inicio">
            <img src="{{ asset('assets/logos/logo.png') }}"
                 alt="Lima View Tours"
                 class="h-8 sm:h-10 lg:h-12 w-auto select-none"
                 draggable="false"
                 width="295" height="91">
        </a>

        {{-- Spacer --}}
        <div class="flex-1"></div>

        {{-- Navegación horizontal (desktop) — mismos accesos del menú --}}
        <nav class="site-header__nav hidden lg:flex items-center gap-7"
             aria-label="{{ $isEn ? 'Main navigation' : 'Navegación principal' }}">
            <a href="{{ route('home', ['locale' => $locale]) }}" class="site-header__nav-link">{{ $L('Inicio', 'Home', 'Início') }}</a>
            <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="site-header__nav-link">{{ $L('Tours', 'Tours', 'Tours') }}</a>
            <a href="{{ route('blog.index', ['locale' => $locale]) }}" class="site-header__nav-link">Blog</a>
            <a href="{{ route('reviews', ['locale' => $locale]) }}" class="site-header__nav-link">{{ $L('Reseñas', 'Reviews', 'Avaliações') }}</a>
            <a href="{{ url('/' . $locale . '/nosotros') }}" class="site-header__nav-link">{{ $L('Nosotros', 'About', 'Sobre nós') }}</a>
            <a href="{{ route('contact', ['locale' => $locale]) }}" class="site-header__nav-link">{{ $L('Contacto', 'Contact', 'Contato') }}</a>
        </nav>

        {{-- Spacer --}}
        <div class="flex-1"></div>

        {{-- Cluster derecho --}}
        <div class="flex items-center gap-2 sm:gap-3">

            {{-- Lang switcher --}}
            <x-lang-switcher />

            {{-- Divisor --}}
            <span class="site-header__divider" aria-hidden="true"></span>

            {{-- Carrito con badge --}}
            <a href="{{ route('cart.index', ['locale' => $locale]) }}"
               class="site-header__cart-btn relative p-2 rounded-full transition-colors hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
               aria-label="{{ $cartCount }} {{ $isEn ? ($cartCount === 1 ? 'item in cart' : 'items in cart') : ($cartCount === 1 ? 'ítem en el carrito' : 'ítems en el carrito') }}">
                {{-- Ícono carrito outline --}}
                <svg class="w-6 h-6 lg:w-7 lg:h-7" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75M7.5 14.25h11.218a.75.75 0 00.74-.617l1.5-9A.75.75 0 0019.218 4.5H5.106M7.5 14.25L5.106 4.5M9 19.5a1.125 1.125 0 11-2.25 0 1.125 1.125 0 012.25 0zm9 0a1.125 1.125 0 11-2.25 0 1.125 1.125 0 012.25 0z"/>
                </svg>
                {{-- Badge contador --}}
                <span class="site-header__badge" aria-hidden="true">{{ $cartCount }}</span>
            </a>

            {{-- Divisor --}}
            <span class="site-header__divider" aria-hidden="true"></span>

            {{-- Iniciar sesión / Mi cuenta (visible en desktop) --}}
            @auth('customer')
                <a href="{{ route('customer.account', ['locale' => $locale]) }}"
                   class="hidden lg:flex"
                   style="align-items:center;gap:7px;padding:7px 14px;border-radius:9999px;font-size:13px;font-weight:700;color:#fff;border:1px solid rgba(255,255,255,.35);text-decoration:none;transition:background .15s;white-space:nowrap;"
                   onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='transparent'">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0"/></svg>
                    <span>{{ __('customer.my_account') }}</span>
                </a>
            @else
                <a href="{{ route('customer.login', ['locale' => $locale]) }}"
                   class="hidden lg:flex"
                   style="align-items:center;gap:7px;padding:7px 14px;border-radius:9999px;font-size:13px;font-weight:700;color:#fff;border:1px solid rgba(255,255,255,.35);text-decoration:none;transition:background .15s;white-space:nowrap;"
                   onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='transparent'">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0"/></svg>
                    <span>{{ $L('Iniciar sesión', 'Sign in', 'Entrar') }}</span>
                </a>
            @endauth

            {{-- Divisor --}}
            <span class="site-header__divider hidden lg:block" aria-hidden="true"></span>

            {{-- Botón hamburguesa (todos los breakpoints) --}}
            <button type="button"
                    class="site-header__hamburger p-2 rounded-full transition-colors hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-controls="mobile-menu"
                    :aria-label="open ? '{{ $labelClose }}' : '{{ $labelOpen }}'">
                <svg class="w-6 h-6 lg:w-7 lg:h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path x-show="!open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    <path x-show="open"  stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- ── Backdrop (todos los breakpoints) ── --}}
    <div x-show="open" x-cloak x-transition.opacity
         class="fixed inset-0 z-[60] bg-teal-950/50 backdrop-blur-sm"
         @click="open = false" aria-hidden="true"></div>

    {{-- ── Drawer de navegación ── --}}
    <div id="mobile-menu"
         x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="site-menu fixed top-0 right-0 z-[70] w-[88%] max-w-[400px] bg-cream-50 text-teal-800 shadow-2xl flex flex-col overflow-y-auto overscroll-contain"
         style="height:100vh; height:100dvh;"
         role="dialog" aria-modal="true"
         aria-label="{{ $isEn ? 'Main menu' : 'Menú principal' }}">

        {{-- Cabecera del drawer: logo + cerrar --}}
        <div class="flex items-center justify-between px-5 pt-5 pb-4 border-b border-teal-800/10">
            <a href="{{ route('home', ['locale' => $locale]) }}" class="leading-none" @click="open = false" aria-label="Lima View Tours">
                <span class="block font-display text-[22px] tracking-[0.12em] text-teal-800 leading-none">LIMA</span>
                <span class="block text-[10px] tracking-[0.35em] text-orange-600 font-semibold mt-1">VIEW TOURS</span>
            </a>
            <button type="button" @click="open = false"
                    class="w-10 h-10 rounded-full bg-teal-800 text-white grid place-items-center shrink-0 hover:bg-teal-900 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                    aria-label="{{ $isEn ? 'Close menu' : 'Cerrar menú' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="px-5 py-5 space-y-5">
            {{-- Buscador --}}
            <form method="GET" action="{{ route('tours.results', ['locale' => $locale]) }}" class="relative" role="search">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-teal-800/40" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                    </svg>
                </span>
                <input type="search" name="q"
                       placeholder="{{ $L('Buscar tours, destinos o experiencias', 'Search tours, destinations or experiences', 'Buscar tours, destinos ou experiências') }}"
                       class="w-full rounded-2xl border border-teal-800/15 bg-white pl-12 pr-4 py-3.5 text-sm text-teal-800 placeholder-teal-800/40 focus:border-teal-600 focus:ring-1 focus:ring-teal-600 outline-none"
                       aria-label="{{ $isEn ? 'Search' : 'Buscar' }}">
            </form>

            {{-- Navegación --}}
            <nav aria-label="{{ $isEn ? 'Main navigation' : 'Navegación principal' }}" class="space-y-1">

                {{-- Inicio --}}
                <a href="{{ route('home', ['locale' => $locale]) }}" @click="open = false" class="site-menu__row">
                    <span class="site-menu__ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.5 1.5 0 012.122 0L21.75 12M4.5 9.75V19.5a1.5 1.5 0 001.5 1.5h3.75V15.75a1.5 1.5 0 011.5-1.5h1.5a1.5 1.5 0 011.5 1.5V21H18a1.5 1.5 0 001.5-1.5V9.75"/>
                        </svg>
                    </span>
                    <span class="site-menu__txt">
                        <b>{{ __('nav.home') }}</b>
                        <small>{{ $L('Volver al inicio', 'Back to start', 'Voltar ao início') }}</small>
                    </span>
                    <svg class="site-menu__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>

                {{-- Tours (expandible por regiones) --}}
                <button type="button" @click="mTours = !mTours" class="site-menu__row w-full"
                        :aria-expanded="mTours.toString()">
                    <span class="site-menu__ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 9l-2 6-4 2 2-6 4-2z"/>
                        </svg>
                    </span>
                    <span class="site-menu__txt">
                        <b>Tours</b>
                        <small>{{ $L('Lima, Ica y Cusco', 'Lima, Ica & Cusco', 'Lima, Ica e Cusco') }}</small>
                    </span>
                    <svg class="site-menu__chev transition-transform" :class="mTours && 'rotate-90'"
                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <div x-show="mTours" x-collapse x-cloak class="pl-14 pr-2 pb-1 space-y-0.5">
                    <a href="{{ route('tours.index', ['locale' => $locale]) }}" @click="open = false" class="site-menu__sub">
                        {{ $L('Todos los tours', 'All tours', 'Todos os tours') }}
                    </a>
                    @foreach ($regions as $region)
                        <a href="{{ route('tours.index', ['locale' => $locale]) }}#{{ $region }}" @click="open = false" class="site-menu__sub">
                            {{ __('nav.tours_' . $region) }}
                        </a>
                    @endforeach
                </div>

                {{-- Mis reservas --}}
                <a href="{{ route('cart.index', ['locale' => $locale]) }}#reservas" @click="open = false" class="site-menu__row">
                    <span class="site-menu__ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z"/>
                        </svg>
                    </span>
                    <span class="site-menu__txt">
                        <b>{{ $L('Mis reservas', 'My bookings', 'Minhas reservas') }}</b>
                        <small>{{ $L('Consulta tus reservas', 'Check your reservations', 'Confira suas reservas') }}</small>
                    </span>
                    <svg class="site-menu__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>

                {{-- Carrito --}}
                <a href="{{ route('cart.index', ['locale' => $locale]) }}" @click="open = false" class="site-menu__row">
                    <span class="site-menu__ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75M7.5 14.25h11.218a.75.75 0 00.74-.617l1.5-9A.75.75 0 0019.218 4.5H5.106M7.5 14.25L5.106 4.5M9 19.5a1.125 1.125 0 11-2.25 0 1.125 1.125 0 012.25 0zm9 0a1.125 1.125 0 11-2.25 0 1.125 1.125 0 012.25 0z"/>
                        </svg>
                    </span>
                    <span class="site-menu__txt">
                        <b>{{ __('nav.cart') }}</b>
                        <small>{{ $L('Revisa y paga', 'Review and pay', 'Revise e pague') }}</small>
                    </span>
                    <svg class="site-menu__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>

                {{-- Blog --}}
                <a href="{{ route('blog.index', ['locale' => $locale]) }}" @click="open = false" class="site-menu__row">
                    <span class="site-menu__ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5M6 7.5h3v3H6v-3z"/>
                        </svg>
                    </span>
                    <span class="site-menu__txt">
                        <b>Blog</b>
                        <small>{{ $L('Guías y consejos de viaje', 'Travel guides & tips', 'Guias e dicas de viagem') }}</small>
                    </span>
                    <svg class="site-menu__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>

                {{-- Reseñas → página interna con comentarios de Google, Tripadvisor y la web --}}
                <a href="{{ route('reviews', ['locale' => $locale]) }}" @click="open = false" class="site-menu__row">
                    <span class="site-menu__ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.5a.56.56 0 011.04 0l2.12 5.11a.56.56 0 00.48.35l5.52.44c.5.04.7.66.32.99l-4.2 3.6a.56.56 0 00-.18.56l1.28 5.38a.56.56 0 01-.84.61l-4.72-2.88a.56.56 0 00-.59 0l-4.72 2.88a.56.56 0 01-.84-.61l1.28-5.38a.56.56 0 00-.18-.56l-4.2-3.6a.56.56 0 01.32-.99l5.52-.44a.56.56 0 00.48-.35L11.48 3.5z"/>
                        </svg>
                    </span>
                    <span class="site-menu__txt">
                        <b>{{ $L('Reseñas', 'Reviews', 'Avaliações') }}</b>
                        <small>{{ $L('Comentarios de nuestros clientes', 'What our clients say', 'Comentários dos clientes') }}</small>
                    </span>
                    <svg class="site-menu__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>

                {{-- Nosotros --}}
                <a href="{{ url('/' . $locale . '/nosotros') }}" @click="open = false" class="site-menu__row">
                    <span class="site-menu__ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                        </svg>
                    </span>
                    <span class="site-menu__txt">
                        <b>{{ __('nav.about') }}</b>
                        <small>{{ $L('Conoce Lima View Tours', 'About Lima View Tours', 'Conheça a Lima View Tours') }}</small>
                    </span>
                    <svg class="site-menu__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>

                {{-- Contacto --}}
                <a href="{{ route('contact', ['locale' => $locale]) }}" @click="open = false" class="site-menu__row">
                    <span class="site-menu__ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                        </svg>
                    </span>
                    <span class="site-menu__txt">
                        <b>{{ __('nav.contact') }}</b>
                        <small>{{ $L('Te ayudamos', 'We help you', 'Nós ajudamos você') }}</small>
                    </span>
                    <svg class="site-menu__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>

                {{-- Mi cuenta / Ingresar --}}
                @if (auth('customer')->check())
                    <a href="{{ route('customer.account', ['locale' => $locale]) }}" @click="open = false" class="site-menu__row">
                        <span class="site-menu__ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </span>
                        <span class="site-menu__txt">
                            <b>{{ __('customer.my_account') }}</b>
                            <small>{{ $L('Tus reservas y perfil', 'Your bookings & profile', 'Suas reservas e perfil') }}</small>
                        </span>
                        <svg class="site-menu__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @else
                    <a href="{{ route('customer.login', ['locale' => $locale]) }}" @click="open = false" class="site-menu__row">
                        <span class="site-menu__ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
                            </svg>
                        </span>
                        <span class="site-menu__txt">
                            <b>{{ __('customer.login') }}</b>
                            <small>{{ $L('Accede a tu cuenta', 'Access your account', 'Acesse sua conta') }}</small>
                        </span>
                        <svg class="site-menu__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @endif
            </nav>
        </div>

        {{-- Pie: teléfono + idioma --}}
        <div class="mt-auto px-5 py-5 border-t border-teal-800/10 bg-cream-100/60 flex items-center justify-between gap-3">
            <a href="tel:{{ str_replace([' ', '+'], '', $contactPhone) }}"
               class="flex items-center gap-2 text-sm font-semibold text-teal-800">
                <span class="w-9 h-9 rounded-full bg-teal-800 text-white grid place-items-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                    </svg>
                </span>
                {{ $supportPhone }}
            </a>
            <x-lang-switcher />
        </div>
    </div>
</header>
