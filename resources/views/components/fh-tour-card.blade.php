{{--
    Tarjeta "Elige tu experiencia" del home FINAL (diseño aprobado 20-nov-2026).
    Vertical en todos los anchos (2 columnas en móvil, 4 en escritorio). Estilos: public/css/redesign.css (.fh-card*).
    Datos: un elemento de $homePick (contrato "FINAL HOME" §3). Todo dato es real: insignia = badge_text del admin;
    descuento solo si discount_percent !== null; estrellas solo con reseñas publicadas; chips solo con datos/flags reales.
    $img = ['src' => ?string, 'srcset' => ?string, 'sizes' => ?string] opcional (variantes WebP de la portada).
--}}
@props(['p' => [], 'img' => []])

@php
    $locale = app()->getLocale();
    $L = fn (string $es, string $en, string $pt): string => $locale === 'pt' ? $pt : ($locale === 'en' ? $en : $es);

    $pct = $p['discount_percent'] ?? null;
    $hasDiscount = $pct !== null && ! empty($p['price_before']) && (float) $p['price_before'] > (float) ($p['price'] ?? 0);

    $stars = null;
    if (! empty($p['rating']) && (int) ($p['rating']['count'] ?? 0) > 0) {
        $stars = max(1, min(5, (int) round((float) $p['rating']['rating'])));
    }

    // Chips: etiquetas cortas como en el diseño ("Recojo", "ES / EN"); el chip real "especial" (cancelación / grupo) va en naranja.
    $chips = [];
    $accent = null;
    foreach (($p['chips'] ?? []) as $c) {
        $label = (string) ($c['label'] ?? '');
        if ($label === '') { continue; }
        $type = $c['type'] ?? '';
        if ($type === 'pickup') { $label = $L('Recojo', 'Pickup', 'Transporte'); }
        if ($type === 'language') {
            $label = str_replace(['Español', 'Spanish', 'Espanhol', 'Inglés', 'English', 'Inglês', 'Portugués', 'Portuguese', 'Português'], ['ES', 'ES', 'ES', 'EN', 'EN', 'EN', 'PT', 'PT', 'PT'], $label);
        }
        if (in_array($type, ['cancellation', 'group'], true)) { $accent = $label; continue; }
        $chips[] = $label;
    }

    $imgSrc = $img['src'] ?? ($p['image'] ?? asset('assets/banners/banner-hero.jpg'));
    $imgFallback = parse_url(asset('assets/banners/banner-hero.jpg'), PHP_URL_PATH);
@endphp

<article class="fh-card" data-ga-select data-ga-item-id="{{ $p['id'] ?? '' }}" data-ga-item-name="{{ $p['title'] ?? '' }}" data-ga-price="{{ (float) ($p['price'] ?? 0) }}">
    <div class="fh-card__media">
        <a href="{{ $p['url'] ?? '#' }}" tabindex="-1" aria-hidden="true" class="fh-card__img-link">
            <img src="{{ $imgSrc }}" @if (! empty($img['srcset'])) srcset="{{ $img['srcset'] }}" sizes="{{ $img['sizes'] ?? '' }}" @endif
                 alt="{{ $p['title'] ?? '' }}" class="fh-card__img" width="640" height="533" loading="lazy" decoding="async"
                 onerror="this.onerror=null;this.src='{{ $imgFallback }}'">
        </a>
        @if (filled($p['badge'] ?? null))
            <span class="fh-card__badge"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.8 6.2L20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8L12 2z"/></svg>{{ $p['badge'] }}</span>
        @endif
        @if (filled($p['region'] ?? null))
            <span class="fh-card__region">{{ $p['region'] }}</span>
        @endif
    </div>

    <div class="fh-card__body">
        <h3 class="fh-card__title"><a href="{{ $p['url'] ?? '#' }}" class="fh-card__title-link">{{ $p['title'] ?? '' }}</a></h3>

        @if ($stars)
            <p class="fh-card__rate">
                <span class="fh-stars" role="img" aria-label="{{ number_format((float) $p['rating']['rating'], 1) }} / 5">@for ($i = 1; $i <= 5; $i++)<svg viewBox="0 0 24 24" aria-hidden="true" class="{{ $i <= $stars ? 'on' : 'off' }}"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6L2.5 9.4l6.6-.8L12 2.5z" fill="currentColor"/></svg>@endfor</span>
                <a href="#opiniones" class="fh-card__opin">{{ $L('Ver opiniones', 'See reviews', 'Ver opiniões') }} <span aria-hidden="true">›</span></a>
            </p>
        @endif

        @if (count($chips) || $accent)
            <ul class="fh-card__chips">
                @foreach ($chips as $c)<li>{{ $c }}</li>@endforeach
                @if ($accent)<li class="is-accent">{{ $accent }}</li>@endif
            </ul>
        @endif

        <div class="fh-card__buy">
            @if ($hasDiscount)
                <div class="fh-card__was">
                    <span class="fh-card__off"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 12.6V4.5A1.5 1.5 0 014.5 3h8.1a1.5 1.5 0 011.06.44l7.9 7.9a1.5 1.5 0 010 2.12l-7.2 7.2a1.5 1.5 0 01-2.12 0l-7.9-7.9A1.5 1.5 0 013 12.6zM7.5 8.5a1 1 0 100-2 1 1 0 000 2z"/></svg>{{ $pct }}% {{ $L('Descuento', 'Off', 'Desconto') }}</span>
                    <span class="fh-card__before">{{ $L('ANTES', 'WAS', 'ANTES') }} <s>US${{ number_format((float) $p['price_before'], 0) }}</s></span>
                </div>
                <p class="fh-card__lbl">{{ $L('Ahora', 'Now', 'Agora') }} · P/P</p>
            @else
                <p class="fh-card__lbl">{{ $L('Desde', 'From', 'A partir de') }} · P/P</p>
            @endif
            <div class="fh-card__row">
                <span class="fh-card__price">US${{ number_format((float) ($p['price'] ?? 0), 0) }}</span>
                {{-- En la ficha el selector de fecha tiene un ancla distinta en móvil y en escritorio: dos enlaces, se muestra uno según el ancho. --}}
                <a href="{{ $p['dates_url'] ?? ($p['url'] ?? '#') }}" class="fh-card__cta fh-card__cta--m" data-ga-cta="ver_fechas" data-ga-location="home_experiencia">{{ $L('Ver fechas', 'See dates', 'Ver datas') }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M9 7h8v8"/></svg></a>
                <a href="{{ $p['dates_url_desktop'] ?? ($p['url'] ?? '#') }}" class="fh-card__cta fh-card__cta--d" data-ga-cta="ver_fechas" data-ga-location="home_experiencia">{{ $L('Ver fechas', 'See dates', 'Ver datas') }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M9 7h8v8"/></svg></a>
            </div>
        </div>
    </div>
</article>
