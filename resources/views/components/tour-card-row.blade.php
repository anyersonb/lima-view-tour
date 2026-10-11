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
    // Ya localizados (es/en/pt) por el caller; null = el tour no tiene el dato => no se pinta.
    'language'           => null,
    // Modalidad normalizada y localizada (Grupal/Privado/...). Aún no se pinta; queda como prop para el maquetador.
    'modality'           => null,
    // true = el tour lo promete en el CMS (interruptores por tour); false/null = no se pinta "Recojo incluido" / "Cancelación gratuita".
    'pickup'             => null,
    'freeCancellation'   => null,
    'locationLabel'      => null,
    'isMasVendido'       => false,
    'discountPct'        => null,
    'badge'              => null,
    'badgeType'          => 'success',
    'showBestSeller'     => true,
    'showOffer'          => true,
    // true = primeras tarjetas del listado: la imagen carga sin lazy (y sin descargarse en ≥640 px, donde esta lista está oculta).
    'eager'              => false,
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

    // $badge, $badgeType, $isMasVendido, $showBestSeller y $subtitle ya no
    // se pintan (badges eliminados a pedido del cliente). Se dejan como props
    // para no romper callers. $showOffer solo gobierna la pastilla "-N%".
    $durationLabel = filled($duration) ? $duration : null;

    // Features compactas (icono + label). Solo entran las que el tour
    // realmente tiene: nada de promesas por defecto.
    $features = [];
    if ($durationLabel) {
        $features[] = ['label' => $durationLabel, 'icon' => 'clock'];
    }
    if ($pickup === true) {
        $features[] = ['label' => __('ui.pickup_included'), 'icon' => 'truck'];
    }
    if (filled($language)) {
        $features[] = ['label' => $language, 'icon' => 'globe'];
    }
    if ($freeCancellation === true) {
        $features[] = ['label' => __('ui.free_cancellation'), 'icon' => 'shield'];
    }

    $hasDiscount = $pct && $before && (float) $before > (float) $now;
    $priceText   = $currency . number_format((float) $now, 0);
    $beforeText  = $before ? $currency . number_format((float) $before, 0) : null;
    $imgFallback = asset('assets/banners/banner-hero.jpg');
    $imgClass    = 'absolute inset-0 w-full h-full object-cover object-center text-transparent';
    $pixel       = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
    $ctaClass    = 'bg-teal-900 hover:bg-teal-950 active:bg-teal-950 text-white rounded-xl h-[27px] px-3 inline-flex items-center justify-center gap-1.5 font-semibold text-[13px] transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600';
    $arrow       = '<svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>';
@endphp

<article class="tour-card-row relative flex bg-white rounded-[20px] overflow-hidden shadow-[0_2px_14px_rgba(15,52,56,0.09)] ring-1 ring-teal-800/5 min-h-[170px]" {{ $attributes }}>

    {{-- IMAGEN: ≈44 % del ancho, a toda la altura, pegada a los bordes (solo redondea el radio de la card) --}}
    <div class="relative shrink-0 w-[44%] bg-cream-100">
        <a href="{{ $url }}" tabindex="-1" aria-hidden="true" class="absolute inset-0 block">
            @if ($eager)
                <picture>
                    <source media="(min-width:640px)" srcset="{{ $pixel }}">
                    <img src="{{ $imgSrc }}" alt="{{ $altText }}" class="{{ $imgClass }}"
                         onerror="this.onerror=null;this.src='{{ $imgFallback }}'"
                         width="160" height="220" decoding="async">
                </picture>
            @else
                <img src="{{ $imgSrc }}" alt="{{ $altText }}" class="{{ $imgClass }}"
                     onerror="this.onerror=null;this.src='{{ $imgFallback }}'"
                     loading="lazy" decoding="async" width="160" height="220">
            @endif
        </a>
    </div>

    {{-- CONTENIDO --}}
    <div class="flex-1 min-w-0 p-[7px] pb-2 flex flex-col">
        <h3 class="font-display text-[17px] text-teal-800 leading-[1.05]">
            <a href="{{ $url }}" class="hover:text-orange-700 transition-colors line-clamp-2">{{ $title }}</a>
        </h3>

        @if ($durationLabel)
            <p class="mt-0.5 text-[13px] font-bold text-orange-700 leading-[14px]">{{ $durationLabel }}</p>
        @endif

        @if ($features)
            <ul class="mt-[3px] space-y-px">
                @foreach ($features as $f)
                    <li class="flex items-center gap-2 h-[18px]">
                        <span class="w-[17px] h-[17px] rounded-full bg-cream-100 grid place-items-center shrink-0 text-teal-800" aria-hidden="true">
                            @switch($f['icon'])
                                @case('clock')
                                    <svg class="w-[11px] h-[11px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    @break
                                @case('truck')
                                    <svg class="w-[11px] h-[11px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                                    @break
                                @case('globe')
                                    <svg class="w-[11px] h-[11px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/></svg>
                                    @break
                                @default
                                    <svg class="w-[11px] h-[11px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                            @endswitch
                        </span>
                        <span class="text-xs text-teal-800/70 leading-tight truncate">{{ $f['label'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Precio + CTA: con descuento el botón ocupa todo el ancho; sin descuento va junto al precio --}}
        <div class="mt-auto pt-[3px]">
            <div class="border-t border-teal-800/10 pt-[5px]">
                @if ($hasDiscount)
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-price text-2xl font-bold text-teal-800 leading-[20px]">{{ $priceText }}</p>
                            <p class="text-xs text-teal-800/70 leading-[13px]">{{ __('ui.per_person') }}</p>
                        </div>
                        <div class="flex flex-col items-end gap-0.5 text-right shrink-0">
                            @if ($showOffer)
                                <span class="inline-flex items-center bg-teal-100 text-teal-800 text-xs font-semibold leading-none px-2.5 py-[3px] rounded-full">−{{ $pct }}%</span>
                            @endif
                            <p class="text-xs text-teal-800/70 leading-tight whitespace-nowrap">{{ __('ui.was') }} <span class="line-through">{{ $beforeText }}</span></p>
                        </div>
                    </div>
                    <a href="{{ $url }}" class="{{ $ctaClass }} mt-[5px] w-full">
                        <span>{{ __('ui.book') }}</span>
                        {!! $arrow !!}
                    </a>
                @else
                    <div class="flex items-center gap-2.5">
                        <div class="shrink-0">
                            <p class="font-price text-2xl font-bold text-teal-800 leading-[20px]">{{ $priceText }}</p>
                            <p class="text-xs text-teal-800/70 leading-[13px]">{{ __('ui.per_person') }}</p>
                        </div>
                        <a href="{{ $url }}" class="{{ $ctaClass }} flex-1 min-w-0">
                            <span>{{ __('ui.book') }}</span>
                            {!! $arrow !!}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</article>
