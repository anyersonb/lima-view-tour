{{--
    Tarjeta de tour responsive: UN solo <article> por tour (antes /tours y las
    categorías pintaban fila + card por separado y ocultaban una con CSS).

    variant="list" : fila compacta < 640 px, card vertical desde 640 px (/tours y categorías).
    variant="home" : fila compacta < 768 px, card nueva (badges + reseñas reales + grilla 2x2
                     + caja de precio) desde 768 px (solo la home).

    Los bloques que solo existen en un diseño (descripción de duración de la fila, "AHORA",
    pills de descuento, badges) son nodos pequeños con max-*:hidden / hidden bp:block;
    imagen, título, características y CTA son los mismos nodos en ambos diseños.
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
    $bp      = fn (string $list, string $home): string => $isHome ? $home : $list;
    $locale  = app()->getLocale();
    $url     = $slug ? route('tours.show', ['locale' => $locale, 'slug' => $slug]) : '#';
    $imgFull = $img ?? asset('assets/banners/banner-hero.jpg');
    $imgSmall = $imgThumb ?: $imgFull;
    $altText = $imgAlt ?? $title;
    $imgFallback = asset('assets/banners/banner-hero.jpg');

    // Variantes WebP responsive (ImageVariants::srcset devuelve null si no hay ninguna => se mantiene el <picture> de siempre).
    $variantSrcset = ($imgPath && class_exists(\App\Support\ImageVariants::class)) ? \App\Support\ImageVariants::srcset($imgPath) : null;
    if ($variantSrcset && ! str_contains($variantSrcset, ' 1440w')) {
        $variantSrcset .= ', ' . $imgFull . ' 1441w'; // el original cubre lo que las variantes no alcanzan
    }
    $imgSizes = $isHome ? '(min-width:768px) 342px, 190px' : '(min-width:1024px) 300px, (min-width:640px) 352px, 160px';

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
    $iconPaths = [
        'clock'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'truck'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>',
        'globe'  => '<circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/>',
        'shield' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>',
    ];

    $showRating = $isHome ? ($rating && (int) $reviews > 0) : (bool) $rating;
    $showBadges = $isHome && ($topRated || $bestSeller);
    $homeRowsSpan = $featCount > 2 ? 'md:row-span-2' : 'md:row-span-1';
    $ctaSpan = $hasDiscount ? 'md:row-span-2' : 'md:row-span-1';
@endphp

<article class="flex bg-white rounded-[20px] overflow-hidden shadow-[0_2px_14px_rgba(15,52,56,0.09)] ring-1 ring-teal-800/5 min-h-[170px] {{ $bp(
    'sm:flex-col sm:rounded-3xl sm:shadow-md sm:min-h-0 sm:h-full sm:border sm:border-slate-100 sm:hover:shadow-lg sm:transition-shadow',
    'md:flex-col md:rounded-3xl md:shadow-[0_10px_30px_rgba(15,52,56,0.12)] md:min-h-0 md:h-full md:hover:shadow-[0_14px_36px_rgba(15,52,56,0.18)] md:transition-shadow'
) }}" {{ $attributes }}>

    {{-- IMAGEN --}}
    <div class="relative shrink-0 w-[44%] bg-cream-100 {{ $bp('sm:w-full sm:bg-transparent', 'md:w-full') }}">
        <a href="{{ $url }}" tabindex="-1" aria-hidden="true" class="absolute inset-0 block {{ $bp('sm:static sm:aspect-[4/3] sm:bg-cream-100', 'md:static md:aspect-[6/5] md:bg-cream-100') }}">
            <picture class="contents">
                @if (! $variantSrcset && $imgSmall !== $imgFull)
                    <source media="{{ $bp('(min-width:640px)', '(min-width:768px)') }}" srcset="{{ $imgFull }}">
                @endif
                <img src="{{ $variantSrcset ? \App\Support\ImageVariants::responsive($imgPath, 480) : $imgSmall }}" @if ($variantSrcset) srcset="{{ $variantSrcset }}" sizes="{{ $imgSizes }}" @endif alt="{{ $altText }}"
                     class="absolute inset-0 w-full h-full object-cover object-center text-transparent {{ $bp('sm:static', 'md:static') }}"
                     onerror="this.onerror=null;this.src='{{ $imgFallback }}'"
                     @if (! $eager) loading="lazy" @endif decoding="async" width="640" height="480">
            </picture>
        </a>

        @if ($showBadges)
            <div class="hidden md:flex absolute top-4 left-4 flex-col items-start gap-2">
                @if ($topRated)
                    <span class="inline-flex items-center gap-2 h-8 pl-2.5 pr-3.5 rounded-full bg-white text-teal-900 text-[13px] font-semibold leading-none shadow-md">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6L2.5 9.4l6.6-.8L12 2.5z"/></svg>
                        {{ $tr('ui.badge_top_rated', 'Mejor valorado', 'Top rated', 'Mais bem avaliado') }}
                    </span>
                @endif
                @if ($bestSeller)
                    <span class="inline-flex items-center gap-2 h-8 pl-2.5 pr-3.5 rounded-full bg-orange-700 text-white text-[13px] font-semibold leading-none shadow-md">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.963 2.286a.75.75 0 00-1.071-.136 9.742 9.742 0 00-3.539 6.177A7.547 7.547 0 016.648 6.61a.75.75 0 00-1.152-.082A9 9 0 1015.68 4.534a7.46 7.46 0 01-2.717-2.248zM15.75 14.25a3.75 3.75 0 11-7.313-1.172c.628.465 1.35.81 2.133 1a5.99 5.99 0 011.925-3.545 3.75 3.75 0 013.255 3.717z" clip-rule="evenodd"/></svg>
                        {{ $tr('ui.badge_best_seller', 'Más vendido', 'Best seller', 'Mais vendido') }}
                    </span>
                @endif
            </div>
        @endif
    </div>

    {{-- CONTENIDO --}}
    <div class="flex flex-col flex-1 min-w-0 p-[7px] pb-2 {{ $bp('sm:p-6', 'md:px-4 md:pt-4 md:pb-2') }}">
        <h3 class="font-display text-[17px] text-teal-800 leading-[1.05] {{ $bp('sm:text-2xl sm:leading-8', 'md:text-[22px] md:leading-[1.15] md:uppercase') }}">
            <a href="{{ $url }}" class="hover:text-orange-700 transition-colors line-clamp-2 {{ $bp('sm:line-clamp-none', 'md:line-clamp-none') }}">{{ $title }}</a>
        </h3>

        @if (filled($duration))
            <p class="mt-0.5 text-[13px] font-bold text-orange-700 leading-[14px] {{ $bp('sm:hidden', 'md:hidden') }}">{{ $duration }}</p>
        @endif

        @if ($showRating)
            <p class="hidden items-center gap-2 {{ $bp('sm:flex mt-3 text-sm', 'md:flex mt-2.5 text-[15px]') }}">
                <span class="{{ $bp('text-orange-400 text-lg', 'text-orange-500 text-[17px]') }} leading-none tracking-tight" aria-hidden="true">★★★★★</span>
                <span class="font-semibold text-teal-800">{{ $rating }}</span>
                <span class="text-teal-800/70" aria-hidden="true">{{ $bp('·', '•') }}</span>
                <span class="text-teal-800/70">{{ number_format((int) $reviews) }} {{ __('ui.reviews') }}</span>
            </p>
        @endif

        @if ($featCount)
            <ul class="mt-[3px] space-y-px {{ $bp('sm:mt-3 sm:space-y-0 sm:grid sm:grid-cols-2 sm:gap-2 sm:text-sm', 'md:mt-3 md:space-y-0 md:grid md:grid-cols-[minmax(0,1fr)_1px_minmax(0,1fr)] md:border-t md:border-teal-800/10') }}">
                @foreach ($features as $i => $f)
                    @php
                        $rowLine = $isHome && $featCount > 2 && $i < 2;
                        $homeCell = $i % 2 === 0
                            ? 'md:col-start-1 md:pr-3'
                            : 'md:col-start-3 md:pl-3';
                        $homeLine = $rowLine
                            ? ($i % 2 === 0
                                ? 'md:[background:linear-gradient(rgba(15,52,56,0.1),rgba(15,52,56,0.1))_left_bottom/calc(100%-12px)_1px_no-repeat]'
                                : 'md:[background:linear-gradient(rgba(15,52,56,0.1),rgba(15,52,56,0.1))_right_bottom/calc(100%-12px)_1px_no-repeat]')
                            : '';
                    @endphp
                    <li class="flex items-center gap-2 h-[18px] {{ $f['rowHidden'] ? $bp('max-sm:hidden', 'max-md:hidden') : '' }} {{ $bp(
                            'sm:h-auto sm:bg-cream-100 sm:rounded-xl sm:ring-1 sm:ring-teal-800/5 sm:py-2.5 sm:px-2.5',
                            'md:h-auto md:gap-2.5 md:py-2 ' . $homeCell . ' ' . $homeLine
                        ) }}">
                        <span class="w-[17px] h-[17px] rounded-full bg-cream-100 grid place-items-center shrink-0 text-teal-800 {{ $bp('sm:w-8 sm:h-8 sm:bg-white sm:shadow-sm', 'md:w-[34px] md:h-[34px]') }}" aria-hidden="true">
                            <svg class="w-[11px] h-[11px] {{ $bp('sm:w-4 sm:h-4 sm:[stroke-width:1.5]', 'md:w-[18px] md:h-[18px] md:[stroke-width:1.5]') }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">{!! $iconPaths[$f['icon']] !!}</svg>
                        </span>
                        <span class="min-w-0 text-xs text-teal-800/70 leading-tight truncate {{ $bp(
                            'sm:font-bold sm:text-teal-800 sm:text-[13px] sm:overflow-visible sm:whitespace-normal sm:text-clip',
                            'md:font-medium md:text-teal-800 md:text-sm md:overflow-visible md:whitespace-normal md:text-clip'
                        ) }}">{{ $f['label'] }}</span>
                    </li>
                @endforeach
                @if ($isHome && $featCount > 1)
                    <li class="hidden md:block md:col-start-2 md:row-start-1 {{ $homeRowsSpan }} md:w-px md:my-2 md:bg-teal-800/10" aria-hidden="true"></li>
                @endif
            </ul>
        @endif

        {{-- PRECIO + CTA --}}
        <div class="mt-auto pt-[3px] {{ $bp('sm:pt-4', 'md:pt-4 md:-mx-2') }}">
            <div class="grid border-t border-teal-800/10 pt-[5px] {{ $hasDiscount ? 'grid-cols-[minmax(0,1fr)_auto] gap-x-2 items-start' : 'grid-cols-[auto_minmax(0,1fr)] gap-x-2.5 items-center' }} {{ $bp(
                'sm:border-t-0 sm:bg-cream-100 sm:rounded-2xl sm:ring-1 sm:ring-teal-800/5 sm:p-3 sm:grid-cols-[minmax(min-content,1fr)_auto] sm:gap-x-2 sm:gap-y-2 sm:items-center',
                'md:border-t-0 md:bg-cream-100 md:rounded-2xl md:p-3 md:grid-cols-[minmax(0,1fr)_auto] md:gap-x-3 md:gap-y-2 md:items-center'
            ) }}">

                {{-- Precio actual --}}
                <div class="col-start-1 row-start-1 {{ $hasDiscount ? 'min-w-0' : 'shrink-0' }} {{ $bp(
                    'sm:row-start-2 sm:flex sm:flex-col sm:gap-0.5',
                    ($hasDiscount ? 'md:row-start-2' : 'md:row-start-1') . ' md:flex md:flex-wrap md:items-baseline md:gap-x-2 md:gap-y-0.5'
                ) }}">
                    <span class="hidden font-bold uppercase text-emerald-700 leading-none {{ $bp('sm:block text-[9px] tracking-widest', 'md:block text-[11px] tracking-wider') }}">{{ __('ui.now_price') }}</span>
                    <p class="font-price text-2xl font-bold text-teal-800 leading-[20px] {{ $bp('sm:text-[26px] sm:leading-none', 'md:text-[34px] md:leading-none') }}">{{ $priceText }}</p>
                    <span class="block text-xs text-teal-800/70 leading-[13px] {{ $bp('sm:inline-flex sm:items-center sm:gap-0.5 sm:text-[10px] sm:mt-0.5 sm:leading-none', 'md:text-xs md:leading-none') }}">
                        @unless ($isHome)
                            <svg class="hidden w-3 h-3 shrink-0 sm:block" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd"/></svg>
                        @endunless
                        {{ __('ui.per_person') }}
                    </span>
                </div>

                {{-- Descuento + precio anterior --}}
                @if ($hasDiscount)
                    <div class="col-start-2 row-start-1 flex flex-col items-end gap-0.5 text-right {{ $bp(
                        'sm:col-start-1 sm:col-span-2 sm:flex-row sm:items-center sm:text-left sm:gap-2 sm:min-h-[26px]',
                        'md:col-start-1 md:col-span-1 md:flex-row md:flex-wrap md:items-center md:text-left md:gap-x-2 md:gap-y-1'
                    ) }}">
                        @if ($showOffer)
                            <span class="inline-flex items-center bg-teal-100 text-teal-800 text-xs font-semibold leading-none px-2.5 py-[3px] rounded-full {{ $bp('sm:hidden', 'md:hidden') }}">−{{ $pct }}%</span>
                        @endif
                        <span class="hidden items-center gap-1 bg-red-50 text-red-700 border border-red-200 font-bold rounded-lg whitespace-nowrap leading-tight shrink-0 {{ $bp('sm:inline-flex text-[10px] px-2 py-1', 'md:inline-flex text-xs px-2.5 py-1') }}">
                            <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/></svg>
                            {{ $pct }}% {{ __('ui.discount_label') }}
                        </span>
                        <p class="text-xs text-teal-800/70 leading-tight whitespace-nowrap {{ $bp('sm:hidden', 'md:hidden') }}">{{ __('ui.was') }} <span class="line-through">{{ $beforeText }}</span></p>
                        <span class="hidden items-center gap-1 {{ $bp('sm:flex', 'md:flex') }}">
                            <span class="{{ $bp('text-[10px]', 'text-[11px]') }} text-teal-800/70 font-medium uppercase tracking-wide leading-none">{{ __('ui.before_price') }}</span>
                            <span class="font-price {{ $bp('text-[13px]', 'text-sm') }} text-teal-800/70 line-through leading-none">{{ $beforeText }}</span>
                        </span>
                    </div>
                @elseif (! $isHome)
                    <div class="hidden sm:block sm:col-span-2 sm:row-start-1 h-[26px]" aria-hidden="true"></div>
                @endif

                {{-- CTA --}}
                <a href="{{ $url }}" class="{{ $hasDiscount ? 'col-start-1 col-span-2 row-start-2 mt-[5px]' : 'col-start-2 row-start-1 min-w-0' }} bg-teal-900 hover:bg-teal-950 active:bg-teal-950 text-white rounded-xl h-[27px] px-3 inline-flex items-center justify-center gap-1.5 font-semibold text-[13px] transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 {{ $bp(
                    'sm:col-span-1 sm:col-start-2 sm:row-start-2 sm:mt-0 sm:rounded-full sm:h-auto sm:pl-4 sm:pr-1.5 sm:py-1.5 sm:gap-2 sm:text-sm sm:justify-start sm:shadow-md',
                    'md:col-span-1 md:col-start-2 md:row-start-1 ' . $ctaSpan . ' md:mt-0 md:rounded-full md:h-[48px] md:pl-5 md:pr-1.5 md:gap-3 md:text-[15px] md:justify-start md:shadow-lg'
                ) }}">
                    <span class="{{ $bp('sm:hidden', 'md:hidden') }}">{{ __('ui.book') }}</span>
                    <span class="hidden whitespace-nowrap {{ $bp('sm:inline', 'md:inline') }}">{{ __('ui.see_tour') }}</span>
                    <span class="inline-flex shrink-0 {{ $bp('sm:grid sm:w-8 sm:h-8 sm:rounded-full sm:bg-orange-500 sm:place-items-center', 'md:grid md:w-9 md:h-9 md:rounded-full md:bg-orange-500 md:place-items-center') }}">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </span>
                </a>
            </div>
        </div>
    </div>
</article>
