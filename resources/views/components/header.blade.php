@php
    $locale = app()->getLocale();
    $variant = $variant ?? 'solid'; // 'solid' | 'transparent'
    $supportPhone = '190010088';
@endphp

<header class="site-header" data-variant="{{ $variant }}" role="banner"
        x-data="{ open: false, lang: false, regions: { lima: false, ica: false, cusco: false } }">
    <div class="container mx-auto flex items-center gap-4 py-3">
        {{-- Logo --}}
        <a href="{{ route('home', ['locale' => $locale]) }}"
           class="flex items-center gap-3 shrink-0"
           aria-label="{{ __('seo.site_name') }}">
            <span class="font-display text-xl tracking-wide">LIMA<br><span class="text-xs tracking-[0.2em]">VIEW TOURS</span></span>
            <span aria-hidden="true" class="w-9 h-9 rounded-full border border-current/40 grid place-items-center">
                <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                    <circle cx="12" cy="12" r="9"/>
                    <circle cx="12" cy="12" r="6"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
            </span>
        </a>

        {{-- Nav desktop --}}
        <nav aria-label="{{ __('nav.home') }}" class="hidden lg:flex items-center gap-7 mx-auto">
            <a href="{{ route('home', ['locale' => $locale]) }}" class="site-header__nav-link">{{ __('nav.home') }}</a>

            @foreach (['lima', 'ica', 'cusco'] as $region)
                <div class="relative" @mouseleave="regions.{{ $region }} = false">
                    <button type="button"
                            class="site-header__nav-link inline-flex items-center gap-1"
                            @click="regions.{{ $region }} = !regions.{{ $region }}"
                            @mouseenter="regions.{{ $region }} = true"
                            :aria-expanded="regions.{{ $region }}.toString()"
                            aria-haspopup="true">
                        {{ __('nav.tours_' . $region) }}
                        <svg class="w-3 h-3" aria-hidden="true" fill="currentColor" viewBox="0 0 12 12"><path d="M6 8L2 4h8z"/></svg>
                    </button>
                    <div x-show="regions.{{ $region }}"
                         x-cloak x-transition
                         class="absolute left-0 top-full pt-2 min-w-[16rem] z-50">
                        <ul class="bg-white text-teal-700 rounded-lg shadow-xl py-2 ring-1 ring-black/5">
                            <li><a href="{{ route('tours.index', ['locale' => $locale]) }}#{{ $region }}" class="block px-4 py-2 hover:bg-cream-100">{{ __('nav.tours_' . $region) }}</a></li>
                        </ul>
                    </div>
                </div>
            @endforeach

            <a href="{{ url('/' . $locale . '/nosotros') }}" class="site-header__nav-link">{{ __('nav.about') }}</a>
            <a href="{{ route('contact', ['locale' => $locale]) }}" class="site-header__nav-link">{{ __('nav.contact') }}</a>
        </nav>

        {{-- Right side: lang + support + cart --}}
        <div class="hidden lg:flex items-center gap-3 shrink-0">
            <x-lang-switcher />

            <a href="tel:+51{{ $supportPhone }}" class="flex items-center gap-2 border border-current/30 rounded-pill px-4 py-2">
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 12a6 6 0 10-12 0v4m12-4v4a2 2 0 01-2 2h-1m3-6h-2m-10 0H4m0 0v4a2 2 0 002 2h1m-3-6V8a8 8 0 0116 0v4"/></svg>
                <span class="leading-tight">
                    <span class="block font-semibold tracking-wide">{{ $supportPhone }}</span>
                    <span class="block text-[10px] uppercase tracking-widest opacity-80">{{ __('nav.support_center') }}</span>
                </span>
            </a>

            <a href="{{ route('checkout', ['locale' => $locale]) }}"
               class="rounded-pill bg-orange-500 hover:bg-orange-600 text-white p-3"
               aria-label="{{ __('nav.cart') }}">
                <svg class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218a.75.75 0 00.74-.617l1.5-9A.75.75 0 0019.218 4.5H5.106M7.5 14.25L5.106 4.5M7.5 14.25L4.5 17.25"/></svg>
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

    {{-- Mobile menu --}}
    <div id="mobile-menu" x-show="open" x-cloak x-transition class="lg:hidden bg-teal-700 text-white border-t border-white/10">
        <nav aria-label="{{ __('nav.home') }}" class="container mx-auto py-6">
            <ul class="flex flex-col gap-1">
                <li><a href="{{ route('home', ['locale' => $locale]) }}" class="block py-3 site-header__nav-link">{{ __('nav.home') }}</a></li>
                @foreach (['lima', 'ica', 'cusco'] as $region)
                    <li><a href="{{ route('tours.index', ['locale' => $locale]) }}#{{ $region }}" class="block py-3 site-header__nav-link">{{ __('nav.tours_' . $region) }}</a></li>
                @endforeach
                <li><a href="{{ url('/' . $locale . '/nosotros') }}" class="block py-3 site-header__nav-link">{{ __('nav.about') }}</a></li>
                <li><a href="{{ route('contact', ['locale' => $locale]) }}" class="block py-3 site-header__nav-link">{{ __('nav.contact') }}</a></li>
                <li class="pt-4 mt-4 border-t border-white/10 flex items-center justify-between">
                    <x-lang-switcher />
                    <a href="tel:+51{{ $supportPhone }}" class="text-sm font-semibold">{{ $supportPhone }}</a>
                </li>
                <li class="pt-4">
                    <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="btn--primary btn--block">{{ __('nav.book_now') }}</a>
                </li>
            </ul>
        </nav>
    </div>
</header>
