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

    // Chips: etiquetas cortas como en el diseño ("Recojo", "ES / EN"). Chips curados (type 'custom', cargados en el admin): se pintan tal cual
    // y el marcado accent va en naranja. Sin curados: el chip real "especial" (cancelación / grupo) va en naranja.
    $chips = [];
    $accent = null;
    foreach (($p['chips'] ?? []) as $c) {
        $label = (string) ($c['label'] ?? '');
        if ($label === '') { continue; }
        $type = $c['type'] ?? '';
        if ($type === 'custom') {
            if (! empty($c['accent']) && $accent === null) { $accent = $label; } else { $chips[] = $label; }
            continue;
        }
        if ($type === 'pickup') { $label = $L('Recojo', 'Pickup', 'Pickup'); }
        if ($type === 'language') {
            $label = str_replace(['Español', 'Spanish', 'Espanhol', 'Inglés', 'English', 'Inglês', 'Portugués', 'Portuguese', 'Português'], ['ES', 'ES', 'ES', 'EN', 'EN', 'EN', 'PT', 'PT', 'PT'], $label);
        }
        if (in_array($type, ['cancellation', 'group'], true)) { $accent = $label; continue; }
        $chips[] = ['type' => $type, 'label' => $label];
    }
    // Orden del diseño: duración, recojo, idiomas, resto; el destacado (naranja) siempre al final. Máx. 4 en total (grilla 2x2).
    $rank = ['duration' => 0, 'pickup' => 1, 'language' => 2];
    $curatedChips = array_values(array_filter($chips, fn ($c) => is_string($c)));
    $derived = array_values(array_filter($chips, fn ($c) => is_array($c)));
    usort($derived, fn ($a, $b) => ($rank[$a['type']] ?? 9) <=> ($rank[$b['type']] ?? 9));
    $chips = array_slice(array_merge($curatedChips, array_column($derived, 'label')), 0, $accent !== null ? 3 : 4);

    // Título corto de tarjeta (si no se cargó, card_title = title) y etiqueta de ubicación (si no, región).
    $cardTitle = trim((string) (filled($p['card_title'] ?? null) ? $p['card_title'] : ($p['title'] ?? '')));
    // Si el texto viene TODO EN MAYÚSCULAS (título largo del tour o escrito así en el admin) se normaliza solo en la tarjeta:
    // "TOUR DE DIA COMPLETO AL OASIS" -> "Tour de Dia Completo al Oasis" (conectores en minúscula, nombres propios con inicial).
    $letters = preg_replace('/[^\p{L}]/u', '', $cardTitle);
    if ($letters !== '' && mb_strlen($letters) > 3 && mb_strtoupper($letters) === $letters) {
        $small = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'e', 'o', 'a', 'al', 'en', 'con', 'por', 'para', 'the', 'of', 'and', 'in', 'to', 'at', 'with', 'for', 'do', 'da', 'dos', 'das', 'em', 'no', 'na', 'um', 'uma', 'ao'];
        $cardTitle = preg_replace_callback('/\p{L}[\p{L}\']*/u', function ($m) use ($small) {
            $w = mb_strtolower($m[0]);
            return in_array($w, $small, true) ? $w : mb_strtoupper(mb_substr($w, 0, 1)) . mb_substr($w, 1);
        }, $cardTitle);
        $cardTitle = mb_strtoupper(mb_substr($cardTitle, 0, 1)) . mb_substr($cardTitle, 1);
    }
    $cardLoc = filled($p['location'] ?? null) ? $p['location'] : ($p['region'] ?? null);
    // Etiqueta de precio por modalidad: now = descuento real; regular = TARIFA REGULAR; private = TARIFA PRIVADA · DESDE · GRUPO.
    $priceKind = $p['price_kind'] ?? ($hasDiscount ? 'now' : 'regular');
    $priceUnit = ($p['price_unit'] ?? 'pp') === 'group' ? $L('Grupo', 'Group', 'Grupo') : 'P/P';

    $imgSrc = $img['src'] ?? ($p['image'] ?? asset('assets/banners/banner-hero.jpg'));
    $imgFallback = parse_url(asset('assets/banners/banner-hero.jpg'), PHP_URL_PATH);
@endphp

