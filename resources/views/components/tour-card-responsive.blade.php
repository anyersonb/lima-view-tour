{{--
    Tarjeta de tour responsive: UN solo <article> por tour.

    variant="list" : fila compacta < 640 px, card vertical desde 640 px (/tours y categorías).
    variant="home" : fila compacta < 768 px, card vertical desde 768 px (solo la home).

    Rediseño 19-nov-2026: estilos propios en public/css/redesign.css (clases .lvt-tc*), SIN depender
    de utilidades Tailwind nuevas (el build de Vite de prod no se recompila). Todo dato es real:
    rating solo con reseñas reales, descuento solo si price_before > price, tags solo con dato del tour.
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
    // Overline (ciudad/categoría). Solo se pinta si el tour tiene el dato real.
    'eyebrow'          => null,
])

@php
    $isHome  = $variant === 'home';
    $locale  = app()->getLocale();
    $url     = $slug ? route('tours.show', ['locale' => $locale, 'slug' => $slug]) : '#';
    $imgFull = $img ?? asset('assets/banners/banner-hero.jpg');
    $altText = $imgAlt ?? $title;

    // Un unico <img>: variantes WebP 480/960 si existen; si no, miniatura 480; el original solo sin ninguna derivada.
    $imgSizes = $isHome ? '(min-width:768px) 342px, 190px' : '(min-width:1024px) 300px, (min-width:640px) 352px, 160px';
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

    // Tags: solo con dato real del tour (sin "Full day"/idioma por defecto inventados).
    $features = [];
    if (filled($duration))          { $features[] = ['label' => $duration, 'icon' => 'clock']; }
    if (filled($language))          { $features[] = ['label' => $language, 'icon' => 'globe']; }
    if ($pickup === true)           { $features[] = ['label' => __('ui.pickup_included'), 'icon' => 'truck']; }
    if ($freeCancellation === true) { $features[] = ['label' => __('ui.free_cancellation'), 'icon' => 'shield']; }
    $iconPaths = [
        'clock'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'truck'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>',
        'globe'  => '<circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/>',
        'shield' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>',
    ];

    // Rating solo con reseñas reales.
    $showRating   = (bool) $rating && (int) $reviews > 0;
    $showBadges   = $topRated || $bestSeller;
    $reviewsLabel = (int) $reviews === 1
        ? $tr('ui.review_one', 'reseña', 'review', 'avaliação')
        : $tr('ui.reviews_lower', 'reseñas', 'reviews', 'avaliações');
@endphp

<article class="lvt-tc lvt-tc--{{ $variant }}" data-ga-select data-ga-item-id="{{ $slug }}" data-ga-item-name="{{ $title }}" data-ga-price="{{ (float) $now }}" {{ $attributes }}>

    {{-- IMAGEN --}}
    <div class="lvt-tc__media">
        <a href="{{ $url }}" tabindex="-1" aria-hidden="true" class="lvt-tc__img-link">
            <img src="{{ $imgSrc }}" @if ($card['srcset']) srcset="{{ $card['srcset'] }}" sizes="{{ $card['sizes'] }}" @endif alt="{{ $altText }}"
                 class="lvt-tc__img"
                 onerror="this.onerror=null;this.src='{{ $imgFallback }}'"
                 @if (! $eager) loading="lazy" @endif decoding="async" width="640" height="480">
        </a>

        @if ($showBadges)
            <div class="lvt-tc__badges">
                @if ($topRated)
                    <span class="lvt-tc__badge lvt-tc__badge--light">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6L2.5 9.4l6.6-.8L12 2.5z"/></svg>
                        {{ $tr('ui.badge_top_rated', 'Mejor valorado', 'Top rated', 'Mais bem avaliado') }}
                    </span>
                @endif
                @if ($bestSeller)
                    <span class="lvt-tc__badge lvt-tc__badge--gold">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.963 2.286a.75.75 0 00-1.071-.136 9.742 9.742 0 00-3.539 6.177A7.547 7.547 0 016.648 6.61a.75.75 0 00-1.152-.082A9 9 0 1015.68 4.534a7.46 7.46 0 01-2.717-2.248zM15.75 14.25a3.75 3.75 0 11-7.313-1.172c.628.465 1.35.81 2.133 1a5.99 5.99 0 011.925-3.545 3.75 3.75 0 013.255 3.717z" clip-rule="evenodd"/></svg>
                        {{ $tr('ui.badge_best_seller', 'Más vendido', 'Best seller', 'Mais vendido') }}
                    </span>
                @endif
            </div>
        @endif
    </div>

    {{-- CONTENIDO --}}
    <div class="lvt-tc__body">
        @if (filled($eyebrow))
            <p class="lvt-tc__eyebrow">{{ $eyebrow }}</p>
        @endif

        <h3 class="lvt-tc__title">
            <a href="{{ $url }}" class="lvt-tc__title-link">{{ $title }}</a>
        </h3>

        @if ($showRating)
            <p class="lvt-tc__rating">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6L2.5 9.4l6.6-.8L12 2.5z"/></svg>
                <b>{{ $rating }}</b>
                <span>({{ number_format((int) $reviews) }} {{ $reviewsLabel }})</span>
            </p>
        @endif

        @if (count($features))
            <ul class="lvt-tc__tags">
                @foreach ($features as $f)
                    <li class="lvt-tc__tag">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">{!! $iconPaths[$f['icon']] !!}</svg>
                        <span>{{ $f['label'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- PRECIO + CTA --}}
        <div class="lvt-tc__foot">
            <div class="lvt-tc__price">
                <span class="lvt-tc__from">{{ __('ui.from') }}</span>
                <span class="lvt-tc__amount">{{ $priceText }}</span>
                <span class="lvt-tc__per">/ {{ __('ui.per_person') }}</span>
                @if ($hasDiscount)
                    <span class="lvt-tc__was"><span class="sr-only">{{ __('ui.was') }} </span><s>{{ $beforeText }}</s>@if ($showOffer) <b class="lvt-tc__save">−{{ $pct }}%</b>@endif</span>
                @endif
            </div>
            <a href="{{ $url }}" data-ga-cta="ver_tour" data-ga-location="card" class="lvt-card-cta lvt-tc__cta">
                <span>{{ $tr('ui.see_experience', 'Ver experiencia', 'View experience', 'Ver experiência') }}</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
            </a>
        </div>
    </div>
</article>
