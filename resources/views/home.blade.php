@extends('layouts.app')

{{-- Editables desde el admin: Configuración → SEO → Metas por página → Home.
     Si el campo está vacío cae al texto por defecto de lang/{idioma}/seo.php. --}}
@section('title', \App\Support\PageSeo::title('home', __('seo.home_title')))
@section('description', \App\Support\PageSeo::description('home', __('seo.home_description')))
@section('header_variant', 'solid')
@section('body_class', 'lvt-page-home')

{{-- Lote B (2026-08-31): se apagó el WebSite automático que se pintaba aquí
     siempre (duplicaba el global de <x-jsonld />, hoy también apagado). Si
     el equipo SEO quiere un WebSite para la home, lo carga en el campo de
     abajo (Datos estructurados → Home). --}}

@php
    // Configuración → SEO → Metas por página → Home → Datos estructurados.
    // Vacío = no imprime nada extra (el WebSite automático de arriba sigue).
    $homeCustomSchema = \App\Support\PageSeo::schemaJsonLd('home');
@endphp
@if ($homeCustomSchema)
@push('schema')
<x-schema-raw :json="$homeCustomSchema" />
@endpush
@endif

@php
    $locale = app()->getLocale();
    // Helper de traducción inline ES / EN / PT (chrome del home)
    $L = fn (string $es, string $en, string $pt): string => $locale === 'pt' ? $pt : ($locale === 'en' ? $en : $es);

    // Repeaters editables del home (Ajustes). Settings::save los guarda como
    // JSON en un setting tipo 'string', así que Setting::get() devuelve texto:
    // antes la vista exigía is_array() y SIEMPRE caía a los defaults (lo
    // editado en el admin nunca salía). Aquí se decodifica y cada elemento se
    // mezcla con el default de su misma posición: los textos guardados mandan;
    // lo que el admin no guarda (iconPath, img, etc.) sale del default. Los
    // vacíos no pisan nada, así un idioma sin traducir cae a _es en la vista.
    // $forceKeys: claves que siempre vienen del default por posición (p.ej. el
    // id de los tabs, que Alpine usa como pestaña inicial).
    $homeRepeater = static function ($raw, array $defaults, array $forceKeys = []): array {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (! is_array($raw) || count($raw) === 0) {
            return $defaults;
        }
        $out = [];
        foreach (array_values($raw) as $i => $item) {
            // Solo escalares: en prod hay 'img' guardados como estado crudo de
            // FilePond ({"uuid":[]} o []) que reventaban ImagePath::homeImage().
            $item = array_filter(is_array($item) ? $item : [], static fn ($v) => is_scalar($v) && $v !== '');
            $def  = $defaults[$i] ?? [];
            // Del default solo se heredan claves NO traducibles (íconos, imágenes,
            // ids); los textos de un default no deben mezclarse con textos propios.
            // Un elemento extra (más que los defaults) hereda lo visual del último.
            $visualFrom = $def ?: (count($defaults) ? $defaults[count($defaults) - 1] : []);
            $inherit = array_filter($visualFrom, static fn ($k) => ! preg_match('/_(es|en|pt)$/', (string) $k) && ! in_array($k, $forceKeys, true), ARRAY_FILTER_USE_KEY);
            $merged = array_merge($inherit, $item);
            foreach ($forceKeys as $fk) {
                if (array_key_exists($fk, $def)) {
                    $merged[$fk] = $def[$fk];
                } elseif (! isset($merged[$fk])) {
                    $merged[$fk] = $fk . '-' . $i;
                }
            }
            $out[] = $merged;
        }

        return $out;
    };

    $featuredTours = $featuredTours ?? collect();
    $toursIca      = $toursIca      ?? collect();
    $toursLima     = $toursLima     ?? collect();
    $toursCusco    = $toursCusco    ?? collect();
    $st            = $siteSettings  ?? [];

    // Sin fallback ficticio: si una región no tiene tours publicados, su
    // carrusel simplemente no se pinta (antes caía en 4 tours inventados con
    // rating/reviews_count fijos, reutilizados en las 4 secciones).

    $normalizeTours = static function (\Illuminate\Support\Collection $collection): array {
        return $collection->map(static function ($t): array {
            // Única fuente de verdad: reseñas publicadas reales del tour.
            $stats = is_object($t) && method_exists($t, 'reviewStats') ? $t->reviewStats() : ['average' => null, 'total' => 0];
            return [
                'title'     => is_object($t) ? $t->title                                         : $t['title'],
                'before'    => is_object($t) ? ($t->price_before ?? null)                        : ($t['price_before'] ?? null),
                'now'       => is_object($t) ? $t->price                                         : $t['price'],
                'badge'     => is_object($t) ? ($t->badge_text ?? null)                          : ($t['badge_text'] ?? null),
                'badgeType' => is_object($t) ? ($t->badge_type ?? 'warn')                        : ($t['badge_type'] ?? 'warn'),
                'rating'    => $stats['average'] !== null ? number_format($stats['average'], 1) : null,
                'reviews'   => $stats['total'],
                'img'       => is_object($t)
                    ? (\App\Support\ImagePath::url($t->cover_image ?? null) ?? asset('assets/banners/banner-hero.jpg'))
                    : (\App\Support\ImagePath::url($t['cover_image'] ?? null) ?? asset('assets/banners/banner-hero.jpg')),
                'slug'      => is_object($t) ? ($t instanceof \App\Models\Tour ? $t->slugFor(app()->getLocale()) : ($t->slug ?? '')) : ($t['slug'] ?? ''),
                'showOffer'      => (bool) (is_object($t) ? ($t->show_offer_badge ?? true)       : ($t['show_offer_badge'] ?? true)),
                // Datos reales para la card de escritorio (nada de valores por defecto inventados).
                'imgPath'   => is_object($t) ? ($t->cover_image ?: null) : null,
                'imgThumb'  => is_object($t) ? (\App\Support\TourThumbnail::url($t->cover_image ?? null) ?? null) : null,
                'duration'  => \App\Support\TourListing::durationLabel(data_get($t, 'duration')),
                'language'  => (is_object($t) && method_exists($t, 'languagesLabel'))
                    ? $t->languagesLabel()
                    : \App\Support\TourListing::languageLabel(data_get($t, 'language')),
                'pickup'    => (bool) data_get($t, 'pickup_included', true),
                'freeCancellation' => (bool) data_get($t, 'free_cancellation', true),
                'topRated'  => (bool) data_get($t, 'show_top_rated', false),
                'bestSeller' => (bool) data_get($t, 'show_most_sold', false),
                'eyebrow'   => ($t instanceof \App\Models\Tour) ? $t->region?->name : null,
                'discountPct' => (($t instanceof \App\Models\Tour) ? $t->discount_percent : null),
                'category'  => (($t instanceof \App\Models\Tour) ? $t->category?->name : null),
            ];
        })->all();
    };

    $toursCarousels = [
        ['eyebrow' => 'ENJOY WORLD-CLASS STAY EXPERIENCE', 'title' => 'Nuestros Tours más Comprados', 'bg' => 'bg-cream-100', 'items' => $normalizeTours($featuredTours)],
        ['eyebrow' => 'ENJOY WORLD-CLASS STAY EXPERIENCE', 'title' => 'Nuestros Destinos en Ica',     'bg' => 'bg-cream-100', 'items' => $normalizeTours($toursIca)],
        ['eyebrow' => 'ENJOY WORLD-CLASS STAY EXPERIENCE', 'title' => 'Nuestros Destinos en Lima',    'bg' => 'bg-cream-100', 'items' => $normalizeTours($toursLima)],
        ['eyebrow' => 'ENJOY WORLD-CLASS STAY EXPERIENCE', 'title' => 'Nuestros Destinos en Cusco',   'bg' => 'bg-cream-100', 'items' => $normalizeTours($toursCusco)],
    ];

    // First featured tour for "Más Comprados" section
    $firstTour = $featuredTours->first();
    $btSlug    = is_object($firstTour) ? (($firstTour instanceof \App\Models\Tour ? $firstTour->slugFor(app()->getLocale()) : $firstTour->slug) ?? 'huacachina-paracas-full-day') : ($firstTour['slug'] ?? 'huacachina-paracas-full-day');
    $btImg     = ($firstTour && ! empty(is_object($firstTour) ? $firstTour->cover_image : $firstTour['cover_image'] ?? null))
        ? (\App\Support\ImagePath::url(is_object($firstTour) ? $firstTour->cover_image : $firstTour['cover_image']) ?: asset('assets/banners/Rectangle 19210.jpg'))
        : asset('assets/banners/Rectangle 19210.jpg');
    $btUrl     = $btSlug ? route('tours.show', ['locale' => $locale, 'slug' => $btSlug]) : '#';

    // Destino (lima|ica|cusco) de un texto libre; las 3 páginas existen en tours.category.
    $destFromText = static function (string ...$texts): ?string {
        $t = mb_strtolower(implode(' ', $texts));
        $map = [
            'cusco' => '/(cusco|machu|inca|valle sagrado|humantay)/u',
            'ica'   => '/(\bica\b|huacachina|nazca|paracas|ballestas)/u',
            'lima'  => '/(\blima\b|pachacamac|catacumbas|miraflores|barranco)/u',
        ];
        foreach ($map as $dest => $re) {
            if (preg_match($re, $t)) {
                return $dest;
            }
        }

        return null;
    };
    $destUrl = fn (?string $dest): string => $dest
        ? route('tours.category', ['locale' => $locale, 'categoria' => $dest])
        : route('tours.index', ['locale' => $locale]);

    // Imagen subida al disco "media" con variantes WebP: src + srcset/sizes si existen; ['src' => null] si no (se usa el fallback de siempre).
    $mediaVariants = static function ($path, string $sizes): array {
        $path = is_string($path) && $path !== '' ? $path : null;
        $set  = $path ? \App\Support\ImageVariants::srcset($path, null, 'media') : null;
        if (! $set) {
            return ['src' => null, 'attrs' => ''];
        }
        if (! str_contains($set, ' 1440w')) {
            $set .= ', ' . \App\Support\ImageVariants::url($path, 'media') . ' 1441w';
        }

        return [
            'src'   => \App\Support\ImageVariants::responsive($path, 480, 'media'),
            'attrs' => ' srcset="' . e($set) . '" sizes="' . e($sizes) . '"',
        ];
    };

    // Separador decorativo reutilizable
    $sep = '<div class="lvt-sep flex items-center justify-center gap-2 mt-4 mb-0" aria-hidden="true">
        <span class="h-px w-8 bg-orange-500/50"></span>
        <svg class="w-3 h-3 text-orange-500" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l1.5 8.5L22 12l-8.5 1.5L12 22l-1.5-8.5L2 12l8.5-1.5L12 2z"/></svg>
        <span class="h-px w-8 bg-orange-500/50"></span>
    </div>';

    // Brújula SVG naranja outline (para secciones con fondo claro)
    $brujula = '<svg class="lvt-brujula w-11 h-11 text-orange-500 mx-auto mb-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5.5"/><path d="M15.5 8.5l-2 5-5 2 2-5 5-2z" fill="currentColor" opacity=".9"/></svg>';
