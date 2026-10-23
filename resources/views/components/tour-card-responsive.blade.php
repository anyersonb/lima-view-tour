{{--
    Tarjeta de tour responsive: UN solo <article> por tour.

    variant="list" : fila compacta < 640 px (imagen cuadrada fija de 124 px + texto), card vertical
                     desde 640 px (/tours y categorias).
    variant="home" : card VERTICAL en todos los anchos (imagen 4:3, titulo, reseñas, grilla 2x2 y caja
                     de precio). En movil usa tipografia reducida; desde 768 px, la de siempre.

    Una sola relacion de imagen en todo el sitio: 4:3.
--}}
@props([
    'variant'          => 'list',
    'title'            => '',
    'slug'             => '',
    'img'              => null,
    'imgThumb'         => null,
    'imgAlt'           => null,
    // Ruta guardada de la portada (p. ej. tours/covers/x.jpg): habilita srcset 480/960/1440 si existen las variantes WebP.
    'imgPath'          => null,
    'before'           => null,
    'now'              => 0,
    'currency'         => 'US$',
    'rating'           => null,
    'reviews'          => 0,
    'duration'         => null,
    'language'         => null,
    'pickup'           => null,
    'freeCancellation' => null,
    'discountPct'      => null,
    'showOffer'        => true,
    'topRated'         => false,
    'bestSeller'       => false,
    'eager'            => false,
])

@php
    $isHome  = $variant === 'home';
    $locale  = app()->getLocale();
    $url     = $slug ? route('tours.show', ['locale' => $locale, 'slug' => $slug]) : '#';
    $imgFull = $img ?? asset('assets/banners/banner-hero.jpg');
    $altText = $imgAlt ?? $title;

    // Un unico <img>: variantes WebP 480/960 si existen; si no, miniatura 480; el original solo sin ninguna derivada.
    $imgSizes = $isHome ? '(min-width:768px) 342px, 320px' : '(min-width:1024px) 300px, (min-width:640px) 352px, 124px';
    $card     = \App\Support\ImageVariants::card($imgPath, $imgSizes);
    $imgSrc   = $card['src'] ?: \App\Support\ImageVariants::relative($imgThumb ?: $imgFull);
    $imgFallback = parse_url(asset('assets/banners/banner-hero.jpg'), PHP_URL_PATH);

    $pct = $discountPct;
    if (! $pct && $before && $now && (float) $before > (float) $now) {
        $pct = (int) round((1 - ((float) $now / (float) $before)) * 100);
    }
    $hasDiscount = $pct && $before && (float) $before > (float) $now;
    $priceText   = $currency . number_format((float) $now, 0);
    $beforeText  = $before ? $currency . number_format((float) $before, 0) : null;

    $tr = function (string $key, string $es, string $en, string $pt) use ($locale): string {
        return \Illuminate\Support\Facades\Lang::has($key) ? __($key) : ($locale === 'pt' ? $pt : ($locale === 'en' ? $en : $es));
    };

    // Características: {label, icono, ocultaEnFila}. En la lista, los vacíos conservan su
    // valor por defecto desde 640 px (como la card vertical de antes); en la home no se inventa nada.
    $features = [];
    if (filled($duration)) {
        $features[] = ['label' => $duration, 'icon' => 'clock', 'rowHidden' => false];
    } elseif (! $isHome) {
        $features[] = ['label' => __('ui.full_day'), 'icon' => 'clock', 'rowHidden' => true];
    }
    if ($pickup === true) {
        $features[] = ['label' => __('ui.pickup_included'), 'icon' => 'truck', 'rowHidden' => false];
    }
    if (filled($language)) {
        $features[] = ['label' => $language, 'icon' => 'globe', 'rowHidden' => false];
    } elseif (! $isHome) {
        $features[] = ['label' => __('ui.language_default'), 'icon' => 'globe', 'rowHidden' => true];
    }
    if ($freeCancellation === true) {
        $features[] = ['label' => __('ui.free_cancellation'), 'icon' => 'shield', 'rowHidden' => false];
    }
    $featCount = count($features);

    // Fila compacta (< 640): solo 2 características, siempre las mismas por prioridad
    // (duración, cancelación, recojo, idioma) para que todas las filas midan igual.
    if (! $isHome) {
        $prio  = ['clock' => 0, 'shield' => 1, 'truck' => 2, 'globe' => 3];
        $show2 = collect($features)->filter(fn ($f) => ! $f['rowHidden'])
            ->sortBy(fn ($f) => $prio[$f['icon']])->take(2)->pluck('icon')->all();
        foreach ($features as $k => $f) {
            $features[$k]['rowHidden'] = $f['rowHidden'] || ! in_array($f['icon'], $show2, true);
        }
    }

    $iconPaths = [
        'clock'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'truck'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>',
        'globe'  => '<circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/>',
        'shield' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>',
    ];

    $showRating = $isHome ? ($rating && (int) $reviews > 0) : (bool) $rating;
    $showBadges = $isHome && ($topRated || $bestSeller);
    $homeRowsSpan = $featCount > 2 ? 'row-span-2' : 'row-span-1';
    $ctaSpan = $hasDiscount ? 'row-span-2' : 'row-span-1';