<article class="fh-card"{!! filled($p['sheet_url'] ?? null) ? ' data-sheet-url="' . e($p['sheet_url']) . '"' : '' !!} data-ga-select data-ga-item-id="{{ $p['id'] ?? '' }}" data-ga-item-name="{{ $p['title'] ?? '' }}" data-ga-price="{{ (float) ($p['price'] ?? 0) }}">
    <div class="fh-card__media">
        <a href="{{ $p['url'] ?? '#' }}" tabindex="-1" aria-hidden="true" class="fh-card__img-link">
            <img src="{{ $imgSrc }}" @if (! empty($img['srcset'])) srcset="{{ $img['srcset'] }}" sizes="{{ $img['sizes'] ?? '' }}" @endif
                 alt="{{ $p['title'] ?? '' }}" class="fh-card__img" width="640" height="533" loading="lazy" decoding="async"
                 onerror="this.onerror=null;this.src='{{ $imgFallback }}'">
        </a>
        @if (filled($p['badge'] ?? null))
            <span class="fh-card__badge"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.8 6.2L20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8L12 2z"/></svg>{{ $p['badge'] }}</span>
        @endif
        @if (filled($cardLoc))
            <span class="fh-card__region">{{ $cardLoc }}</span>
        @endif
    </div>

    <div class="fh-card__body">
        <h3 class="fh-card__title"><a href="{{ $p['url'] ?? '#' }}" class="fh-card__title-link" title="{{ $p['title'] ?? '' }}">{{ $cardTitle }}</a></h3>

        @if ($stars)
            <p class="fh-card__rate">
                <span class="fh-stars" role="img" aria-label="{{ number_format((float) $p['rating']['rating'], 1) }} / 5">@for ($i = 1; $i <= 5; $i++)<svg viewBox="0 0 24 24" aria-hidden="true" class="{{ $i <= $stars ? 'on' : 'off' }}"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6L2.5 9.4l6.6-.8L12 2.5z" fill="currentColor"/></svg>@endfor</span>
                <a href="#opiniones" class="fh-card__opin">{{ $L('Ver opiniones', 'See reviews', 'Ver opiniões') }} <span aria-hidden="true">›</span></a>
            </p>
        @endif

        @if (count($chips) || $accent)
            <ul class="fh-card__chips">
                {{-- Grilla 2x2 como el diseño: máx. 4 chips, el destacado (is-accent) en naranja al final --}}
                @foreach ($chips as $c)<li>{{ $c }}</li>@endforeach
                @if ($accent)<li class="is-accent">{{ $accent }}</li>@endif
            </ul>
        @endif

        <div class="fh-card__buy">
            @if ($priceKind === 'regular')
                <p class="fh-card__kind">{{ $L('Tarifa regular', 'Regular rate', 'Tarifa regular') }}</p>
            @elseif ($priceKind === 'private')
                <p class="fh-card__kind">{{ $L('Tarifa privada', 'Private rate', 'Tarifa privada') }}</p>
            @endif
            @if ($hasDiscount)
                <div class="fh-card__was">
                    <span class="fh-card__off"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 12.6V4.5A1.5 1.5 0 014.5 3h8.1a1.5 1.5 0 011.06.44l7.9 7.9a1.5 1.5 0 010 2.12l-7.2 7.2a1.5 1.5 0 01-2.12 0l-7.9-7.9A1.5 1.5 0 013 12.6zM7.5 8.5a1 1 0 100-2 1 1 0 000 2z"/></svg>{{ $pct }}% {{ $L('Descuento', 'Off', 'Desconto') }}</span>
                    <span class="fh-card__before">{{ $L('ANTES', 'WAS', 'ANTES') }} <s>US${{ number_format((float) $p['price_before'], 0) }}</s></span>
                </div>
            @endif
            <div class="fh-card__row">
                <div class="fh-card__amt">
                    <p class="fh-card__lbl">{{ $hasDiscount ? $L('Ahora', 'Now', 'Agora') : $L('Desde', 'From', 'A partir de') }} · {{ $priceUnit }}</p>
                    <span class="fh-card__price">US${{ number_format((float) ($p['price'] ?? 0), 0) }}</span>
                </div>
                {{-- En la ficha el selector de fecha tiene un ancla distinta en móvil y en escritorio: dos enlaces, se muestra uno según el ancho. --}}
                <a href="{{ $p['dates_url'] ?? ($p['url'] ?? '#') }}" class="fh-card__cta fh-card__cta--m" data-ga-cta="ver_fechas" data-ga-location="home_experiencia">{{ $L('Ver fechas', 'See dates', 'Ver datas') }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M9 7h8v8"/></svg></a>
                <a href="{{ $p['dates_url_desktop'] ?? ($p['url'] ?? '#') }}" class="fh-card__cta fh-card__cta--d" data-ga-cta="ver_fechas" data-ga-location="home_experiencia">{{ $L('Ver fechas', 'See dates', 'Ver datas') }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M9 7h8v8"/></svg></a>
            </div>
        </div>
    </div>
</article>