@endphp

@section('content')

{{--
    SLIDER FIX — 2026-06-29
    Las clases shrink-0, scrollbar-hide y lg:grid fueron purgadas del CSS compilado de producción
    porque el markup es más nuevo que el último npm run build (disco/SSL bloqueados).
    Solución: CSS literal en <style> + estilos inline en los tracks. No se purga nunca.
--}}
<style>
/* Track base: siempre flex, nunca grid */
.carousel-track {
    display: flex;
    flex-wrap: nowrap;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;   /* Firefox */
    -ms-overflow-style: none; /* IE/Edge */
}
.carousel-track::-webkit-scrollbar { display: none; }

/* ── Alineación común del home (18-nov) ──
   Todo el contenido comparte el mismo borde: max-w-7xl + 1.25rem desde lg; 20px en mobile. */
@media (max-width: 1023.98px) {
    .home-wrap { padding-left: 0 !important; padding-right: 0 !important; } /* evita doble padding (section px-5 + .container) */
}
@media (min-width: 1024px) {
    .home-hero__wrap, .home-stats__wrap {
        width: calc(100% - 2.5rem);
        padding-left: 1.25rem !important; padding-right: 1.25rem !important;
    }
}
/* Footer del home alineado al contenido (solo en esta vista) */
.site-footer > section.container, .site-footer > div > .container { padding-left: 1.25rem !important; padding-right: 1.25rem !important; }
@media (min-width: 1024px) {
    .site-footer > section.container, .site-footer > div > .container { width: calc(100% - 2.5rem); max-width: 80rem; }
}
/* Carruseles: la 1.ª card arranca en el borde del contenido (el padding queda para sombras;
   scroll-padding evita que el snap "start" se coma ese padding y corra la card) */
.carousel-track.ct-bleed { scroll-padding-left: 1rem; scroll-padding-right: 1rem; }
@media (min-width: 640px) {
    .carousel-track.ct-bleed { margin-left: -1rem; margin-right: -1rem; }
}
/* Pocos slides: centrados en vez de pegados a la izquierda */
.carousel-track.ct-one { justify-content: center; }
@media (min-width: 1024px) {
    .carousel-track.ct-few { justify-content: center; }
}

/* Slides: 3 columnas (mc-carousel, lc-carousel) */
.tour-slide {
    flex: 0 0 88%;
    max-width: 88%;
    scroll-snap-align: center;
}
@media (min-width: 640px) {
    .tour-slide {
        flex-basis: 64%;
        max-width: 64%;
    }
}
@media (min-width: 768px) {
    .tour-slide {
        flex-basis: 48%;
        max-width: 48%;
    }
}
@media (min-width: 1024px) {
    .tour-slide {
        flex-basis: calc((100% - 2rem) / 3);
        max-width: calc((100% - 2rem) / 3);
        scroll-snap-align: start;
    }
}

/* Slides: 4 columnas (exp-carousel, reviews-carousel) */
.tour-slide-4 {
    flex: 0 0 88%;
    max-width: 88%;
    scroll-snap-align: start;
}
@media (min-width: 640px) {
    .tour-slide-4 {
        flex-basis: 60%;
        max-width: 60%;
    }
}
@media (min-width: 768px) {
    .tour-slide-4 {
        flex-basis: 45%;
        max-width: 45%;
    }
}
@media (min-width: 1024px) {
    .tour-slide-4 {
        flex-basis: calc((100% - 3rem) / 4);
        max-width: calc((100% - 3rem) / 4);
        scroll-snap-align: start;
    }
}
</style>

{{-- ============================================================
     HOME FINAL (diseño aprobado por el cliente, 20-nov-2026). Móvil = archivo "FINAL HOME"; escritorio = mismo diseño ensanchado.
     Estilos: public/css/redesign.css, bloque "HOME FINAL" (.fh-*). Todo dato es real o se oculta.
     ============================================================ --}}