@endphp

@if ($isHome)
<article class="relative flex flex-col h-full bg-white rounded-3xl overflow-hidden shadow-[0_10px_30px_rgba(15,52,56,0.12)] ring-1 ring-teal-800/5 hover:shadow-[0_14px_36px_rgba(15,52,56,0.18)] transition-shadow" {{ $attributes }}>

    {{-- IMAGEN --}}
    <div class="relative shrink-0 w-full bg-cream-100">
        <a href="{{ $url }}" tabindex="-1" aria-hidden="true" class="block aspect-[4/3] bg-cream-100">
            <img src="{{ $imgSrc }}" @if ($card['srcset']) srcset="{{ $card['srcset'] }}" sizes="{{ $card['sizes'] }}" @endif alt="{{ $altText }}"
                 class="w-full h-full object-cover object-center text-transparent"
                 onerror="this.onerror=null;this.src='{{ $imgFallback }}'"
                 @if (! $eager) loading="lazy" @endif decoding="async" width="640" height="480">
        </a>

        @if ($showBadges)
            <div class="flex absolute top-3 left-3 md:top-4 md:left-4 flex-col items-start gap-2">
                @if ($topRated)
                    <span class="inline-flex items-center gap-2 h-7 md:h-8 pl-2.5 pr-3.5 rounded-full bg-white text-teal-900 text-xs md:text-[13px] font-semibold leading-none shadow-md">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6L2.5 9.4l6.6-.8L12 2.5z"/></svg>
                        {{ $tr('ui.badge_top_rated', 'Mejor valorado', 'Top rated', 'Mais bem avaliado') }}
                    </span>
                @endif
                @if ($bestSeller)
                    <span class="inline-flex items-center gap-2 h-7 md:h-8 pl-2.5 pr-3.5 rounded-full bg-orange-700 text-white text-xs md:text-[13px] font-semibold leading-none shadow-md">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.963 2.286a.75.75 0 00-1.071-.136 9.742 9.742 0 00-3.539 6.177A7.547 7.547 0 016.648 6.61a.75.75 0 00-1.152-.082A9 9 0 1015.68 4.534a7.46 7.46 0 01-2.717-2.248zM15.75 14.25a3.75 3.75 0 11-7.313-1.172c.628.465 1.35.81 2.133 1a5.99 5.99 0 011.925-3.545 3.75 3.75 0 013.255 3.717z" clip-rule="evenodd"/></svg>
                        {{ $tr('ui.badge_best_seller', 'Más vendido', 'Best seller', 'Mais vendido') }}
                    </span>
                @endif
            </div>
        @endif
    </div>

    {{-- CONTENIDO --}}
    <div class="flex flex-col flex-1 min-w-0 px-4 pt-4 pb-2">
        <h3 class="font-display text-[17px] leading-[1.15] text-teal-800 uppercase md:text-[22px]">
            <a href="{{ $url }}" class="hover:text-orange-700 transition-colors after:absolute after:inset-0">{{ $title }}</a>
        </h3>

        @if ($showRating)
            <p class="flex items-center gap-2 mt-2.5 text-sm md:text-[15px]">
                <span class="text-orange-500 text-[17px] leading-none tracking-tight" aria-hidden="true">★★★★★</span>
                <span class="font-semibold text-teal-800">{{ $rating }}</span>
                <span class="text-teal-800/70" aria-hidden="true">•</span>
                <span class="text-teal-800/70">{{ number_format((int) $reviews) }} {{ __('ui.reviews') }}</span>
            </p>
        @endif

        @if ($featCount)
            <ul class="mt-3 grid grid-cols-[minmax(0,1fr)_1px_minmax(0,1fr)] border-t border-teal-800/10">
                @foreach ($features as $i => $f)
                    @php
                        $rowLine = $featCount > 2 && $i < 2;
                        $homeCell = $i % 2 === 0
                            ? 'col-start-1 pr-2 md:pr-3'
                            : 'col-start-3 pl-2 md:pl-3';
                        $homeLine = $rowLine
                            ? ($i % 2 === 0
                                ? '[background:linear-gradient(rgba(15,52,56,0.1),rgba(15,52,56,0.1))_left_bottom/calc(100%-12px)_1px_no-repeat]'
                                : '[background:linear-gradient(rgba(15,52,56,0.1),rgba(15,52,56,0.1))_right_bottom/calc(100%-12px)_1px_no-repeat]')
                            : '';
                    @endphp
                    <li class="flex items-center gap-2 md:gap-2.5 py-2 {{ $homeCell }} {{ $homeLine }}">
                        <span class="w-7 h-7 md:w-[34px] md:h-[34px] rounded-full bg-cream-100 grid place-items-center shrink-0 text-teal-800" aria-hidden="true">
                            <svg class="w-4 h-4 md:w-[18px] md:h-[18px] [stroke-width:1.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $iconPaths[$f['icon']] !!}</svg>
                        </span>
                        <span class="min-w-0 text-xs md:text-sm font-medium text-teal-800 leading-tight">{{ $f['label'] }}</span>
                    </li>
                @endforeach
                @if ($featCount > 1)
                    <li class="col-start-2 row-start-1 {{ $homeRowsSpan }} w-px my-2 bg-teal-800/10" aria-hidden="true"></li>
                @endif
            </ul>
        @endif

        {{-- PRECIO + CTA --}}
        <div class="mt-auto pt-4 -mx-2">
            <div class="grid bg-cream-100 rounded-2xl p-3 grid-cols-[minmax(0,1fr)_auto] gap-x-3 gap-y-2 items-center">

                {{-- Precio actual --}}
                <div class="col-start-1 {{ $hasDiscount ? 'min-w-0 row-start-2' : 'shrink-0 row-start-1' }} flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                    <span class="hidden md:block font-bold uppercase text-emerald-700 leading-none text-[11px] tracking-wider">{{ __('ui.now_price') }}</span>
                    <p class="font-price text-2xl md:text-[34px] font-bold text-teal-800 leading-none">{{ $priceText }}</p>
                    <span class="block text-xs text-teal-800/70 leading-none">{{ __('ui.per_person') }}</span>
                </div>

                {{-- Descuento + precio anterior --}}
                @if ($hasDiscount)
                    <div class="col-start-1 row-start-1 col-span-1 flex flex-row flex-wrap items-center text-left gap-x-2 gap-y-1">
                        <span class="inline-flex items-center gap-1 bg-red-50 text-red-700 border border-red-200 font-bold rounded-lg whitespace-nowrap leading-tight shrink-0 text-xs px-2.5 py-1">
                            <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/></svg>
                            {{ $pct }}% {{ __('ui.discount_label') }}
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="text-xs md:text-[11px] text-teal-800/70 font-medium uppercase tracking-wide leading-none">{{ __('ui.before_price') }}</span>
                            <span class="font-price text-sm text-teal-800/70 line-through leading-none">{{ $beforeText }}</span>
                        </span>
                    </div>
                @endif

                {{-- CTA --}}
                <a href="{{ $url }}" class="relative z-10 col-span-1 col-start-2 row-start-1 {{ $ctaSpan }} bg-teal-900 hover:bg-teal-950 active:bg-teal-950 text-white rounded-full h-11 md:h-[48px] pl-4 md:pl-5 pr-1.5 inline-flex items-center justify-start gap-2.5 md:gap-3 font-semibold text-sm md:text-[15px] shadow-lg transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
                    <span class="whitespace-nowrap">{{ __('ui.see_tour') }}</span>
                    <span class="grid shrink-0 w-8 h-8 md:w-9 md:h-9 rounded-full bg-orange-500 place-items-center">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </span>
                </a>
            </div>
        </div>
    </div>
