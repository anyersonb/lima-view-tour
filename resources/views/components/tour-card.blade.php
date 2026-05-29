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

    // Texto del escudo
    $shieldText = $badge ?: ($isMasVendido ? 'BEST SELLER' : null);
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
@endphp

<article class="tour-card flex flex-col bg-white rounded-3xl overflow-hidden shadow-md ring-1 ring-teal-800/5 h-full">

    {{-- ── ZONA SUPERIOR: IMAGEN ── --}}
    <div class="relative shrink-0">
        <a href="{{ $url }}" tabindex="-1" aria-hidden="true">
            <img src="{{ $imgSrc }}"
                 alt="{{ $altText }}"
                 class="w-full h-64 md:h-72 object-cover"
                 loading="lazy"
                 width="640"
                 height="288">
        </a>

        {{-- Badge superior-izquierdo: ESCUDO COLGANTE con estrella arriba + texto abajo --}}
        @if (! empty($shieldLines))
            <div class="tour-card__shield {{ $shieldBg }} text-white absolute -top-1 left-4 z-10 w-[88px] flex flex-col items-center justify-start pt-2.5 pb-5 px-1.5 shadow-lg">
                <svg class="w-4 h-4 text-yellow-400 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
                <p class="mt-1.5 text-[11px] font-bold uppercase leading-[1.15] tracking-[0.05em] text-center">
                    @foreach ($shieldLines as $line)
                        {{ $line }}@if (! $loop->last)<br>@endif
                    @endforeach
                </p>
            </div>
        @endif

        {{-- Badge OFERTA ESPECIAL (crema con icono etiqueta verde + -% rojo) --}}
        @if ($pct)
            <span class="absolute top-4 right-16 inline-flex items-center gap-1.5 bg-white text-teal-800 text-xs font-bold uppercase tracking-[0.06em] px-3 py-2 rounded-xl shadow-md ring-1 ring-teal-800/10">
                <svg class="w-3.5 h-3.5 text-teal-700 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.25 2.25a3 3 0 00-3 3v4.318a3 3 0 00.879 2.121l9.58 9.581c.92.92 2.39 1.186 3.548.428a18.849 18.849 0 005.441-5.44c.758-1.16.492-2.629-.428-3.548l-9.58-9.581a3 3 0 00-2.122-.879H5.25zM6.375 7.5a1.125 1.125 0 100-2.25 1.125 1.125 0 000 2.25z" clip-rule="evenodd"/>
                </svg>
                <span>OFERTA ESPECIAL</span>
                <span class="text-state-error">-{{ $pct }}%</span>
            </span>
        @endif

        {{-- Botón Favorito (corazón) --}}
        <button type="button"
                x-data="{ liked: false }" @click.prevent="liked = !liked"
                class="absolute top-4 right-4 w-11 h-11 rounded-full bg-white grid place-items-center shadow-md ring-1 ring-teal-800/10 transition hover:bg-cream-50"
                aria-label="Agregar a favoritos">
            <svg class="w-5 h-5 transition" :class="liked ? 'text-state-error' : 'text-teal-800/70'"
                 fill="currentColor" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"
                 :fill="liked ? 'currentColor' : 'none'">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
            </svg>
        </button>

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
    <div class="flex flex-col flex-1 p-6">

        {{-- Título --}}
        <h3 class="font-display text-2xl text-teal-800 leading-tight">
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
            <p class="mt-3 flex items-center gap-2 text-sm">
                <span class="text-orange-400 text-lg leading-none tracking-tight" aria-hidden="true">★★★★★</span>
                <span class="font-semibold text-teal-800">{{ $rating }}</span>
                <span class="text-teal-800/55">·</span>
                <span class="text-teal-800/55">{{ number_format((int)$reviews) }} reseñas</span>
            </p>
        @endif

        {{-- Lista vertical de features --}}
        <ul class="mt-5 space-y-3 text-sm">
            <li class="flex items-start gap-3">
                <span class="w-9 h-9 rounded-full grid place-items-center text-teal-700 shrink-0" aria-hidden="true">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.4" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
                <span>
                    <span class="block font-semibold text-teal-800">{{ $duration ?? 'Full Day' }}</span>
                    <span class="block text-xs text-teal-800/60">Duración del tour</span>
                </span>
            </li>
            <li class="flex items-start gap-3">
                <span class="w-9 h-9 rounded-full grid place-items-center text-teal-700 shrink-0" aria-hidden="true">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.4" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                    </svg>
                </span>
                <span>
                    <span class="block font-semibold text-teal-800">{{ $pickup !== null ? ($pickup ? 'Recojo incluido' : 'Sin recojo') : 'Recojo incluido' }}</span>
                    <span class="block text-xs text-teal-800/60">Desde tu hotel</span>
                </span>
            </li>
            <li class="flex items-start gap-3">
                <span class="w-9 h-9 rounded-full grid place-items-center text-teal-700 shrink-0" aria-hidden="true">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.4" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/>
                    </svg>
                </span>
                <span>
                    <span class="block font-semibold text-teal-800">{{ $language }}</span>
                    <span class="block text-xs text-teal-800/60">Guía bilingüe</span>
                </span>
            </li>
            @if ($freeCancellation !== false)
                <li class="flex items-start gap-3">
                    <span class="w-9 h-9 rounded-full grid place-items-center text-teal-700 shrink-0" aria-hidden="true">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.4" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                        </svg>
                    </span>
                    <span>
                        <span class="block font-semibold text-teal-800">Cancelación gratuita</span>
                        <span class="block text-xs text-teal-800/60">Hasta 24h antes del tour</span>
                    </span>
                </li>
            @endif
        </ul>

        {{-- Precio + CTA en grid 2 columnas --}}
        <div class="mt-6 grid grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)] gap-3 items-stretch">
            {{-- Caja crema con precio --}}
            <div class="bg-cream-100 rounded-2xl px-4 py-3 flex flex-col justify-center">
                @if ($before)
                    <div class="flex items-baseline gap-2">
                        <p class="text-[10px] tracking-[0.15em] uppercase text-teal-800/55 font-semibold">Antes</p>
                        <p class="font-price text-sm text-teal-800/50 line-through">{{ $currency }}{{ number_format((float)$before, 0) }}</p>
                    </div>
                @endif
                <div class="flex items-baseline gap-1.5 mt-0.5">
                    <span class="inline-block bg-amber-100 text-teal-800 text-[9px] font-bold uppercase tracking-[0.15em] px-2 py-0.5 rounded">Desde</span>
                </div>
                <p class="font-price text-3xl text-teal-800 font-semibold leading-tight mt-1">{{ $currency }}{{ number_format((float)$now, 0) }}</p>
                <p class="text-[11px] text-teal-800/60 mt-0.5">por persona
                    @if ($pct)
                        <span class="inline-flex items-center gap-1 ml-1 text-teal-700">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.25 2.25a3 3 0 00-3 3v4.318a3 3 0 00.879 2.121l9.58 9.581c.92.92 2.39 1.186 3.548.428a18.849 18.849 0 005.441-5.44c.758-1.16.492-2.629-.428-3.548l-9.58-9.581a3 3 0 00-2.122-.879H5.25z" clip-rule="evenodd"/>
                            </svg>
                            <span class="font-semibold">{{ $pct }}% DTO.</span>
                        </span>
                    @endif
                </p>
            </div>

            {{-- Botón Reservar ahora --}}
            <a href="{{ $url }}" class="bg-teal-800 hover:bg-teal-700 active:bg-teal-900 text-white rounded-2xl px-4 py-3 flex items-center justify-center gap-3 font-semibold text-sm transition shadow-md">
                <svg class="w-5 h-5 text-orange-400 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                </svg>
                <span>Reservar ahora</span>
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                </svg>
            </a>
        </div>
    </div>
</article>