{{-- ── Franja de confianza superior + HERO ── --}}
@php
    $fhRating = filled($homeStats['rating']['value'] ?? null)
        ? $homeStats['rating']['value']
        : (! empty($homeReviewStats) ? number_format($homeReviewStats['average'], 1) : null);

    // H1 desde Ajustes (home_hero_title_*); default nuevo del diseño. Última línea en cursiva dorada cuando hay 2+ líneas.
    $heroTitleDefault = [
        'es' => "Tu próxima aventura\nempieza aquí.",
        'en' => "Your next adventure\nstarts here.",
        'pt' => "Sua próxima aventura\ncomeça aqui.",
    ];
    $heroStored = trim((string) \App\Models\Setting::get('home_hero_title_' . $locale));
    $heroTitleRaw = $heroStored !== '' ? $heroStored : ($heroTitleDefault[$locale] ?? $heroTitleDefault['es']);
    $heroLines = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $heroTitleRaw)), fn ($l) => $l !== ''));
    $heroLast  = count($heroLines) > 1 ? array_pop($heroLines) : null;

    // Imagen del hero: la del admin si existe; si no, la ilustración del diseño aprobado.
    $heroImgSetting = \App\Models\Setting::get('home_hero_image');
    if (is_array($heroImgSetting)) { $heroImgSetting = $heroImgSetting[0] ?? ''; }
    $heroImgSetting = trim((string) $heroImgSetting);
    $heroCustom = ($heroImgSetting !== '' && $heroImgSetting !== '[]' && $heroImgSetting !== '""');
    $heroImgUrl = $heroCustom ? \Illuminate\Support\Facades\Storage::disk('media')->url($heroImgSetting) : asset('assets/home/hero-catedral.webp');
    $heroCustomSet = null;
    if ($heroCustom) {
        try { $heroCustomSet = \App\Support\ImageVariants::srcset($heroImgSetting, null, 'media'); } catch (\Throwable $e) { $heroCustomSet = null; }
    }
    $heroSizes = '(min-width:1024px) 520px, 60vw';

    // Chips rápidos: $homeChips (contrato FINAL HOME §2): cada chip filtra /tours con un solo parámetro. Sin chips = no se pinta la fila.
    $fhQuick = [];
    foreach (($homeChips ?? []) as $c) {
        if (! is_array($c) || ! filled($c['label'] ?? null) || ! filled($c['url'] ?? null)) { continue; }
        $fhQuick[] = ['label' => trim(preg_replace('/\s*\(.*?\)/u', '', (string) $c['label'])), 'url' => $c['url'], 'type' => $c['type'] ?? 'categoria'];
    }
    $fhQuick = array_slice($fhQuick, 0, 5);
    $fhQuickIcon = static function (array $c, int $i): string {
        $l = mb_strtolower($c['label']);
        if (($c['type'] ?? '') === 'duracion') { return preg_match('/(complet|full|inteiro|d[ií]a)/u', $l) && ! preg_match('/(medio|half|meio)/u', $l) ? 'sun' : 'clock'; }
        if (($c['type'] ?? '') === 'destino') { return 'compass'; }
        if (preg_match('/(aventur|advent)/u', $l)) { return 'mountain'; }
        if (preg_match('/(arque|archaeo)/u', $l)) { return 'pyramid'; }
        if (preg_match('/(histor|cultur)/u', $l)) { return 'pillars'; }
        if (preg_match('/(natur|gastro|food|comid|culinar)/u', $l)) { return 'leaf'; }
        return 'compass';
    };
    $fhIcons = [
        'clock'    => '<circle cx="12" cy="12" r="8.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5V12l3 2"/>',
        'sun'      => '<circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2.5v2.5M12 19v2.5M2.5 12H5M19 12h2.5M5.3 5.3l1.8 1.8M16.9 16.9l1.8 1.8M5.3 18.7l1.8-1.8M16.9 7.1l1.8-1.8"/>',
        'pillars'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.5 9L12 4l8.5 5M5 9.5v8M9.5 9.5v8M14.5 9.5v8M19 9.5v8M3.5 20h17M3.5 17.5h17"/>',
        'mountain' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.5 19L9 7l4 7 2.5-4 6 9z"/>',
        'pyramid'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3.5L21 19.5H3z"/><path stroke-linecap="round" d="M9.2 15h5.6"/>',
        'leaf'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 19c0-8 5-13 14-14 0 9-5 14-13 14M5 19c3-4 6-6 9-8"/>',
        'compass'  => '<circle cx="12" cy="12" r="8.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M15.5 8.5l-2 5-5 2 2-5 5-2z"/>',
    ];
@endphp

<div class="fh-top">
    <ul class="fh-top__in">
        @if ($fhRating)<li><b>{{ $fhRating }}</b> {{ $L('en nuestra web', 'on our website', 'em nosso site') }}</li>@endif
        <li>{{ $L('Guías locales', 'Local guides', 'Guias locais') }}</li>
        @if (! empty($homeApi['whatsapp_url']))
            <li><button type="button" class="fh-top__btn" data-sheet="contact">{{ $L('Atención por WhatsApp', 'WhatsApp support', 'Atendimento por WhatsApp') }}</button></li>
        @else
            <li>{{ $L('Atención por WhatsApp', 'WhatsApp support', 'Atendimento por WhatsApp') }}</li>
        @endif
    </ul>
</div>

@push('head')
    <link rel="preload" as="image" href="{{ $heroImgUrl }}" @if ($heroCustomSet) imagesrcset="{{ $heroCustomSet }}" imagesizes="{{ $heroSizes }}" @endif fetchpriority="high">
@endpush

<section class="fh-hero" aria-labelledby="hero-title">
    <div class="fh-hero__bg" aria-hidden="true">
        <img src="{{ $heroImgUrl }}" @if ($heroCustomSet) srcset="{{ $heroCustomSet }}" sizes="{{ $heroSizes }}" @endif alt=""
             width="436" height="604" loading="eager" fetchpriority="high" decoding="async">
    </div>
    <div class="fh-hero__in">
        <div class="fh-hero__copy">
            <p class="fh-hero__eyebrow">{{ \App\Models\Setting::get('home_hero_eyebrow_' . $locale) ?: $L('Experiencias reales · Lima y Perú', 'Real experiences · Lima & Peru', 'Experiências reais · Lima e Peru') }}</p>
            <h1 id="hero-title" class="fh-hero__title">{!! nl2br(e(implode("\n", $heroLines))) !!}@if ($heroLast)<br><em>{{ $heroLast }}</em>@endif</h1>
            <p class="fh-hero__lead">{{ \App\Models\Setting::get('home_hero_subtitle_' . $locale) ?: $L('Tours increíbles, información clara y reservas sin complicaciones.', 'Amazing tours, clear information and hassle-free bookings.', 'Tours incríveis, informação clara e reservas sem complicações.') }}</p>
        </div>

        @if (! empty($homeSearch))
        <div class="fh-hero__find">
            <form class="fh-search" role="search" method="GET" action="{{ $homeSearch['action'] }}" data-search-form>
                <svg class="fh-search__ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path stroke-linecap="round" d="M15.5 15.5L21 21"/></svg>
                <label for="fh-q" class="sr-only">{{ $L('¿Qué te gustaría descubrir en Perú?', 'What would you like to discover in Peru?', 'O que você gostaria de descobrir no Peru?') }}</label>
                <input id="fh-q" type="search" name="{{ $homeSearch['params']['query'] ?? 'q' }}" autocomplete="off" maxlength="{{ $homeSearch['query_max'] ?? 80 }}"
                       placeholder="{{ $L('¿Qué te gustaría descubrir en Perú?', 'What would you like to discover in Peru?', 'O que você gostaria de descobrir no Peru?') }}">
                <button type="submit" class="fh-search__btn" data-ga-cta="buscar_texto" data-ga-location="home_hero">{{ $L('Buscar', 'Search', 'Buscar') }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
            </form>

            @if (count($fhQuick))
                <p class="fh-quick__lead"><span>{{ $L('Toca una opción y descubre tours recomendados', 'Tap an option and discover recommended tours', 'Toque em uma opção e descubra tours recomendados') }}</span></p>
                <ul class="fh-quick">
                    @foreach ($fhQuick as $qi => $qc)
                        <li><a href="{{ $qc['url'] }}" class="fh-chip fh-chip--{{ $qi }}" data-find data-find-title="{{ $qc['label'] }}" data-ga-cta="chip_rapido" data-ga-location="home_hero">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">{!! $fhIcons[$fhQuickIcon($qc, $qi)] !!}</svg>{{ $qc['label'] }}</a></li>
                    @endforeach
                </ul>
            @endif
        </div>
        @endif
    </div>
</section>

{{-- ── ELIGE TU EXPERIENCIA (tours reales; H2 SEO id bestseller-title: fusiona el carrusel "más vendidos") ── --}}
@php
    // "Elige tu experiencia": $homePick (contrato FINAL HOME §3): tours destacados reales; 1..4. Sin tours = sección oculta.
    $fhPick = array_slice(array_values(array_filter((array) ($homePick ?? []), 'is_array')), 0, 4);
    $fhPickImgs = [];
    if (count($fhPick)) {
        try {
            $fhPickRows = \App\Models\Tour::whereIn('id', array_column($fhPick, 'id'))->get(['id', 'cover_image'])->keyBy('id');
            foreach ($fhPick as $pk) {
                $cv = $fhPickRows[$pk['id']]->cover_image ?? null;
                $cd = \App\Support\ImageVariants::card($cv ?: null, '(min-width:1024px) 280px, 190px');
                $fhPickImgs[$pk['id']] = ['src' => $cd['src'] ?: null, 'srcset' => $cd['srcset'], 'sizes' => $cd['sizes']];
            }
        } catch (\Throwable $e) { $fhPickImgs = []; }
    }
@endphp
@if (count($fhPick))
<section class="fh-sec fh-exp" id="elige" aria-labelledby="bestseller-title">
    <div class="fh-wrap">
        <p class="fh-tag"><i aria-hidden="true"></i>{{ \App\Models\Setting::get('home_sec_featured_eyebrow_' . $locale) ?: $L('Tours para enamorarte de Perú', 'Tours to fall in love with Peru', 'Tours para se apaixonar pelo Peru') }}</p>
        <div class="fh-sec__head">
<h2 id="bestseller-title" class="fh-h2">{!! e(trim((string) \App\Models\Setting::get('home_sec_featured_title_' . $locale))) ?: $L('Elige tu <span class="fh-u">experiencia</span>.', 'Choose your <span class="fh-u">experience</span>.', 'Escolha sua <span class="fh-u">experiência</span>.') !!}</h2>
            <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="fh-more" data-sheet="all">{{ $L('Ver todos', 'See all', 'Ver todos') }} <span aria-hidden="true">›</span></a>
        </div>
        <div class="fh-grid">
            @foreach ($fhPick as $fp)
                <x-fh-tour-card :p="$fp" :img="$fhPickImgs[$fp['id']] ?? []" />
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ── ENCUENTRA TU TOUR (buscador de 3 preguntas, $homeSearch; oculto si no hay opciones) ── --}}
@if (! empty($homeSearch))
@php
    $hsP = $homeSearch['params'] ?? ['duration' => 'duracion', 'experience' => 'experiencia', 'destination' => 'destino'];
    $hsQs = [
        ['key' => 'duration',    'list' => $homeSearch['durations'] ?? [],    'q' => $L('¿Cuánto tiempo tienes?', 'How much time do you have?', 'Quanto tempo você tem?')],
        ['key' => 'experience',  'list' => $homeSearch['experiences'] ?? [],  'q' => $L('¿Qué te gustaría vivir?', 'What would you like to experience?', 'O que você gostaria de viver?')],
        ['key' => 'destination', 'list' => $homeSearch['destinations'] ?? [], 'q' => $L('¿En qué destino?', 'Which destination?', 'Em qual destino?')],
    ];
    $hsQs = array_values(array_filter($hsQs, fn ($q) => count($q['list'])));