</article>
@else
<article class="flex gap-3 p-3 bg-white rounded-[20px] overflow-hidden shadow-[0_2px_14px_rgba(15,52,56,0.09)] ring-1 ring-teal-800/5 min-h-[192px] sm:flex-col sm:gap-0 sm:p-0 sm:rounded-3xl sm:shadow-md sm:min-h-0 sm:h-full sm:border sm:border-slate-100 sm:hover:shadow-lg sm:transition-shadow" {{ $attributes }}>

    {{-- IMAGEN: cuadrada fija de 124 px en fila, 4:3 en card vertical --}}
    <div class="relative shrink-0 self-start w-[124px] h-[124px] rounded-2xl overflow-hidden bg-cream-100 sm:w-full sm:h-auto sm:rounded-none sm:overflow-visible sm:bg-transparent">
        <a href="{{ $url }}" tabindex="-1" aria-hidden="true" class="block w-full h-full sm:h-auto sm:aspect-[4/3] sm:bg-cream-100">
            <img src="{{ $imgSrc }}" @if ($card['srcset']) srcset="{{ $card['srcset'] }}" sizes="{{ $card['sizes'] }}" @endif alt="{{ $altText }}"
                 class="w-full h-full object-cover object-center text-transparent"
                 onerror="this.onerror=null;this.src='{{ $imgFallback }}'"
                 @if (! $eager) loading="lazy" @endif decoding="async" width="640" height="480">
        </a>
        @if ($hasDiscount && $showOffer)
            <span class="absolute top-1.5 left-1.5 sm:hidden inline-flex items-center bg-teal-100 text-teal-800 text-xs font-semibold leading-none px-2 py-1 rounded-full">−{{ $pct }}%</span>
        @endif
    </div>

    {{-- CONTENIDO --}}
    <div class="flex flex-col flex-1 min-w-0 sm:p-6">
        <h3 class="font-display text-[17px] text-teal-800 leading-[1.15] sm:text-2xl sm:leading-8">
            <a href="{{ $url }}" class="hover:text-orange-700 transition-colors line-clamp-3 sm:line-clamp-none">{{ $title }}</a>
        </h3>

        @if ($showRating)
            <p class="hidden items-center gap-2 sm:flex mt-3 text-sm">
                <span class="text-orange-400 text-lg leading-none tracking-tight" aria-hidden="true">★★★★★</span>
                <span class="font-semibold text-teal-800">{{ $rating }}</span>
                <span class="text-teal-800/70" aria-hidden="true">·</span>
                <span class="text-teal-800/70">{{ number_format((int) $reviews) }} {{ __('ui.reviews') }}</span>
            </p>
        @endif

        @if ($featCount)
            <ul class="mt-1.5 space-y-0.5 sm:mt-3 sm:space-y-0 sm:grid sm:grid-cols-2 sm:gap-2 sm:text-sm">
                @foreach ($features as $f)
                    <li class="flex items-center gap-2 h-[18px] {{ $f['rowHidden'] ? 'max-sm:hidden' : '' }} sm:h-auto sm:bg-cream-100 sm:rounded-xl sm:ring-1 sm:ring-teal-800/5 sm:py-2.5 sm:px-2.5">
                        <span class="w-[17px] h-[17px] rounded-full bg-cream-100 grid place-items-center shrink-0 text-teal-800 sm:w-8 sm:h-8 sm:bg-white sm:shadow-sm" aria-hidden="true">
                            <svg class="w-[11px] h-[11px] sm:w-4 sm:h-4 sm:[stroke-width:1.5]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">{!! $iconPaths[$f['icon']] !!}</svg>
                        </span>
                        <span class="min-w-0 text-xs text-teal-800/70 leading-tight truncate sm:font-bold sm:text-teal-800 sm:text-[13px] sm:overflow-visible sm:whitespace-normal sm:text-clip">{{ $f['label'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- PRECIO + CTA --}}
        <div class="mt-auto pt-2 sm:pt-4">
            <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-2 items-center border-t border-teal-800/10 pt-1.5 sm:border-t-0 sm:bg-cream-100 sm:rounded-2xl sm:ring-1 sm:ring-teal-800/5 sm:p-3 sm:grid-cols-[minmax(min-content,1fr)_auto] sm:gap-y-2">

                {{-- Precio actual --}}
                <div class="col-start-1 row-start-2 min-w-0 sm:flex sm:flex-col sm:gap-0.5">
                    <span class="hidden font-bold uppercase text-emerald-700 leading-none sm:block text-[9px] tracking-widest">{{ __('ui.now_price') }}</span>
                    <p class="font-price text-2xl font-bold text-teal-800 leading-[24px] sm:text-[26px] sm:leading-none">{{ $priceText }}</p>
                    <span class="block text-xs text-teal-800/70 leading-[13px] sm:inline-flex sm:items-center sm:gap-0.5 sm:text-[10px] sm:mt-0.5 sm:leading-none">
                        <svg class="hidden w-3 h-3 shrink-0 sm:block" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd"/></svg>
                        {{ __('ui.per_person') }}
                    </span>
                </div>

                {{-- Descuento + precio anterior (en fila: solo "Antes US$X"; el −% va sobre la imagen) --}}
                @if ($hasDiscount)
                    <div class="col-start-1 row-start-1 flex items-center gap-2 sm:col-span-2 sm:text-left sm:min-h-[26px]">
                        <span class="hidden items-center gap-1 bg-red-50 text-red-700 border border-red-200 font-bold rounded-lg whitespace-nowrap leading-tight shrink-0 sm:inline-flex text-[10px] px-2 py-1">
                            <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/></svg>
                            {{ $pct }}% {{ __('ui.discount_label') }}
                        </span>
                        <p class="text-xs text-teal-800/70 leading-[15px] whitespace-nowrap sm:hidden">{{ __('ui.was') }} <span class="line-through">{{ $beforeText }}</span></p>
                        <span class="hidden items-center gap-1 sm:flex">
                            <span class="text-[10px] text-teal-800/70 font-medium uppercase tracking-wide leading-none">{{ __('ui.before_price') }}</span>
                            <span class="font-price text-[13px] text-teal-800/70 line-through leading-none">{{ $beforeText }}</span>
                        </span>
                    </div>
                @else
                    <div class="col-start-1 row-start-1 h-[15px] sm:col-span-2 sm:h-[26px]" aria-hidden="true"></div>
                @endif

                {{-- CTA --}}
                <a href="{{ $url }}" class="col-start-2 row-start-1 row-span-2 self-center bg-teal-900 hover:bg-teal-950 active:bg-teal-950 text-white rounded-full h-10 px-3.5 inline-flex items-center justify-center gap-1.5 font-semibold text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 sm:row-span-1 sm:row-start-2 sm:self-auto sm:h-auto sm:pl-4 sm:pr-1.5 sm:py-1.5 sm:gap-2 sm:justify-start sm:shadow-md">
                    <span class="sm:hidden">{{ __('ui.book') }}</span>
                    <span class="hidden whitespace-nowrap sm:inline">{{ __('ui.see_tour') }}</span>
                    <span class="hidden shrink-0 sm:grid sm:w-8 sm:h-8 sm:rounded-full sm:bg-orange-500 sm:place-items-center">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </span>
                </a>
            </div>
        </div>
    </div>
</article>
@endif
