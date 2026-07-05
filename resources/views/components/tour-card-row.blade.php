@props([
    'title'              => '',
    'subtitle'           => null,
    'slug'               => '',
    'img'                => null,
    'imgAlt'             => null,
    'before'             => null,
    'now'                => 0,
    'currency'           => 'US$',
    'rating'             => null,
    'reviews'            => 0,
    'duration'           => null,
    'language'           => __('ui.spanish_english'),
    'pickup'             => null,
    'freeCancellation'   => null,
    'locationLabel'      => null,
    'isMasVendido'       => false,
    'discountPct'        => null,
    'badge'              => null,
    'badgeType'          => 'success',
    'showBestSeller'     => true,
    'showOffer'          => true,
])

@php
    $locale  = app()->getLocale();
    $url     = $slug ? route('tours.show', ['locale' => $locale, 'slug' => $slug]) : '#';
    $imgSrc  = $img ?? asset('assets/banners/banner-hero.jpg');
    $altText = $imgAlt ?? $title;

    $pct = $discountPct;
    if (! $pct && $before && $now && (float)$before > (float)$now) {
        $pct = (int) round((1 - ((float)$now / (float)$before)) * 100);
    }

    $badgeKey = $badge ? 'ui.badge_' . \Illuminate\Support\Str::slug($badge, '_') : null;
    $badgeResolved = $badge ? (\Illuminate\Support\Facades\Lang::has($badgeKey) ? __($badgeKey) : $badge) : null;
    $shieldText = $showBestSeller ? ($badgeResolved ?: ($isMasVendido ? 'BEST SELLER' : null)) : null;

    $shieldBg = match ($badgeType) {
        'warn'    => 'bg-orange-600',
        'error'   => 'bg-state-error',
        'info'    => 'bg-teal-600',
        'gold'    => 'bg-amber-600',
        default   => 'bg-teal-800',
    };

    $durationLabel = $duration ?: 'Full Day';
    $pickupLabel   = $pickup !== null ? ($pickup ? __('ui.pickup_included') : __('ui.no_pickup')) : __('ui.pickup_included');

    // Subtítulo: "Full Day desde Lima" — usa el explícito o lo arma con duración + ubicación.
    $sub = $subtitle ?: trim($durationLabel . ($locationLabel ? ' ' . __('ui.from_location') . ' ' . $locationLabel : ''));

    // Features compactas (icono + label) para la columna central.
    $features = [
        ['label' => $durationLabel, 'icon' => 'clock'],
        ['label' => $pickupLabel,   'icon' => 'truck'],
        ['label' => $language,      'icon' => 'globe'],
    ];
    if ($freeCancellation !== false) {
        $features[] = ['label' => __('ui.free_cancellation'), 'icon' => 'shield'];
    }
@endphp

<article class="tour-card-row relative flex bg-white rounded-3xl overflow-hidden shadow-md ring-1 ring-teal-800/5">

    {{-- ── COL 1: IMAGEN ── --}}
    <div class="relative shrink-0 w-[33%] max-w-[150px] p-2.5">
        <a href="{{ $url }}" tabindex="-1" aria-hidden="true"
           class="block h-full min-h-[150px] rounded-2xl overflow-hidden bg-cream-100">
            <img src="{{ $imgSrc }}" alt="{{ $altText }}"
                 class="w-full h-full object-cover object-center text-transparent"
                 onerror="this.onerror=null;this.src='{{ asset('assets/banners/banner-hero.jpg') }}'"
                 loading="lazy" width="320" height="420">
        </a>

        @if ($shieldText)
            <span class="{{ $shieldBg }} text-white absolute top-4 left-0 z-10 inline-flex items-center gap-1 pl-2 pr-2.5 py-1.5 rounded-r-lg shadow-md max-w-[88%]">
                <svg class="w-3 h-3 text-yellow-400 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
                <span class="text-[9px] font-bold uppercase leading-tight tracking-[0.04em] truncate">{{ $shieldText }}</span>
            </span>
        @endif
    </div>

    {{-- ── COL 2: CONTENIDO ── --}}
    <div class="flex-1 min-w-0 py-3 pr-2 flex flex-col">
        <h3 class="font-display text-base sm:text-lg text-teal-800 leading-tight">
            <a href="{{ $url }}" class="hover:text-orange-500 transition-colors line-clamp-2">{{ $title }}</a>
        </h3>

        @if ($sub)
            <p class="mt-0.5 text-[11px] font-semibold text-orange-500 leading-tight">{{ $sub }}</p>
        @endif

        <ul class="mt-2 space-y-1">
            @foreach ($features as $f)
                <li class="flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-cream-100 grid place-items-center shrink-0 text-teal-700" aria-hidden="true">
                        @switch($f['icon'])
                            @case('clock')
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @break
                            @case('truck')
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                                @break
                            @case('globe')
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/></svg>
                                @break
                            @default
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                        @endswitch
                    </span>
                    <span class="text-[11px] text-teal-800/80 leading-tight truncate">{{ $f['label'] }}</span>
                </li>
            @endforeach
        </ul>

        @if ($rating)
            <p class="mt-auto pt-2 flex items-center gap-1 text-[11px]">
                <svg class="w-3.5 h-3.5 text-orange-400 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <span class="font-bold text-teal-800">{{ $rating }}</span>
                <span class="text-teal-800/55">({{ number_format((int)$reviews) }} {{ __('ui.reviews') }})</span>
            </p>
        @endif
    </div>

    {{-- ── COL 3: PRECIO + CTA ── --}}
    <div class="shrink-0 w-[30%] max-w-[140px] border-l border-teal-800/10 py-3 px-3 flex flex-col items-end text-right">
        @if ($pct && $showOffer)
            <p class="text-[9px] font-bold uppercase tracking-[0.08em] text-orange-500 leading-tight">{{ __('ui.special_offer') }}</p>
        @endif

        <p class="mt-1 font-price text-2xl font-bold text-teal-800 leading-none">{{ $currency }}{{ number_format((float)$now, 0) }}</p>
        <p class="text-[10px] text-teal-800/55 leading-tight">{{ __('ui.per_person') }}</p>

        @if ($pct && $showOffer)
            <span class="mt-1.5 inline-flex items-center bg-cream-100 text-teal-800 text-[11px] font-bold px-2.5 py-0.5 rounded-full ring-1 ring-teal-800/10">−{{ $pct }}%</span>
        @endif

        @if ($pct && $before)
            <p class="mt-1 text-[10px] text-teal-800/55 leading-tight">
                {{ __('ui.was') }} <span class="line-through">{{ $currency }}{{ number_format((float)$before, 0) }}</span>
            </p>
        @endif

        <a href="{{ $url }}"
           class="mt-3 w-full bg-teal-900 hover:bg-teal-950 active:bg-teal-950 text-white rounded-full px-3 py-2 inline-flex items-center justify-center gap-1.5 font-semibold text-xs transition shadow-md">
            <span>{{ __('ui.book') }}</span>
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
        </a>
    </div>
</article>