@endphp
<section class="fh-sec fh-finder-sec" id="encuentra-tu-tour" aria-labelledby="lvt-finder-title">
    <div class="fh-wrap">
        <div class="fh-finder">
            <div class="fh-finder__intro">
                <p class="fh-finder__eyebrow">{{ $L('Tu asesor automático', 'Your automatic advisor', 'Seu assessor automático') }}</p>
                <h2 id="lvt-finder-title" class="fh-finder__title">{{ $L('Encuentra tu tour', 'Find your tour', 'Encontre seu tour') }}<span>{{ $L('con tres preguntas.', 'with three questions.', 'com três perguntas.') }}</span></h2>
                <p class="fh-finder__sub">{{ $L('Haz clic en lo que te interesa y descubre automáticamente tu tour ideal.', 'Click on what interests you and automatically find your ideal tour.', 'Clique no que lhe interessa e descubra automaticamente seu tour ideal.') }}</p>
            </div>
            <form class="fh-finder__form" id="lvt-finder" data-finder-form method="GET" action="{{ $homeSearch['action'] }}" novalidate aria-labelledby="lvt-finder-title">
                <div class="fh-finder__panel">
                    @foreach ($hsQs as $qi => $q)
                        <fieldset class="fh-q">
                            <legend><span class="fh-q__n" aria-hidden="true">{{ str_pad((string) ($qi + 1), 2, '0', STR_PAD_LEFT) }}</span>{{ $q['q'] }}</legend>
                            <div class="fh-opts">
                                @foreach ($q['list'] as $opt)
                                    <label class="fh-opt">
                                        <input type="radio" name="{{ $hsP[$q['key']] }}" value="{{ $opt['value'] }}">
                                        <span>{{ $opt['label'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
                <p class="fh-finder__err" id="lvt-finder-err" role="alert" hidden>{{ $L('Elige al menos una opción para buscar.', 'Choose at least one option to search.', 'Escolha pelo menos uma opção para buscar.') }}</p>
                <button type="submit" class="fh-finder__cta" data-ga-cta="finder_submit" data-ga-location="home_finder">
                    {{ $L('Ver mi tour recomendado', 'See my recommended tour', 'Ver meu tour recomendado') }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
            </form>
        </div>
    </div>
</section>
@endif

{{-- ── LA EXPERIENCIA ESTRELLA ($homeStar; oculto si es null). H2 id star-title ── --}}
@if (! empty($homeStar))
@php
    $fhSt = $homeStar;
    $fhStTone = ['teal', 'peach', 'sage', 'sand'];
    $fhStSheet = null;
    try { $fhStSheet = route('api.tours.sheet', ['locale' => $locale, 'slug' => $fhSt['tour']->slugFor($locale)]); } catch (\Throwable $e) { $fhStSheet = null; }
    $fhStItems = (array) ($fhSt['milestone_items'] ?? []);
    if (! count($fhStItems)) { $fhStItems = array_map(fn ($m) => ['title' => $m, 'detail' => null], (array) ($fhSt['milestones'] ?? [])); }
@endphp
<section class="fh-sec fh-star-sec" id="estrella" aria-labelledby="star-title">
    <div class="fh-wrap">
        <p class="fh-over">{{ $L('La experiencia estrella', 'The star experience', 'A experiência estrela') }}</p>
        <h2 id="star-title" class="fh-star__h">{{ $fhSt['title'] }}</h2>
        <div class="fh-star">
            <a href="{{ $fhSt['url'] }}" class="fh-star__media"{!! $fhStSheet ? ' data-sheet-url="' . e($fhStSheet) . '"' : '' !!} data-ga-select data-ga-item-id="{{ $fhSt['tour']->getKey() }}" data-ga-item-name="{{ $fhSt['title'] }}" data-ga-price="{{ $fhSt['price'] }}" tabindex="-1" aria-hidden="true">
                <img src="{{ $fhSt['image'] }}" alt="" loading="lazy" decoding="async" width="1280" height="900">
                @if (filled($fhSt['badge'] ?? null))<span class="fh-star__badge">{{ $fhSt['badge'] }}</span>@endif
                @if (filled($fhSt['headline'] ?? null) || filled($fhSt['subtitle'] ?? null) || count($fhSt['chips'] ?? []))
                    <span class="fh-star__over">
                        @if (filled($fhSt['headline'] ?? null))<span class="fh-star__lead">{{ $fhSt['headline'] }}</span>@endif
                        @if (filled($fhSt['subtitle'] ?? null))<span class="fh-star__sub">{{ $fhSt['subtitle'] }}</span>@endif
                        @if (count($fhSt['chips'] ?? []))<span class="fh-star__chips">@foreach ($fhSt['chips'] as $sc)<span>{{ $sc }}</span>@endforeach</span>@endif
                    </span>
                @endif
            </a>
            <div class="fh-star__card">
                @if (filled($fhSt['question'] ?? null))<h3 class="fh-star__q">{{ $fhSt['question'] }}</h3>@endif
                @if (filled($fhSt['lead'] ?? null))<p class="fh-star__p">{{ $fhSt['lead'] }}</p>@endif
                @if (count($fhStItems))
                    <ul class="fh-hitos">
                        @foreach ($fhStItems as $mi => $ms)
                            <li class="fh-hito fh-hito--{{ $fhStTone[$mi % 4] }}"><b>{{ str_pad((string) ($mi + 1), 2, '0', STR_PAD_LEFT) }} · {{ $ms['title'] }}</b>@if (filled($ms['detail'] ?? null))<span>{{ $ms['detail'] }}</span>@endif</li>
                        @endforeach
                    </ul>
                @endif
                <div class="fh-star__buy">
                    <div class="fh-star__price">
                        <span class="fh-star__from">{{ __('ui.from') }} / {{ __('ui.per_person') }}</span>
                        <b>US${{ number_format($fhSt['price'], 0) }}</b>
                        @if ($fhSt['price_before'])<s>US${{ number_format($fhSt['price_before'], 0) }}</s>@endif
                    </div>
                    <a href="{{ $fhSt['url'] }}" class="fh-star__cta"{!! $fhStSheet ? ' data-sheet-url="' . e($fhStSheet) . '"' : '' !!} data-ga-cta="estrella_reservar" data-ga-location="home_estrella">{{ $L('Ver fechas y reservar', 'See dates & book', 'Ver datas e reservar') }}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
                </div>
                <p class="fh-star__note">{{ $L('Recorridos y entradas sujetos a la opción reservada.', 'Routes and tickets depend on the option booked.', 'Roteiros e ingressos sujeitos à opção reservada.') }}</p>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ── EXPLORA PERÚ POR DESTINOS (H2 SEO id destinos-title). Datos: $homeDestinations (contrato FINAL HOME §4): Regiones activas con tours ── --}}
@php
    $fhDest = array_values(array_filter((array) ($homeDestinations ?? []), 'is_array'));
    // La "destacada" (featured) va primero y es la grande.
    usort($fhDest, fn ($a, $b) => (int) ! empty($b['featured']) <=> (int) ! empty($a['featured']));
    $fhDest = array_slice($fhDest, 0, 3);
@endphp
@if (count($fhDest))
<section class="fh-sec fh-dest-sec" id="destinos" aria-labelledby="destinos-title">
    <div class="fh-wrap">
        <div class="fh-dest__head">
            <h2 id="destinos-title" class="fh-h2 fh-h2--c">{!! \App\Models\Setting::get('home_sec_destinos_title_' . $locale) ?: $L('Explora Perú por destinos', 'Explore Peru by destination', 'Explore o Peru por destinos') !!}</h2>
            <p class="fh-dest__sub">{{ \App\Models\Setting::get('home_destinos_sub_' . $locale) ?: $L('Descubre experiencias inolvidables en Lima, Ica y Cusco.', 'Discover unforgettable experiences in Lima, Ica and Cusco.', 'Descubra experiências inesquecíveis em Lima, Ica e Cusco.') }}</p>
        </div>
        <div class="fh-dest">
            @foreach ($fhDest as $di => $d)
                <article class="fh-dcard{{ $di === 0 ? ' fh-dcard--big' : '' }}{{ empty($d['image']) ? ' fh-dcard--noimg' : '' }}" data-dest="{{ $d['slug'] ?? '' }}" data-dest-name="{{ $d['name'] }}">
                    @if (! empty($d['image']))
                        <img src="{{ $d['image'] }}" alt="" class="fh-dcard__img" loading="lazy" decoding="async" width="{{ $di === 0 ? 1280 : 640 }}" height="{{ $di === 0 ? 700 : 640 }}">
                    @endif
                    <div class="fh-dcard__body">
                        @if (filled($d['eyebrow'] ?? null))<span class="fh-dcard__badge">{{ $d['eyebrow'] }}</span>@endif
                        <h3 class="fh-dcard__title"><a href="{{ $d['url'] }}" class="fh-dcard__link" data-find>{{ $d['name'] }}</a></h3>
                        @if (filled($d['description'] ?? null))<p class="fh-dcard__desc">{{ $d['description'] }}</p>@endif
                        @if (filled($d['places'] ?? null))<p class="fh-dcard__places">{{ $d['places'] }}</p>@endif
                        @if ($di === 0)
                            <span class="fh-dcard__cta fh-dcard__cta--gold">{{ $L('Explorar', 'Explore', 'Explorar') }} {{ $d['name'] }}
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M9 7h8v8"/></svg></span>
                        @else
                            <span class="fh-dcard__cta"><u>{{ $L('Ver tours', 'See tours', 'Ver tours') }}</u>
                                <i aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M9 7h8v8"/></svg></i></span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ── VIAJA CON TRANQUILIDAD (banda de confianza; H2 SEO id why-title). Datos: $homeTrust (contrato FINAL HOME §5); solo ítems con dato ── --}}
@php
    $fhTrust = [];
    $fhTrustMeta = ['cancellation' => ['cal', 'sand'], 'years' => ['award', 'sage'], 'travelers' => ['people', 'sage'], 'rating' => ['star', 'sand'], 'pay' => ['wallet', 'sage']];
    foreach ((array) ($homeTrust['items'] ?? []) as $it) {
        $k = $it['key'] ?? '';
        if (! isset($fhTrustMeta[$k]) || ! filled($it['value'] ?? null)) { continue; }
        $val = (string) $it['value'];
        if ($k === 'rating' && preg_match('/^\d+([.,]\d+)?$/', $val)) { $val .= ' / 5'; }
        $lbl = (string) ($it['label'] ?? '');
        if ($k === 'rating' && filled($it['source'] ?? null)) { $lbl = trim($lbl . ' · ' . $it['source'], ' ·'); }
        $fhTrust[] = ['icon' => $fhTrustMeta[$k][0], 'tone' => $fhTrustMeta[$k][1], 'v' => $val, 'l' => $lbl, 'k' => $k, 'detail' => filled($it['detail'] ?? null) ? (string) $it['detail'] : null];
    }
    $fhHasCancel = collect($fhTrust)->contains('k', 'cancellation');
    $fhTrustIcons = [
        'cal'    => '<rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path stroke-linecap="round" d="M3.5 9.5h17M8 3v4M16 3v4"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 15l2 2 4-4"/>',
        'award'  => '<circle cx="12" cy="9.5" r="5.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M8.8 14.2L7.5 21l4.5-2.6 4.5 2.6-1.3-6.8"/>',
        'people' => '<circle cx="12" cy="8" r="2.8"/><circle cx="5.8" cy="10" r="2.2"/><circle cx="18.2" cy="10" r="2.2"/><path stroke-linecap="round" d="M7.5 19c0-2.8 2.2-4.8 4.5-4.8s4.5 2 4.5 4.8M2.5 18c0-2 1.5-3.5 3.3-3.5M21.5 18c0-2-1.5-3.5-3.3-3.5"/>',
        'star'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3.5l2.6 5.5 6 .8-4.4 4.2 1.1 6-5.3-2.9-5.3 2.9 1.1-6L3.4 9.8l6-.8L12 3.5z"/>',
        'wallet' => '<rect x="3" y="6" width="18" height="13" rx="2.5"/><path stroke-linecap="round" d="M3 10h18M7 15h3"/>',
    ];
@endphp
@if (count($fhTrust))
<section class="fh-sec fh-trust" aria-labelledby="why-title">
    <div class="fh-wrap">
        {{-- SEO: el H2 why-title conserva el texto SEO cargado en home_sec_why_title_* (18-nov); si está vacío, el título corto de la banda. --}}
        <h2 id="why-title" class="fh-over">{{ trim(strip_tags((string) \App\Models\Setting::get('home_sec_why_title_' . $locale))) ?: (\App\Models\Setting::get('home_trust_title_' . $locale) ?: $L('Viaja con tranquilidad', 'Travel with peace of mind', 'Viaje com tranquilidade')) }}</h2>
        <ul class="fh-trust__grid">
            @foreach ($fhTrust as $tIdx => $ti)
                @php $tInner = '<span class="fh-tcard__ico fh-tcard__ico--' . $ti['tone'] . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">' . $fhTrustIcons[$ti['icon']] . '</svg></span><span class="fh-tcard__txt"><b>' . e($ti['v']) . '</b>' . ($ti['l'] !== '' ? '<small>' . e($ti['l']) . '</small>' : '') . '</span>'; @endphp
                <li class="fh-tcard-li{{ $ti['k'] === 'pay' ? ' fh-tcard-li--pay' : '' }}">
                    @if ($ti['detail'])
                        <button type="button" class="fh-tcard fh-tcard--btn" data-sheet="benefit" data-benefit="{{ $tIdx }}" aria-haspopup="dialog">{!! $tInner !!}</button>
                    @else
                        <div class="fh-tcard">{!! $tInner !!}</div>
                    @endif
                </li>
            @endforeach
        </ul>
        @if ($fhHasCancel)<p class="fh-trust__note">* {{ $L('Cancelación sujeta a las condiciones de cada tour y canal de reserva.', 'Cancellation subject to the conditions of each tour and booking channel.', 'Cancelamento sujeito às condições de cada tour e canal de reserva.') }}</p>@endif
    </div>
</section>
@endif

{{-- ── HISTORIAS CONTADAS POR NUESTROS VIAJEROS (reseñas reales; H2 SEO id opiniones-title) ──
     Tarjetas y filtros: $homeReviews (contrato FINAL HOME §6). Promedio/distribución: $homeReviewStats. Sin reseñas = solo el resumen (si hay). --}}
@php
    $rs = ! empty($homeReviewStats) ? $homeReviewStats : null;
    $fhCards = (array) ($homeReviews['cards'] ?? []);
    $reviewCount = count($fhCards);
    $fhAvg = $rs ? number_format($rs['average'], 1) : $fhRating;
    $fhPlatforms = collect((array) ($homeReviews['platforms'] ?? []))->filter(fn ($p) => in_array($p['key'] ?? '', ['google', 'tripadvisor', 'viator'], true))->values();
    $fhShowPlat = ! empty($homeReviews['show_platform_filter']) && $fhPlatforms->count() > 1;
    $fhShowDest = ! empty($homeReviews['show_destination_filter']);
    $fhShowPhotos = ! empty($homeReviews['show_photos_filter']);
    $fhHasFilters = $reviewCount > 0 && ($fhShowPlat || $fhShowDest || $fhShowPhotos);
    $fhStarsOn = $fhAvg ? max(1, min(5, (int) round((float) $fhAvg))) : 5;
    $googleReviewsUrl = \App\Models\Setting::get('social_google_reviews')
        ?: 'https://www.google.com/maps/place/Lima+view+tours/@-12.0457338,-77.0281479,17z/data=!3m1!4b1!4m6!3m5!1s0x9105c90e82ab0695:0xad0c3b21492d1a96!8m2!3d-12.0457338!4d-77.0281479!16s%2Fg%2F11w7s4b74q!18m1!1e1';
    $fhStarPath = 'M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6L2.5 9.4l6.6-.8L12 2.5z';
@endphp
<section id="opiniones" class="fh-sec fh-rev-sec scroll-mt-24" aria-labelledby="opiniones-title" x-data="{ reviewModalOpen: false, review: {} }">
    <div class="fh-wrap">
        <div class="fh-rev__head">
            <svg class="fh-rev__dunes" viewBox="0 0 120 36" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path stroke-linecap="round" d="M2 30c14-18 28-18 44-4 12-12 26-16 42-6 10-8 20-10 30-6"/></svg>
            <h2 id="opiniones-title" class="fh-h2">{!! \App\Models\Setting::get('home_sec_reviews_title_' . $locale) ?: $L('Historias contadas por <em>nuestros viajeros.</em>', 'Stories told by <em>our travelers.</em>', 'Histórias contadas por <em>nossos viajantes.</em>') !!}</h2>
        </div>

        <div class="fh-rev{{ ($fhAvg || $rs) ? '' : ' fh-rev--solo' }}">
            @if ($fhAvg || $rs)
                <div class="fh-rev__sum">
                    <div class="fh-rev__top">
                        <div class="fh-rev__score">
                            <b>{{ $fhAvg }}</b>
                            <span class="fh-stars fh-stars--lg" role="img" aria-label="{{ $fhAvg }} / 5">@for ($i = 1; $i <= 5; $i++)<svg viewBox="0 0 24 24" aria-hidden="true" class="{{ $i <= $fhStarsOn ? 'on' : 'off' }}"><path d="{{ $fhStarPath }}" fill="currentColor"/></svg>@endfor</span>
                            @if ($rs)<small>{{ $L('Calificación promedio de', 'Average rating from', 'Avaliação média de') }} {{ number_format($rs['total']) }} {{ $rs['total'] === 1 ? $L('reseña', 'review', 'avaliação') : $L('reseñas', 'reviews', 'avaliações') }}</small>@endif
                        </div>
                        <a href="{{ \App\Support\LocalizedPages::url('reviews', $locale) }}" class="fh-rev__all" data-sheet="reviews">{{ $L('Ver reseñas', 'See reviews', 'Ver avaliações') }} <span aria-hidden="true">›</span></a>
                    </div>
                    @if ($rs)
                        <ul class="fh-bars" aria-label="{{ $L('Distribución de valoraciones', 'Rating distribution', 'Distribuição das avaliações') }}">
                            @foreach ($rs['distribution'] as $star => $d)
                                <li><span>{{ $star }} {{ $star === 1 ? $L('estrella', 'star', 'estrela') : $L('estrellas', 'stars', 'estrelas') }}</span><i style="--w: {{ $d['percent'] }}%"></i><span>{{ $d['percent'] }}%</span></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <div class="fh-rev__list">
                @if ($fhHasFilters)
                    <div class="fh-filter" role="group" aria-label="{{ $L('Filtrar reseñas', 'Filter reviews', 'Filtrar avaliações') }}" id="fh-rev-filter">
                        <button type="button" data-f="all" aria-pressed="true" class="fh-f fh-f--all">{{ $L('Todos', 'All', 'Todos') }}</button>
                        @if ($fhShowPlat)
                            @foreach ($fhPlatforms as $si => $pl)
                                <button type="button" data-f="platform" data-v="{{ $pl['key'] }}" aria-pressed="false" class="fh-f fh-f--s{{ $si % 3 }}">{{ $pl['label'] }}</button>
                            @endforeach
                        @endif
                        @if ($fhShowDest)
                            @foreach ((array) ($homeReviews['destinations'] ?? []) as $di => $dd)
                                <button type="button" data-f="dest" data-v="{{ $dd['slug'] }}" aria-pressed="false" class="fh-f fh-f--d{{ $di % 4 }}">{{ $dd['name'] }}</button>
                            @endforeach
                        @endif
                        @if ($fhShowPhotos)
                            <button type="button" data-f="photos" aria-pressed="false" class="fh-f fh-f--p">{{ $L('Con fotos', 'With photos', 'Com fotos') }}</button>
                        @endif
                    </div>
                @endif

                @if ($reviewCount > 0)
                    <div class="fh-revs" id="fh-revs">
                        @foreach ($fhCards as $c)
                            @php
                                $rvName    = $c['name'] ?: $L('Viajero', 'Traveler', 'Viajante');
                                $rvRating  = max(0, min(5, (int) round((float) ($c['rating'] ?? 5))));
                                $rvQuote   = trim((string) ($c['quote'] ?? ''));
                                $rvAvatarUrl = $c['avatar_url'] ?? null;
                                $rvInitial = mb_strtoupper(mb_substr(trim($rvName), 0, 1));
                                $rvDate    = null;
                                try { $rvDate = ! empty($c['date']) ? \Carbon\Carbon::parse($c['date'])->locale($locale)->translatedFormat('F Y') : null; } catch (\Throwable $e) { $rvDate = null; }
                                $rvTourT   = $c['tour']['title'] ?? null;
                                $rvMeta    = trim(implode(' · ', array_filter(($rvTourT || $rvDate) ? [$rvTourT, $rvDate] : [$c['country'] ?? null, ($c['platform'] ?? 'web') !== 'web' ? ($c['platform_label'] ?? null) : null])));
                                $rvPhotoUrls = array_slice((array) ($c['photos'] ?? []), 0, 3);
                                $rvLong    = mb_strlen($rvQuote) > 150;
                            @endphp
                            <figure class="fh-rv" data-platform="{{ $c['platform'] ?? 'web' }}" data-destino="{{ $c['destination'] ?? '' }}" data-fotos="{{ ! empty($c['has_photos']) ? 1 : 0 }}">
                                <div class="fh-rv__av">
                                    @if ($rvAvatarUrl)
                                        <img src="{{ $rvAvatarUrl }}" alt="" loading="lazy" width="50" height="50">
                                    @else
                                        <span aria-hidden="true">{{ $rvInitial }}</span>
                                    @endif
                                </div>
                                <div class="fh-rv__main">
                                    <div class="fh-rv__head">
                                        <div class="fh-rv__who">
                                            <figcaption class="fh-rv__name">{{ $rvName }}</figcaption>
                                            @if ($rvMeta !== '')<p class="fh-rv__meta">{{ $rvMeta }}</p>@endif
                                        </div>
                                        <span class="fh-stars" role="img" aria-label="{{ $rvRating }} / 5">@for ($i = 1; $i <= 5; $i++)<svg viewBox="0 0 24 24" aria-hidden="true" class="{{ $i <= $rvRating ? 'on' : 'off' }}"><path d="{{ $fhStarPath }}" fill="currentColor"/></svg>@endfor</span>
                                    </div>
                                    <blockquote class="fh-rv__q{{ $rvLong ? ' is-clamped' : '' }}">{{ $rvQuote }}</blockquote>
                                    @if ($rvLong)
                                        <button type="button" class="fh-rv__more"
                                                @click="review = { name: @js($rvName), meta: @js($rvMeta), quote: @js($rvQuote), rating: {{ $rvRating }}, avatar: @js($rvAvatarUrl), initial: @js($rvInitial) }; reviewModalOpen = true">{{ __('ui.see_more') }}</button>
                                    @endif
                                    @if (count($rvPhotoUrls))
                                        <div class="fh-rv__photos">@foreach ($rvPhotoUrls as $pu)<button type="button" class="fh-rv__ph" data-lightbox="{{ $pu }}" aria-label="{{ $L('Ampliar foto', 'Enlarge photo', 'Ampliar foto') }}"><img src="{{ $pu }}" alt="" loading="lazy" decoding="async" width="120" height="100"></button>@endforeach</div>
                                    @endif
                                </div>
                            </figure>
                        @endforeach
                    </div>
                    <p class="fh-rev__empty" id="fh-rev-empty" hidden>{{ $L('No hay reseñas con ese filtro.', 'No reviews match this filter.', 'Não há avaliações com esse filtro.') }}</p>
                @endif

                <div class="fh-rev__google">
                    <a href="{{ $googleReviewsUrl }}" target="_blank" rel="noopener nofollow" class="fh-gbtn">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1Z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.15-4.53H2.18v2.84A11 11 0 0 0 12 23Z"/><path fill="#FBBC05" d="M5.85 14.1a6.6 6.6 0 0 1 0-4.2V7.06H2.18a11 11 0 0 0 0 9.88l3.67-2.84Z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1A11 11 0 0 0 2.18 7.06l3.67 2.84C6.71 7.3 9.14 5.38 12 5.38Z"/></svg>
                        {{ __('ui.see_all_google_reviews') }} <span aria-hidden="true">→</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if ($fhHasFilters)
    <script>
        (function () {
            var box = document.getElementById('fh-rev-filter'); if (!box) return;
            var cards = document.querySelectorAll('#fh-revs > .fh-rv'), empty = document.getElementById('fh-rev-empty');
            box.addEventListener('click', function (e) {
                var b = e.target.closest('button[data-f]'); if (!b) return;
                box.querySelectorAll('button').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
                var t = b.dataset.f, v = b.dataset.v || '', n = 0;
                cards.forEach(function (c) {
                    var ok = t === 'all' || (t === 'platform' && c.dataset.platform === v) || (t === 'dest' && c.dataset.destino === v) || (t === 'photos' && c.dataset.fotos === '1');
                    c.hidden = !ok; if (ok) n++;
                });
                if (empty) empty.hidden = n > 0;
            });
        })();
    </script>
    @endif

    {{-- Popup: comentario completo (se abre desde "Ver más") --}}
    <div x-cloak x-show="reviewModalOpen" x-transition.opacity @keydown.escape.window="reviewModalOpen = false"
         class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-teal-950/60 backdrop-blur-sm" @click="reviewModalOpen = false"></div>
        <div x-show="reviewModalOpen" x-transition.scale.origin.center
             class="relative z-10 w-full max-w-md bg-cream-100 rounded-3xl shadow-2xl p-6 max-h-[85vh] overflow-y-auto">
            <button type="button" @click="reviewModalOpen = false"
                    class="absolute top-4 right-4 w-11 h-11 grid place-items-center rounded-full bg-teal-800/10 text-teal-800 hover:bg-teal-800/20"
                    aria-label="{{ $L('Cerrar', 'Close', 'Fechar') }}">✕</button>
            <div class="text-orange-400 text-lg leading-none mb-3" x-text="'★'.repeat(review.rating || 5) + '☆'.repeat(5 - (review.rating || 5))"></div>
            <blockquote class="text-sm text-teal-800/90 leading-relaxed whitespace-pre-line" x-text="'“' + (review.quote || '') + '”'"></blockquote>
            <div class="mt-5 pt-4 border-t border-teal-800/10 flex items-center gap-3">
                <template x-if="review.avatar"><img :src="review.avatar" :alt="review.name" class="w-11 h-11 rounded-full object-cover shrink-0"></template>
                <template x-if="!review.avatar"><span class="w-11 h-11 rounded-full bg-teal-800 text-white grid place-items-center font-display text-lg shrink-0" x-text="review.initial"></span></template>
                <div class="min-w-0">
                    <p class="font-bold text-teal-800 text-sm leading-tight" x-text="review.name"></p>
                    <p class="text-[11px] text-teal-800/70 leading-tight" x-text="review.meta"></p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── PREGUNTAS FRECUENTES (H2 SEO id faq-title). details/summary: el texto está SIEMPRE en el HTML = microdatos FAQPage ── --}}
