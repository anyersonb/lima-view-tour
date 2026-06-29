@php
    $current  = app()->getLocale();
    $supported = config('app.supported_locales', ['es', 'en', 'pt']);
    // Strip any current locale prefix to get the path segment after it
    $path = ltrim(preg_replace('#^/?(es|en|pt)(/|$)#', '', request()->path()), '/');

    $flags  = ['es' => '🇪🇸', 'en' => '🇺🇸', 'pt' => '🇧🇷'];
    $labels = ['es' => 'Español', 'en' => 'English', 'pt' => 'Português'];
@endphp

<div class="relative" x-data="{ lang: false }" @click.outside="lang = false">
    <button type="button"
            class="flex items-center gap-2 border border-current/30 rounded-pill px-3 py-2 text-sm font-semibold"
            @click="lang = !lang"
            :aria-expanded="lang.toString()"
            aria-haspopup="listbox"
            aria-label="{{ __('nav.language_label') }}">
        <span aria-hidden="true" class="text-base leading-none">{{ $flags[$current] ?? '🌐' }}</span>
        <span>{{ $labels[$current] ?? strtoupper($current) }}</span>
        <svg class="w-3 h-3 transition-transform duration-150"
             :class="{ 'rotate-180': lang }"
             aria-hidden="true" fill="currentColor" viewBox="0 0 12 12">
            <path d="M6 8L2 4h8z"/>
        </svg>
    </button>

    <ul x-show="lang"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        role="listbox"
        class="absolute right-0 top-full mt-2 min-w-[11rem] bg-white text-teal-700 rounded-lg shadow-xl py-1 ring-1 ring-black/5 z-50">
        @foreach ($supported as $loc)
            <li>
                <a href="{{ url('/' . $loc . ($path ? '/' . $path : '')) }}"
                   hreflang="{{ $loc }}"
                   rel="alternate"
                   role="option"
                   aria-selected="{{ $loc === $current ? 'true' : 'false' }}"
                   class="flex items-center gap-2 px-4 py-2 hover:bg-teal-50 text-sm
                          {{ $loc === $current ? 'font-semibold bg-teal-50/60' : '' }}">
                    <span aria-hidden="true" class="text-base leading-none">{{ $flags[$loc] }}</span>
                    <span>{{ $labels[$loc] }}</span>
                    @if ($loc === $current)
                        <svg class="ml-auto w-3.5 h-3.5 text-teal-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</div>
