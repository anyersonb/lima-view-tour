@props([
    'title'            => '',
    'subtitle'         => null,
    'description'      => null,
    'slug'             => '',
    'img'              => null,
    'imgAlt'           => null,
    'before'           => null,
    'now'              => 0,
    'currency'         => 'US$',
    'rating'           => null,
    'reviews'          => 0,
    'duration'         => null,
    'language'         => 'Español / Inglés',
    'pickup'           => null,
    'dailyDepartures'  => null,
    'freeCancellation' => null,
    'locationLabel'    => null,
    'isMasVendido'     => false,
    'discountPct'      => null,
    'badge'            => null,
    'badgeType'        => 'success',
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

    // Texto del escudo: si no hay badge explícito pero isMasVendido, usar "MÁS VENDIDO"
    $shieldText = $badge ?: ($isMasVendido ? 'MÁS VENDIDO' : null);

    // Dividir texto del escudo en 2 líneas (máx) para que entre en el escudo
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

    // Mapa de color del escudo según badgeType
    $shieldBg = match ($badgeType) {
        'warn'    => 'bg-orange-600',
        'error'   => 'bg-state-error',
        'info'    => 'bg-teal-600',
        default   => 'bg-teal-800', // success
    };
@endphp

<article class="tour-card flex flex-col bg-white rounded-2xl overflow-hidden shadow-sm ring-1 ring-teal-800/5 h-full">

    {{-- ── ZONA SUPERIOR: IMAGEN ── --}}
    <div class="relative shrink-0">
        <a href="{{ $url }}" tabindex="-1" aria-hidden="true">
            <img src="{{ $imgSrc }}"
                 alt="{{ $altText }}"
                 class="w-full h-52 object-cover"
                 loading="lazy"
                 width="480"
                 height="208">
        </a>

        {{-- Badge superior-izquierdo: ESCUDO COLGANTE (MÁS VENDIDO, BEST SELLER, TOP EXPERIENCIA…) --}}
        @if (! empty($shieldLines))
            <div class="tour-card__shield {{ $shieldBg }} text-white absolute top-0 left-3 z-10 w-[68px] flex flex-col items-center justify-start pt-2 pb-3 px-1 shadow-md">
                <svg class="w-3.5 h-3.5 text-yellow-300 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
                <p class="mt-1 text-[8.5px] font-bold uppercase leading-[1.15] tracking-[0.04em] text-center">
                    @foreach ($shieldLines as $line)
                        {{ $line }}@if (! $loop->last)<br>@endif
                    @endforeach
                </p>
            </div>
        @endif

        {{-- Badge superior-derecho: OFERTA ESPECIAL (crema con texto verde + ícono etiqueta) --}}
        @if ($pct)
            <span class="absolute top-3 right-3 inline-flex items-center gap-1.5 bg-white text-teal-800 text-[10px] font-bold uppercase tracking-[0.08em] px-2.5 py-1.5 rounded-lg shadow-md ring-1 ring-teal-800/10">
                <svg class="w-3 h-3 text-teal-700 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.25 2.25a3 3 0 00-3 3v4.318a3 3 0 00.879 2.121l9.58 9.581c.92.92 2.39 1.186 3.548.428a18.849 18.849 0 005.441-5.44c.758-1.16.492-2.629-.428-3.548l-9.58-9.581a3 3 0 00-2.122-.879H5.25zM6.375 7.5a1.125 1.125 0 100-2.25 1.125 1.125 0 000 2.25z" clip-rule="evenodd"/>
                </svg>
                <span>OFERTA ESPECIAL</span>
                <span class="text-state-error">-{{ $pct }}%</span>
            </span>
        @endif

        {{-- Pill de ubicación — dorada flotante sobre el borde inferior de la imagen --}}
        @if ($locationLabel)
            <span class="absolute bottom-0 left-4 translate-y-1/2 inline-flex items-center gap-1.5 bg-orange-500 text-white text-[11px] font-semibold uppercase tracking-[0.12em] px-3 py-1.5 rounded-full shadow-md">
                <svg class="w-3.5 h-3.5 text-white shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M11.54 22.351l.07.04.028.016a.76.76 0 00.723 0l.028-.015.071-.041a16.975 16.975 0 001.144-.742 19.58 19.58 0 002.683-2.282c1.944-2.013 3.5-4.667 3.5-8.077A8 8 0 003 11.25c0 3.41 1.556 6.064 3.5 8.077a19.58 19.58 0 002.683 2.282 16.975 16.975 0 001.144.742zM12 13.5a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" clip-rule="evenodd"/>
                </svg>
                {{ $locationLabel }}
            </span>
        @endif
    </div>

    {{-- ── ZONA INFERIOR: CONTENIDO ── --}}
    <div class="flex flex-col flex-1 p-5 {{ $locationLabel ? 'pt-7' : '' }}">

        {{-- Título --}}
        <h3 class="font-display text-lg text-teal-800 leading-snug">
            <a href="{{ $url }}" class="hover:text-orange-500 transition-colors">
                {{ $title }}
            </a>
        </h3>

        {{-- Subtítulo / categoría --}}
        @if ($subtitle)
            <p class="mt-0.5 text-[11px] uppercase tracking-[0.15em] text-teal-800/60 font-semibold">{{ $subtitle }}</p>
        @endif

        {{-- Rating --}}
        @if ($rating)
            <p class="mt-2 flex items-center gap-1.5 text-sm">
                <span class="text-orange-400 tracking-tight leading-none" aria-hidden="true">★★★★★</span>
                <span class="font-semibold text-teal-800 text-xs">{{ $rating }}</span>
                <span class="text-teal-800/55 text-xs">({{ number_format((int)$reviews) }})</span>
            </p>
        @endif

        {{-- Descripción corta --}}
        @if ($description)
            <p class="mt-2 text-xs text-teal-800/70 leading-relaxed line-clamp-2">{{ $description }}</p>
        @endif

        {{-- Grid de features: 5 columnas (la última con highlight crema) --}}
        <ul class="mt-4 grid grid-cols-5 gap-x-1 gap-y-2 text-[9px] text-teal-800/65 text-center">

            {{-- Duración --}}
            <li class="flex flex-col items-center gap-1 px-0.5">
                <span class="w-8 h-8 rounded-full border border-teal-800/20 grid place-items-center text-teal-700" aria-hidden="true">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
                <span class="leading-tight uppercase tracking-[0.06em]">{{ $duration ?? 'Full Day' }}</span>
            </li>

            {{-- Recojo --}}
            <li class="flex flex-col items-center gap-1 px-0.5">
                <span class="w-8 h-8 rounded-full border border-teal-800/20 grid place-items-center text-teal-700" aria-hidden="true">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                    </svg>
                </span>
                <span class="leading-tight uppercase tracking-[0.06em]">{{ $pickup !== null ? ($pickup ? 'Recojo incluido' : 'Sin recojo') : 'Recojo incluido' }}</span>
            </li>

            {{-- Idiomas --}}
            <li class="flex flex-col items-center gap-1 px-0.5">
                <span class="w-8 h-8 rounded-full border border-teal-800/20 grid place-items-center text-teal-700" aria-hidden="true">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 21l5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 016-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 01-3.827-5.802"/>
                    </svg>
                </span>
                <span class="leading-tight uppercase tracking-[0.06em]">{{ $language }}</span>
            </li>

            {{-- Salidas --}}
            <li class="flex flex-col items-center gap-1 px-0.5">
                <span class="w-8 h-8 rounded-full border border-teal-800/20 grid place-items-center text-teal-700" aria-hidden="true">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                    </svg>
                </span>
                <span class="leading-tight uppercase tracking-[0.06em]">{{ $dailyDepartures !== null ? ($dailyDepartures ? 'Salidas diarias' : 'Salidas fijas') : 'Salidas diarias' }}</span>
            </li>

            {{-- Cancelación gratuita (highlight crema) --}}
            @if ($freeCancellation !== false)
                <li class="flex flex-col items-center gap-1 px-0.5 py-1.5 bg-cream-100 rounded-lg border border-teal-800/10 -mx-0.5">
                    <span class="w-8 h-8 rounded-full border border-teal-700/30 grid place-items-center text-teal-700" aria-hidden="true">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                        </svg>
                    </span>
                    <span class="leading-tight uppercase tracking-[0.06em] font-semibold">Cancelación<br>gratuita</span>
                </li>
            @endif
        </ul>

        {{-- Precios — sin borde exterior, solo divisor central --}}
        <div class="mt-4">
            <div class="flex items-stretch divide-x divide-teal-800/15">
                @if ($before)
                    <div class="flex-1 text-center py-2 px-2">
                        <p class="text-[10px] tracking-[0.15em] uppercase text-teal-800/55 font-semibold">Antes</p>
                        <p class="font-price text-base text-teal-800/50 line-through mt-0.5">{{ $currency }} {{ number_format((float)$before, 0) }}</p>
                    </div>
                    <div class="flex-1 text-center py-2 px-2">
                        <p class="text-[10px] tracking-[0.15em] uppercase text-teal-800/55 font-semibold">Ahora</p>
                        <p class="font-price text-2xl text-state-error leading-tight mt-0.5 font-semibold">{{ $currency }} {{ number_format((float)$now, 0) }}</p>
                    </div>
                @else
                    <div class="flex-1 text-center py-2 px-2">
                        <p class="text-[10px] tracking-[0.15em] uppercase text-teal-800/55 font-semibold">Precio</p>
                        <p class="font-price text-2xl text-teal-800 leading-tight mt-0.5 font-semibold">{{ $currency }} {{ number_format((float)$now, 0) }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- CTA --}}
        <a href="{{ $url }}" class="btn--primary btn--block mt-3 !text-sm tracking-[0.15em] uppercase">
            Reservar ahora
        </a>

    </div>
</article>

{{--
=======================================================================
TODO — Campos pendientes en el modelo Tour (requieren migración backend):
=======================================================================
1. is_mas_vendido  boolean  default false
   Actualmente se usa is_featured como proxy. Crear campo dedicado si
   el negocio quiere distinguir "más vendido" de "destacado".

2. discount_pct    tinyInteger unsigned nullable
   Actualmente se calcula en runtime desde price_before/price.
   Opcionalmente persistir para evitar el cálculo y permitir override.

3. pickup_included boolean  default true
4. daily_departures boolean  default true
5. free_cancellation boolean  default true
6. location_label  string nullable
=======================================================================
--}}
