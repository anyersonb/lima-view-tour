@php
    $current = app()->getLocale();
    $supported = config('app.supported_locales', ['es', 'en']);
    $path = ltrim(preg_replace('#^/?(es|en)(/|$)#', '', request()->path()), '/');
    $labels = ['es' => 'Español', 'en' => 'English'];
@endphp
<div class="relative" @click.outside="lang = false">
    <button type="button"
            class="flex items-center gap-2 border border-current/30 rounded-pill px-3 py-2 text-sm font-semibold"
            @click="lang = !lang"
            :aria-expanded="lang.toString()"
            aria-haspopup="listbox"
            aria-label="{{ __('nav.language_label') }}">
        <span aria-hidden="true" class="w-5 h-3.5 rounded-sm overflow-hidden inline-flex">
            <span class="w-1/3 bg-state-error"></span>
            <span class="w-1/3 bg-white"></span>
            <span class="w-1/3 bg-state-error"></span>
        </span>
        <span>{{ $labels[$current] }}</span>
        <svg class="w-3 h-3" aria-hidden="true" fill="currentColor" viewBox="0 0 12 12"><path d="M6 8L2 4h8z"/></svg>
    </button>
    <ul x-show="lang" x-cloak x-transition role="listbox"
        class="absolute right-0 top-full mt-2 min-w-[10rem] bg-white text-teal-700 rounded-lg shadow-xl py-1 ring-1 ring-black/5 z-50">
        @foreach ($supported as $loc)
            <li>
                <a href="{{ url('/' . $loc . ($path ? '/' . $path : '')) }}"
                   hreflang="{{ $loc }}"
                   rel="alternate"
                   role="option"
                   @aria-selected="{{ $loc === $current ? 'true' : 'false' }}"
                   class="block px-4 py-2 hover:bg-cream-100 {{ $loc === $current ? 'font-semibold' : '' }}">
                    {{ $labels[$loc] }}
                </a>
            </li>
        @endforeach
    </ul>
</div>
