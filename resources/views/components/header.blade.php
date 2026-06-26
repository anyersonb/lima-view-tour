@php
    $locale = app()->getLocale();
    $variant = $variant ?? 'solid'; // 'solid' | 'transparent'
    $contactPhone = \App\Models\Setting::get('contact_phone', '+51 935 542 384');
    $supportPhone = $contactPhone;
    $regions = ['lima', 'ica', 'cusco'];
@endphp

<header class="site-header" data-variant="{{ $variant }}" role="banner"
        x-data="{ open: false, lang: false, regions: { lima: false, ica: false, cusco: false }, scrolled: false }"
        x-init="scrolled = window.scrollY > 40"
        @scroll.window.passive="scrolled = window.scrollY > 40"
        :class="{ 'is-scrolled': scrolled }">
    <div class="site-header__bar flex items-stretch">
        <div class="flex-1 flex items-center gap-5 pl-5 lg:pl-10 pr-3">
            {{-- Logo --}}
            <a href="{{ route('home', ['locale' => $locale]) }}"
               class="site-header__logo flex items-center gap-2 shrink-0 py-3"
               aria-label="{{ __('seo.site_name') }}">
                <img src="{{ asset('assets/logos/logo.png') }}" alt="Lima View Tours"
                     class="h-10 lg:h-12 w-auto select-none" draggable="false">
            </a>

            {{-- Nav desktop --}}
            <nav aria-label="{{ __('nav.home') }}" class="hidden lg:flex items-center gap-7 mx-auto">
                <a href="{{ route('home', ['locale' => $locale]) }}" class="site-header__nav-link">{{ __('nav.home') }}</a>

                @foreach ($regions as $region)
                    <div class="relative" @mouseleave="regions.{{ $region }} = false">
                        <button type="button"
                                class="site-header__nav-link inline-flex items-center gap-1.5"
                                @click="regions.{{ $region }} = !regions.{{ $region }}"
                                @mouseenter="regions.{{ $region }} = true"
                                :aria-expanded="regions.{{ $region }}.toString()"
                                aria-haspopup="true">
                            {{ __('nav.tours_' . $region) }}
                            <svg class="w-2.5 h-2.5" aria-hidden="true" fill="currentColor" viewBox="0 0 12 12"><path d="M6 8L2 4h8z"/></svg>
                        </button>
                        <div x-show="regions.{{ $region }}" x-cloak x-transition
                             class="absolute left-0 top-full pt-2 min-w-[14rem] z-50">
                            <ul class="bg-white text-teal-700 rounded-lg shadow-xl py-2 ring-1 ring-black/5">
                                <li><a href="{{ route('tours.index', ['locale' => $locale]) }}#{{ $region }}" class="block px-4 py-2 text-sm hover:bg-cream-100">{{ __('nav.tours_' . $region) }}</a></li>
                            </ul>
                        </div>
                    </div>
                @endforeach

                <a href="{{ url('/' . $locale . '/nosotros') }}" class="site-header__nav-link">{{ __('nav.about') }}</a>
                <a href="{{ route('contact', ['locale' => $locale]) }}" class="site-header__nav-link">{{ __('nav.contact') }}</a>
            </nav>

            {{-- Right side controls (without cart) --}}
            <div class="hidden lg:flex items-center gap-3 shrink-0">
                <x-lang-switcher />

                <a href="tel:{{ str_replace([' ', '+'], '', $contactPhone) }}"
                   class="flex items-center gap-2.5 border border-white/40 rounded-pill pl-2 pr-4 py-1.5">
                    <span class="w-9 h-9 rounded-full border border-white/40 grid place-items-center shrink-0">
                        <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                        </svg>
                    </span>
                    <span class="leading-tight text-left">
                        <span class="block font-semibold tracking-wide text-sm">{{ $supportPhone }}</span>
                        <span class="block text-[10px] uppercase tracking-[0.15em] opacity-80">{{ __('nav.support_center') }}</span>
                    </span>
                </a>
            </div>

            {{-- Mobile button --}}
            <button type="button"
                    class="lg:hidden ml-auto p-2"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-controls="mobile-menu"
                    :aria-label="open ? '{{ __('nav.close_menu') }}' : '{{ __('nav.open_menu') }}'">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path x-show="!open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    <path x-show="open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Cart block (right edge, full-bleed orange) --}}
        <a href="{{ route('cart.index', ['locale' => $locale]) }}"
           class="site-header__cart hidden lg:flex items-center justify-center bg-orange-500 hover:bg-orange-600 transition-colors px-7"
           aria-label="{{ __('nav.cart') }}">
            <svg class="w-7 h-7 text-white" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75M7.5 14.25h11.218a.75.75 0 00.74-.617l1.5-9A.75.75 0 0019.218 4.5H5.106M7.5 14.25L5.106 4.5M9 19.5a1.125 1.125 0 11-2.25 0 1.125 1.125 0 012.25 0zm9 0a1.125 1.125 0 11-2.25 0 1.125 1.125 0 012.25 0z"/>
            </svg>
        </a>
    </div>

    {{-- Mobile menu --}}
    <div id="mobile-menu" x-show="open" x-cloak x-transition class="lg:hidden bg-teal-700 text-white border-t border-white/10">
        <nav aria-label="{{ __('nav.home') }}" class="container mx-auto py-6">
            <ul class="flex flex-col gap-1">
                <li><a href="{{ route('home', ['locale' => $locale]) }}" class="block py-3 site-header__nav-link">{{ __('nav.home') }}</a></li>
                @foreach ($regions as $region)
                    <li><a href="{{ route('tours.index', ['locale' => $locale]) }}#{{ $region }}" class="block py-3 site-header__nav-link">{{ __('nav.tours_' . $region) }}</a></li>
                @endforeach
                <li><a href="{{ url('/' . $locale . '/nosotros') }}" class="block py-3 site-header__nav-link">{{ __('nav.about') }}</a></li>
                <li><a href="{{ route('contact', ['locale' => $locale]) }}" class="block py-3 site-header__nav-link">{{ __('nav.contact') }}</a></li>
                <li class="pt-4 mt-4 border-t border-white/10 flex items-center justify-between">
                    <x-lang-switcher />
                    <a href="tel:+51{{ $supportPhone }}" class="text-sm font-semibold">{{ $supportPhone }}</a>
                </li>
                <li class="pt-4">
                    <a href="{{ route('cart.index', ['locale' => $locale]) }}" class="btn--primary btn--block">{{ __('nav.cart') }}</a>
                </li>
            </ul>
        </nav>
    </div>
</header>