@php
    $defaultHomeFaqs = \App\Support\HomeDefaults::faqs();
    $rawHomeFaqs = \App\Models\Setting::get('home_faqs');
    $faqs = $homeRepeater($rawHomeFaqs, $defaultHomeFaqs);

    // Fuente única del texto: Setting 'faqs' (question_/answer_). home_faqs / defaults solo si 'faqs' está vacío.
    $rawAeoFaqs = \App\Models\Setting::get('faqs');
    if (is_string($rawAeoFaqs)) { $rawAeoFaqs = json_decode($rawAeoFaqs, true); }
    $aeoFaqs = collect(is_array($rawAeoFaqs) ? $rawAeoFaqs : [])
        ->filter(fn ($x) => ! empty($x['question_' . $locale] ?? ($x['question_es'] ?? null)))
        ->values();
    if ($aeoFaqs->isNotEmpty()) {
        $faqs = $aeoFaqs->map(fn ($x, $k) => [
            'q_' . $locale => $x['question_' . $locale] ?? $x['question_es'],
            'a_' . $locale => $x['answer_' . $locale] ?? ($x['answer_es'] ?? ''),
        ])->all();
    }
@endphp
<section class="fh-sec fh-faq-sec" id="faq" aria-labelledby="faq-title">
    <div class="fh-wrap">
        <p class="fh-over">{{ \App\Models\Setting::get('home_sec_faq_eyebrow_' . $locale) ?: $L('Respuestas sin fricción', 'Answers without friction', 'Respostas sem atrito') }}</p>
        <h2 id="faq-title" class="fh-h2 fh-h2--reg">{{ \App\Models\Setting::get('home_sec_faq_title_' . $locale) ?: $L('Lo que quizá te preguntas', 'What you may be wondering', 'O que você talvez esteja se perguntando') }}</h2>
        <div class="lvt-faq fh-faq" itemscope itemtype="https://schema.org/FAQPage">
            @foreach ($faqs as $i => $faqItem)
            @php
                $q = $faqItem['q_' . $locale] ?? $faqItem['q_es'] ?? '';
                $a = $faqItem['a_' . $locale] ?? $faqItem['a_es'] ?? '';
            @endphp
                <details class="lvt-faq__item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                    <summary>
                        <span itemprop="name">{{ $q }}</span>
                        <span class="lvt-faq__plus" aria-hidden="true">+</span>
                    </summary>
                    <div class="lvt-faq__a" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                        <p itemprop="text">{{ $a }}</p>
                    </div>
                </details>
            @endforeach
        </div>
    </div>
