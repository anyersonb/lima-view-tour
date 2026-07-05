@props([
    'title'              => '',
    'subtitle'           => null,
    'description'        => null,
    'slug'               => '',
    'img'                => null,
    'imgAlt'             => null,
    'before'             => null,
    'now'                => 0,
    'currency'           => 'US$',
    'rating'             => null,
    'reviews'            => 0,
    'duration'           => null,
    'durationDetail'     => null,
    'language'           => null,
    'languageDetail'     => null,
    'pickup'             => null,
    'pickupDetail'       => null,
    'freeCancellation'   => null,
    'cancellationDetail' => null,
    'dailyDepartures'    => null,
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

    // Calcular descuento si no se pasó explícitamente
    $pct = $discountPct;
    if (! $pct && $before && $now && (float)$before > (float)$now) {
        $pct = (int) round((1 - ((float)$now / (float)$before)) * 100);
    }

    // Texto del escudo (desactivable por tour desde el admin).
    // badge_text viene libre desde la BD (en español); si existe una clave
    // ui.badge_<slug> se usa su traducción para EN/PT.
    $badgeKey = $badge ? 'ui.badge_' . \Illuminate\Support\Str::slug($badge, '_') : null;
    $badgeResolved = $badge ? (\Illuminate\Support\Facades\Lang::has($badgeKey) ? __($badgeKey) : $badge) : null;
    $shieldText = $showBestSeller ? ($badgeResolved ?: ($isMasVendido ? 'BEST SELLER' : null)) : null;
    $shieldLines = [];
    if ($shieldText) {
        $words = preg_split('/\s+/', trim($shieldText));
        if (count($words) <= 1) {
            $shieldLines = [$words[0]];
        } elseif (count($words) === 2) {
            $shieldLines = $words;
        } else {
            $mid = (int) ceil(count($words) / 2);
            $shieldLines = [
                implode(' ', array_slice($words, 0, $mid)),
                implode(' ', array_slice($words, $mid)),
            ];
        }
    }

    $shieldBg = match ($badgeType) {
        'warn'    => 'bg-orange-600',
        'error'   => 'bg-state-error',
        'info'    => 'bg-teal-600',
        'gold'    => 'bg-amber-600',
        default   => 'bg-teal-800',
    };

    $pickupLabel = $pickup !== null ? ($pickup ? __('ui.pickup_included') : __('ui.no_pickup')) : __('ui.pickup_included');
    $durationLabel = $duration ?? __('ui.full_day');
    $durationDetailResolved  = $durationDetail  ?? __('ui.duration_approx');
    $languageResolved        = $language        ?? __('ui.language_default');
    $languageDetailResolved  = $languageDetail  ?? __('ui.bilingual_guide');
    $pickupDetailResolved    = $pickupDetail    ?? __('ui.from_your_hotel');
    $cancellationDetailResolved = $cancellationDetail ?? __('ui.cancellation_24h');
@endphp

<article class="tour-card flex flex-col bg-white rounded-3xl overflow-hidden shadow-md ring-1 ring-teal-800/5 h-full">

    {{-- ── ZONA SUPERIOR: IMAGEN ── --}}
    <div class="relative shrink-0">
        <a href="{{ $url }}" tabindex="-1" aria-hidden="true" class="block aspect-[16/9] sm:aspect-[4/3] bg-cream-100">
            <img src="{{ $imgSrc }}"
                 alt="{{ $altText }}"
                 class="w-full h-full object-cover object-center text-transparent"
                 onerror="this.onerror=null;this.src='{{ asset('assets/banners/banner-hero.jpg') }}'"
                 loading="lazy"
                 width="640"
                 height="480">
        </a>

        {{-- Badge superior-izquierdo: PILL HORIZONTAL con estrella dorada izq + texto der --}}
        @if (! empty($shieldLines))
            <div class="tour-card__badge {{ $shieldBg }} text-white absolute top-3 left-3 z-10 inline-flex items-center gap-2 pl-2 pr-4 py-1.5 rounded-full shadow-lg ring-1 ring-black/5">
                <span class="w-7 h-7 rounded-full bg-white/10 grid place-items-center shrink-0" aria-hidden="true">
                    <svg class="w-4 h-4 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                </span>
                <span class="text-[12px] font-bold uppercase leading-[1.05] tracking-[0.06em] text-left">
                    @foreach ($shieldLines as $line)
                        {{ $line }}@if (! $loop->last)<br>@endif
                    @endforeach
                </span>
            </div>
        @endif

        {{-- Pill OFERTA ESPECIAL top-right: blanca con chip rojo "-N%" (desactivable por tour) --}}
        @if ($pct && $showOffer)
            <span class="absolute top-3 right-3 inline-flex items-center gap-1.5 bg-white text-teal-800 text-[11px] font-bold uppercase tracking-[0.05em] px-3 py-2 rounded-2xl shadow-md ring-1 ring-teal-800/10">
                <span>{{ __('ui.special_offer') }}</span>
                <span class="inline-flex items-center bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full leading-tight whitespace-nowrap">-{{ $pct }}%</span>
            </span>
        @endif

        {{-- Pill de ubicación: blanca con sombra --}}
        @if ($locationLabel)
            <span class="absolute bottom-4 left-4 inline-flex items-center gap-2 bg-white text-teal-800 text-xs font-semibold uppercase tracking-[0.14em] px-4 py-2 rounded-full shadow-md ring-1 ring-teal-800/5">
                <svg class="w-4 h-4 text-orange-500 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M11.54 22.351l.07.04.028.016a.76.76 0 00.723 0l.028-.015.071-.041a16.975 16.975 0 001.144-.742 19.58 19.58 0 002.683-2.282c1.944-2.013 3.5-4.667 3.5-8.077A8 8 0 003 11.25c0 3.41 1.556 6.064 3.5 8.077a19.58 19.58 0 002.683 2.282 16.975 16.975 0 001.144.742zM12 13.5a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" clip-rule="evenodd"/>
                </svg>
                {{ $locationLabel }}
            </span>
        @endif
    </div>

    {{-- ── ZONA INFERIOR: CONTENIDO ── --}}
    <div class="flex flex-col flex-1 p-4 sm:p-6">

        {{-- Título --}}
        <h3 class="font-display text-lg sm:text-2xl text-teal-800 leading-tight">
            <a href="{{ $url }}" class="hover:text-orange-500 transition-colors">
                {{ $title }}
            </a>
        </h3>

        {{-- Subtítulo opcional --}}
        @if ($subtitle)
            <p class="mt-1 text-[11px] uppercase tracking-[0.15em] text-teal-800/60 font-semibold">{{ $subtitle }}</p>
        @endif

        {{-- Rating con reseñas --}}
        @if ($rating)
            <p class="mt-2 sm:mt-3 flex items-center gap-2 text-[13px] sm:text-sm">
                <span class="text-orange-400 text-base sm:text-lg leading-none tracking-tight" aria-hidden="true">★★★★★</span>
                <span class="font-semibold text-teal-800">{{ $rating }}</span>
                <span class="text-teal-800/55">·</span>
                <span class="text-teal-800/55">{{ number_format((int)$reviews) }} {{ __('ui.reviews') }}</span>
            </p>
        @endif

        {{-- Grid 2×2 de features --}}
        <div class="mt-3 sm:mt-4">
            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-teal-800/70 mb-2 sm:mb-2.5 flex items-center gap-2">
                {{ __('ui.includes') }}
                <span class="h-px flex-1 bg-teal-800/15"></span>
            </p>
            <ul class="grid grid-cols-2 gap-2 text-sm">

                {{-- Duración --}}
                <li class="flex items-center gap-2 bg-cream-100 rounded-xl ring-1 ring-teal-800/5 py-2 px-2 sm:py-2.5 sm:px-2.5">
                    <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white shadow-sm grid place-items-center shrink-0 text-teal-800" aria-hidden="true">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="font-bold text-teal-800 text-[12px] leading-tight">{{ $durationLabel }}</p>
                        <p class="text-[10px] text-teal-800/55 leading-tight truncate">{{ $durationDetailResolved }}</p>
                    </div>
                </li>

                {{-- Recojo --}}
                <li class="flex items-center gap-2 bg-cream-100 rounded-xl ring-1 ring-teal-800/5 py-2 px-2 sm:py-2.5 sm:px-2.5">
                    <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white shadow-sm grid place-items-center shrink-0 text-teal-800" aria-hidden="true">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="font-bold text-teal-800 text-[12px] leading-tight">{{ $pickupLabel }}</p>
                        <p class="text-[10px] text-teal-800/55 leading-tight truncate">{{ $pickupDetailResolved }}</p>
                    </div>
                </li>

                {{-- Idioma --}}
                <li class="flex items-center gap-2 bg-cream-100 rounded-xl ring-1 ring-teal-800/5 py-2 px-2 sm:py-2.5 sm:px-2.5">
                    <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white shadow-sm grid place-items-center shrink-0 text-teal-800" aria-hidden="true">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="9"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="font-bold text-teal-800 text-[12px] leading-tight">{{ $languageResolved }}</p>
                        <p class="text-[10px] text-teal-800/55 leading-tight truncate">{{ $languageDetailResolved }}</p>
                    </div>
                </li>

                {{-- Cancelación gratuita --}}
                @if ($freeCancellation !== false)
                    <li class="flex items-center gap-2 bg-cream-100 rounded-xl ring-1 ring-teal-800/5 py-2 px-2 sm:py-2.5 sm:px-2.5">
                        <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white shadow-sm grid place-items-center shrink-0 text-teal-800" aria-hidden="true">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <p class="font-bold text-teal-800 text-[12px] leading-tight">{{ __('ui.free_cancellation') }}</p>
                            <p class="text-[10px] text-teal-800/55 leading-tight truncate">{{ $cancellationDetailResolved }}</p>
                        </div>
                    </li>
                @endif
            </ul>
        </div>

        {{-- Precio + CTA — bloque unificado, mismo alto con o sin descuento --}}
        <div class="mt-auto pt-3 sm:pt-5">
            <div class="bg-cream-100 rounded-2xl ring-1 ring-teal-800/5 p-2.5 sm:p-3 flex flex-col gap-1.5 sm:gap-2">
                {{-- Fila superior: pill descuento + precio antes (siempre ocupa espacio) --}}
                <div class="flex items-center gap-2 min-h-[26px]">
                    @if ($pct && $before)
                        <span class="inline-flex items-center gap-1 bg-red-50 text-red-600 border border-red-200 text-[10px] font-bold px-2 py-1 rounded-lg whitespace-nowrap leading-tight shrink-0">
                            <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/>
                            </svg>
                            {{ $pct }}% {{ __('ui.discount_label') }}
                        </span>
                        <div class="flex items-center gap-1">
                            <span class="text-[10px] text-teal-800/50 font-medium uppercase tracking-wide leading-none">{{ __('ui.before_price') }}</span>
                            <span class="font-price text-[13px] text-teal-800/45 line-through leading-none">{{ $currency }}{{ number_format((float)$before, 0) }}</span>
                        </div>
                    @else
                        {{-- Espaciador invisible para mantener altura uniforme --}}
                        <span class="inline-block h-[26px]" aria-hidden="true"></span>
                    @endif
                </div>
                {{-- Fila inferior: precio actual + CTA --}}
                <div class="flex items-center justify-between gap-2">
                    <div class="flex flex-col gap-0.5">
                        <span class="text-[9px] font-bold uppercase tracking-widest text-emerald-600 leading-none">{{ __('ui.now_price') }}</span>
                        <span class="font-price text-[22px] sm:text-[26px] font-bold text-teal-800 leading-none">{{ $currency }}{{ number_format((float)$now, 0) }}</span>
                        <span class="inline-flex items-center gap-0.5 text-[10px] text-teal-800/55 mt-0.5 leading-none">
                            <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd"/></svg>
                            {{ __('ui.per_person') }}
                        </span>
                    </div>
                    <a href="{{ $url }}"
                       class="bg-teal-900 hover:bg-teal-950 active:bg-teal-950 text-white rounded-full pl-4 pr-1.5 py-1.5 inline-flex items-center gap-2 font-semibold text-sm transition shadow-md shrink-0">
                        <span class="whitespace-nowrap">{{ __('ui.see_tour') }}</span>
                        <span class="w-8 h-8 rounded-full bg-orange-500 grid place-items-center shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                            </svg>
                        </span>
                    </a>
                </div>
            </div>
        </div>

    </div>
</article>