</section>

{{-- ── CIERRE "El Perú te espera" (texto editable: home_cta_*; default traducido) ── --}}
<section class="fh-sec fh-cta-sec" aria-labelledby="cta-title">
    <div class="fh-wrap">
        <div class="fh-cta">
            <div class="fh-cta__txt">
                <p class="fh-cta__eyebrow">{{ \App\Models\Setting::get('home_cta_eyebrow_' . $locale) ?: $L('Tu historia empieza aquí', 'Your story starts here', 'Sua história começa aqui') }}</p>
                <h2 id="cta-title" class="fh-cta__title">
                    {!! \App\Models\Setting::get('home_cta_title_' . $locale) ?: $L('El Perú te espera. <span class="lvt-accent">Nosotros te guiamos.</span>', 'Peru is waiting. <span class="lvt-accent">We guide you.</span>', 'O Peru te espera. <span class="lvt-accent">Nós guiamos você.</span>') !!}
                </h2>
            </div>
            <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="fh-cta__btn" data-sheet="all" data-ga-cta="cta_final" data-ga-location="home_final">
                {{ $L('Descubrir mis experiencias', 'Discover my experiences', 'Descobrir minhas experiências') }}
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
</section>

{{-- ── VENTANAS (sheets): configuración + textos para public/js/home-sheets.js. Todo texto va en JSON escapado y se pinta con textContent. ── --}}
@php
    $fhTx = [
        'close' => ['Cerrar', 'Close', 'Fechar'], 'back' => ['Volver', 'Back', 'Voltar'],
        'loading' => ['Cargando…', 'Loading…', 'Carregando…'],
        'error' => ['No pudimos cargar la información. Revisa tu conexión e inténtalo de nuevo.', 'We could not load the information. Check your connection and try again.', 'Não foi possível carregar as informações. Verifique sua conexão e tente novamente.'],
        'retry' => ['Reintentar', 'Try again', 'Tentar novamente'],
        'contact_title' => ['Habla con Lima View Tours', 'Talk to Lima View Tours', 'Fale com a Lima View Tours'],
        'contact_eyebrow' => ['Asistencia real', 'Real assistance', 'Assistência real'],
        'contact_h' => ['¿Te ayudamos a elegir?', 'Can we help you choose?', 'Ajudamos você a escolher?'],
        'contact_p' => ['Escríbenos por WhatsApp y te recomendamos el tour ideal según tu tiempo, tus intereses y tu destino.', 'Message us on WhatsApp and we will recommend the ideal tour for your time, interests and destination.', 'Escreva para nós no WhatsApp e recomendamos o tour ideal para seu tempo, interesses e destino.'],
        'contact_btn' => ['Abrir WhatsApp', 'Open WhatsApp', 'Abrir WhatsApp'],
        'search_title' => ['Buscar tours', 'Search tours', 'Buscar tours'],
        'search_ph' => ['Ej. Lima, catacumbas, aventura', 'E.g. Lima, catacombs, adventure', 'Ex. Lima, catacumbas, aventura'],
        'search_btn' => ['Ver tours encontrados', 'See matching tours', 'Ver tours encontrados'],
        'suggest' => ['Prueba con', 'Try', 'Experimente'],
        'res_all' => ['Todos los tours', 'All tours', 'Todos os tours'],
        'res_rec' => ['Tu tour recomendado', 'Your recommended tour', 'Seu tour recomendado'],
        'res_dest' => ['Tours en {x}', 'Tours in {x}', 'Tours em {x}'],
        'res_found' => ['Resultados de búsqueda', 'Search results', 'Resultados da busca'],
        'res_quick' => ['Tours recomendados', 'Recommended tours', 'Tours recomendados'],
        'adv_eyebrow' => ['Lima View Tours · Tu asesor', 'Lima View Tours · Your advisor', 'Lima View Tours · Seu assessor'],
        'no_exact' => ['No encontramos una coincidencia exacta. Te mostramos las alternativas más cercanas.', 'We found no exact match. Here are the closest alternatives.', 'Não encontramos uma correspondência exata. Veja as alternativas mais próximas.'],
        'exact' => ['Coincide con tu elección', 'Matches your choice', 'Corresponde à sua escolha'],
        'alt' => ['Alternativa cercana', 'Close alternative', 'Alternativa próxima'],
        'see_all' => ['Ver todos en la página de tours', 'See all on the tours page', 'Ver todos na página de tours'],
        'details' => ['Ver detalles', 'See details', 'Ver detalhes'], 'book' => ['Reservar', 'Book', 'Reservar'],
        'from' => ['Desde', 'From', 'A partir de'], 'now' => ['Ahora', 'Now', 'Agora'], 'before' => ['Antes', 'Was', 'Antes'], 'pp' => ['P/P', 'P/P', 'P/P'], 'grp' => ['Grupo', 'Group', 'Grupo'],
        'off' => ['Descuento', 'Off', 'Desconto'],
        'wa_consult' => ['Consultar por WhatsApp', 'Ask on WhatsApp', 'Consultar pelo WhatsApp'],
        'wa_empty' => ['¿No ves lo que buscas? Escríbenos por WhatsApp.', 'Not seeing what you need? Message us on WhatsApp.', 'Não encontrou o que procura? Fale conosco no WhatsApp.'],
        'tour_title' => ['Detalles del tour', 'Tour details', 'Detalhes do tour'],
        'duration' => ['Duración', 'Duration', 'Duração'], 'language' => ['Idioma', 'Language', 'Idioma'], 'departure' => ['Salida', 'Departure', 'Saída'], 'type' => ['Tipo', 'Type', 'Tipo'],
        'region' => ['Destino', 'Destination', 'Destino'],
        'includes' => ['Incluye', 'Includes', 'Inclui'], 'excludes' => ['No incluye', 'Not included', 'Não inclui'],
        'pickup' => ['Recojo', 'Pickup', 'Transporte'], 'pickup_zones' => ['Zonas', 'Areas', 'Zonas'],
        'reviews_word' => ['reseñas', 'reviews', 'avaliações'], 'review_word' => ['reseña', 'review', 'avaliação'],
        'flex' => [__('ui.tour_flex_pay_line', [], 'es'), __('ui.tour_flex_pay_line', [], 'en'), __('ui.tour_flex_pay_line', [], 'pt')],
        'reserve_h' => ['Elige tu fecha', 'Choose your date', 'Escolha sua data'],
        'date' => ['Fecha del tour', 'Tour date', 'Data do tour'], 'adults' => ['Adultos', 'Adults', 'Adultos'],
        'reserve_btn' => ['Reservar ahora', 'Book now', 'Reservar agora'], 'adding' => ['Agregando…', 'Adding…', 'Adicionando…'],
        'date_required' => ['Elige una fecha para continuar.', 'Choose a date to continue.', 'Escolha uma data para continuar.'],
        'date_blocked' => ['Esa fecha no está disponible. Elige otra.', 'That date is not available. Choose another.', 'Essa data não está disponível. Escolha outra.'],
        'date_range' => ['Elige una fecha dentro del rango disponible.', 'Choose a date within the available range.', 'Escolha uma data dentro do período disponível.'],
        'reserve_err' => ['No pudimos agregar el tour. Inténtalo de nuevo.', 'We could not add the tour. Please try again.', 'Não foi possível adicionar o tour. Tente novamente.'],
        'reserve_419' => ['Tu sesión venció. Recarga la página e inténtalo de nuevo.', 'Your session expired. Reload the page and try again.', 'Sua sessão expirou. Recarregue a página e tente novamente.'],
        'full_page' => ['Ver ficha completa', 'See full tour page', 'Ver página completa'],
        'rev_title' => ['Opiniones y confianza', 'Reviews and trust', 'Avaliações e confiança'],
        'rev_eyebrow' => ['Historias de nuestros viajeros', 'Stories from our travelers', 'Histórias dos nossos viajantes'],
        'rev_all' => ['Todos', 'All', 'Todos'], 'rev_photos' => ['Con fotos', 'With photos', 'Com fotos'],
        'rev_more' => ['Ver más reseñas', 'See more reviews', 'Ver mais avaliações'],
        'rev_empty' => ['No hay reseñas con ese filtro.', 'No reviews match this filter.', 'Não há avaliações com esse filtro.'],
        'rev_avg' => ['Calificación promedio de', 'Average rating from', 'Avaliação média de'],
        'traveler' => ['Viajero', 'Traveler', 'Viajante'],
        'photo_title' => ['Foto del viajero', 'Traveler photo', 'Foto do viajante'],
        'benefit_cta' => ['Ver comentarios de viajeros', 'See traveler reviews', 'Ver comentários de viajantes'],
        'stars' => ['estrellas', 'stars', 'estrelas'],
    ];
    $fhTi = $locale === 'pt' ? 2 : ($locale === 'en' ? 1 : 0);
    $fhCfg = [
        'locale' => $locale,
        'api' => $homeApi,
        'benefits' => array_map(fn ($t) => ['key' => $t['k'], 'title' => $t['v'], 'label' => $t['l'], 'detail' => $t['detail']], $fhTrust ?? []),
        'tx' => array_map(fn ($t) => $t[$fhTi], $fhTx),
    ];
@endphp
@if (! empty($homeApi))
<script type="application/json" id="lvt-home-cfg">{!! json_encode($fhCfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endif
@push('head')
    <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/raleway-var.woff2') }}" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/roboto-var.woff2') }}" crossorigin>
@endpush
@push('scripts')
    <script src="{{ asset('js/home-sheets.js') }}?v=20261121a" defer></script>
@endpush

@endsection
