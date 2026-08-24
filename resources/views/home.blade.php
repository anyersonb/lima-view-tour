@extends('layouts.app')

{{-- Editables desde el admin: Configuración → SEO → Metas por página → Home.
     Si el campo está vacío cae al texto por defecto de lang/{idioma}/seo.php. --}}
@section('title', \App\Support\PageSeo::title('home', __('seo.home_title')))
@section('description', \App\Support\PageSeo::description('home', __('seo.home_description')))
@section('header_variant', 'transparent')

@push('schema')
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => __('seo.site_name'),
    'url' => url('/' . app()->getLocale()),
    'inLanguage' => app()->getLocale(),
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => url('/' . app()->getLocale() . '/tours?q={search_term_string}'),
        'query-input' => 'required name=search_term_string',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@php
    $locale = app()->getLocale();
    // Helper de traducción inline ES / EN / PT (chrome del home)
    $L = fn (string $es, string $en, string $pt): string => $locale === 'pt' ? $pt : ($locale === 'en' ? $en : $es);

    $featuredTours = $featuredTours ?? collect();
    $toursIca      = $toursIca      ?? collect();
    $toursLima     = $toursLima     ?? collect();
    $toursCusco    = $toursCusco    ?? collect();
    $st            = $siteSettings  ?? [];

    $staticFeatured = collect([
        (object)['title' => 'Tour de día Completo al Oasis de Huacachina + Islas Ballestas', 'price_before' => 125, 'price' => 100, 'badge_text' => 'CUPOS LIMITADOS', 'badge_type' => 'warn', 'rating' => '4.6', 'reviews_count' => 30, 'cover_image' => 'assets/banners/Rectangle 19210.jpg', 'slug' => 'huacachina-paracas-full-day'],
        (object)['title' => 'Full Day Lima Ancestral, Colonial y Moderna', 'price_before' => 125, 'price' => 100, 'badge_text' => '5 CUPOS DE 20', 'badge_type' => 'error', 'rating' => '4.8', 'reviews_count' => 28, 'cover_image' => 'assets/banners/Rectangle 19211.jpg', 'slug' => 'lima-ancestral-colonial'],
        (object)['title' => 'Líneas de Nazca + Oasis de Huacachina e Islas Ballestas', 'price_before' => 250, 'price' => 220, 'badge_text' => 'MÁS RESERVADO', 'badge_type' => 'success', 'rating' => '4.6', 'reviews_count' => 30, 'cover_image' => 'assets/banners/Rectangle 19212.jpg', 'slug' => 'nazca-huacachina-2-dias'],
        (object)['title' => 'Full day a las Líneas de Nazca', 'price_before' => 350, 'price' => 300, 'badge_text' => 'CUPOS LIMITADOS', 'badge_type' => 'warn', 'rating' => '4.8', 'reviews_count' => 28, 'cover_image' => 'assets/banners/Rectangle 19214.jpg', 'slug' => 'nazca-full-day'],
    ]);

    if ($featuredTours->isEmpty()) { $featuredTours = $staticFeatured; }
    if ($toursIca->isEmpty())      { $toursIca      = $staticFeatured; }
    if ($toursLima->isEmpty())     { $toursLima      = $staticFeatured; }
    if ($toursCusco->isEmpty())    { $toursCusco     = $staticFeatured; }

    $normalizeTours = static function (\Illuminate\Support\Collection $collection): array {
        return $collection->map(static function ($t): array {
            return [
                'title'     => is_object($t) ? $t->title                                         : $t['title'],
                'before'    => is_object($t) ? ($t->price_before ?? null)                        : ($t['price_before'] ?? null),
                'now'       => is_object($t) ? $t->price                                         : $t['price'],
                'badge'     => is_object($t) ? ($t->badge_text ?? null)                          : ($t['badge_text'] ?? null),
                'badgeType' => is_object($t) ? ($t->badge_type ?? 'warn')                        : ($t['badge_type'] ?? 'warn'),
                'rating'    => is_object($t) ? $t->rating                                        : $t['rating'],
                'reviews'   => is_object($t) ? ($t->reviews_count ?? 0)                         : ($t['reviews_count'] ?? 0),
                'img'       => is_object($t)
                    ? (\App\Support\ImagePath::url($t->cover_image ?? null) ?? asset('assets/banners/banner-hero.jpg'))
                    : (\App\Support\ImagePath::url($t['cover_image'] ?? null) ?? asset('assets/banners/banner-hero.jpg')),
                'slug'      => is_object($t) ? ($t->slug ?? '')                                  : ($t['slug'] ?? ''),
                'showBestSeller' => (bool) (is_object($t) ? ($t->show_best_seller ?? true)       : ($t['show_best_seller'] ?? true)),
                'showOffer'      => (bool) (is_object($t) ? ($t->show_offer_badge ?? true)       : ($t['show_offer_badge'] ?? true)),
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
    $btSlug    = is_object($firstTour) ? ($firstTour->slug ?? 'huacachina-paracas-full-day') : ($firstTour['slug'] ?? 'huacachina-paracas-full-day');
    $btImg     = ($firstTour && ! empty(is_object($firstTour) ? $firstTour->cover_image : $firstTour['cover_image'] ?? null))
        ? (\App\Support\ImagePath::url(is_object($firstTour) ? $firstTour->cover_image : $firstTour['cover_image']) ?: asset('assets/banners/Rectangle 19210.jpg'))
        : asset('assets/banners/Rectangle 19210.jpg');
    $btUrl     = $btSlug ? route('tours.show', ['locale' => $locale, 'slug' => $btSlug]) : '#';

    // Separador decorativo reutilizable
    $sep = '<div class="flex items-center justify-center gap-2 mt-4 mb-0" aria-hidden="true">
        <span class="h-px w-8 bg-orange-500/50"></span>
        <svg class="w-3 h-3 text-orange-500" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l1.5 8.5L22 12l-8.5 1.5L12 22l-1.5-8.5L2 12l8.5-1.5L12 2z"/></svg>
        <span class="h-px w-8 bg-orange-500/50"></span>
    </div>';

    // Brújula SVG naranja outline (para secciones con fondo claro)
    $brujula = '<svg class="w-11 h-11 text-orange-500 mx-auto mb-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5.5"/><path d="M15.5 8.5l-2 5-5 2 2-5 5-2z" fill="currentColor" opacity=".9"/></svg>';
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
        flex-basis: calc((100% - 32px) / 3);
        max-width: calc((100% - 32px) / 3);
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
        flex-basis: calc((100% - 48px) / 4);
        max-width: calc((100% - 48px) / 4);
        scroll-snap-align: start;
    }
}
</style>

{{-- ============================================================
     SECCIÓN 1 — HERO + STATS
     Stats card está DENTRO del hero. El hero usa flex-col con
     mt-auto en la card para empujarla al fondo. padding-bottom
     del hero hace que la card quede half-inside half-outside.
     Siguiente sección tiene margin-top negativo para el overlap.
     ============================================================ --}}
<section class="relative isolate text-white" aria-labelledby="hero-title">

    {{-- Hero background image (editable via Settings > Home) --}}
    @php
        $heroImgSetting = \App\Models\Setting::get('home_hero_image');
        if (is_array($heroImgSetting)) { $heroImgSetting = $heroImgSetting[0] ?? ''; }
        $heroImgSetting = trim((string) $heroImgSetting);
        $heroImgUrl = ($heroImgSetting !== '' && $heroImgSetting !== '[]' && $heroImgSetting !== '""')
            ? \Illuminate\Support\Facades\Storage::disk('media')->url($heroImgSetting)
            : asset('assets/banners/hero-machu-picchu.png');
    @endphp
    <div class="absolute inset-0 -z-10">
        <img src="{{ $heroImgUrl }}" alt=""
             class="w-full h-full object-cover object-[60%_center] lg:object-center"
             loading="eager" fetchpriority="high">
        <div class="absolute inset-0 bg-gradient-to-b from-black/30 via-black/35 to-black/75"></div>
    </div>

    {{-- Flex column: texto arriba, stats card al fondo --}}
    <div class="home-hero__wrap relative px-5
                pt-24 pb-0
                sm:pt-28
                md:pt-36
                lg:px-10 lg:pt-[130px] lg:pb-20
                xl:pt-[150px]
                min-h-[640px] sm:min-h-[700px] md:min-h-[760px] lg:min-h-[660px] xl:min-h-[760px]
                flex flex-col
                container mx-auto max-w-7xl">

        {{-- Línea decorativa + spark --}}
        <div class="flex items-center gap-2 mb-5" aria-hidden="true">
            <span class="h-px w-12 bg-orange-400"></span>
            <svg class="w-3.5 h-3.5 text-orange-400" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l1.5 8.5L22 12l-8.5 1.5L12 22l-1.5-8.5L2 12l8.5-1.5L12 2z"/></svg>
        </div>

        @php
            // Hero title: Setting with fallback to hardcoded originals
            $heroTitleDefault = [
                'es' => "Descubre\nlo que te\ntransforma.",
                'en' => "Discover\nwhat\ntransforms you.",
                'pt' => "Descubra\no que\ntransforma você.",
            ];
            $heroTitleRaw = \App\Models\Setting::get('home_hero_title_' . $locale)
                ?: ($heroTitleDefault[$locale] ?? $heroTitleDefault['es']);
        @endphp
        <h1 id="hero-title"
            class="home-hero__title font-display font-normal
                   text-[52px] leading-[0.92]
                   sm:text-[68px]
                   md:text-[76px]
                   lg:text-[88px]
                   xl:text-[104px] max-w-3xl">
            {!! nl2br(e($heroTitleRaw)) !!}
        </h1>
        @php $heroCtaLabel = $L('EXPLORAR EXPERIENCIAS', 'EXPLORE EXPERIENCES', 'EXPLORAR EXPERIÊNCIAS'); @endphp

        {{-- CTA pill --}}
        <a href="{{ route('tours.index', ['locale' => $locale]) }}"
           class="mt-7 inline-flex items-center justify-between gap-3 self-start
                  border border-orange-400/70 rounded-full
                  pl-5 pr-1.5 py-1.5
                  font-semibold text-sm
                  bg-black/15 backdrop-blur-sm hover:bg-black/25 transition">
            <span class="inline-flex items-center gap-3">
                <svg class="w-5 h-5 text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M15 9l-2 6-4 2 2-6 4-2z" fill="currentColor"/>
                </svg>
                <span class="uppercase tracking-wider text-white">{{ $heroCtaLabel }}</span>
            </span>
            <span class="w-9 h-9 rounded-full bg-orange-400 grid place-items-center text-teal-900 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
            </span>
        </a>

        {{-- Trust pills --}}
        <div class="mt-5 flex flex-wrap gap-2">
            <span class="inline-flex items-center gap-2 border border-white/35 rounded-full px-3.5 py-1.5 text-[12px] bg-black/15 backdrop-blur-sm">
                <svg class="w-4 h-4 text-orange-300 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                {{ $L('Reserva fácil', 'Easy booking', 'Reserva fácil') }}
            </span>
            <span class="inline-flex items-center gap-2 border border-white/35 rounded-full px-3.5 py-1.5 text-[12px] bg-black/15 backdrop-blur-sm">
                <svg class="w-4 h-4 text-orange-300 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                {{ $L('Segura', 'Secure', 'Segura') }}
            </span>
            <span class="inline-flex items-center gap-2 border border-white/35 rounded-full px-3.5 py-1.5 text-[12px] bg-black/15 backdrop-blur-sm">
                <svg class="w-4 h-4 text-orange-300 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l2.25 2.25L15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ $L('100% garantizada', '100% guaranteed', '100% garantida') }}
            </span>
        </div>

        @php
            // Stats values — editable via Settings > Home; fallback to original hardcoded values
            $statRating    = \App\Models\Setting::get('stats_rating')    ?: '4.8';
            $statTravelers = \App\Models\Setting::get('home_stat_travelers') ?: '+2,000';
            $statYears     = \App\Models\Setting::get('stats_years')     ?: '+11';
            $statTours     = \App\Models\Setting::get('stats_tours')     ?: '+50';
            $statRatingLabel    = \App\Models\Setting::get('home_stat_rating_label')    ?: $L('Valoración', 'Rating', 'Avaliação');
            $statTravelersLabel = \App\Models\Setting::get('home_stat_travelers_label') ?: $L('Viajeros felices', 'Happy travelers', 'Viajantes felizes');
            $statYearsLabel     = \App\Models\Setting::get('home_stat_years_label')     ?: $L('Años de experiencia', 'Years of experience', 'Anos de experiência');
            $statToursLabel     = \App\Models\Setting::get('home_stat_tours_label')     ?: $L('Tours únicos', 'Unique tours', 'Tours exclusivos');
        @endphp
        {{-- STATS CARD — empujada al fondo con mt-auto, sobresale del hero --}}
        <div class="mt-auto pt-10 lg:hidden">
            <div id="stats-card-mobile"
                 class="bg-white text-teal-800 rounded-2xl
                        shadow-2xl ring-1 ring-black/5
                        px-3 py-5
                        grid grid-cols-4 gap-1">

                {{-- Valoración --}}
                <div class="text-center">
                    <svg class="w-6 h-6 mx-auto text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                    <p class="font-display text-2xl mt-2 leading-none">{{ $statRating }}</p>
                    <p class="mt-1 text-[10px] text-teal-800/70">{{ $statRatingLabel }}</p>
                    <p class="mt-0.5 text-orange-400 text-[10px]" aria-hidden="true">★★★★★</p>
                </div>
                <div class="text-center border-l border-cream-200/80">
                    <svg class="w-6 h-6 mx-auto text-teal-800" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                    <p class="font-display text-2xl mt-2 leading-none">{{ $statTravelers }}</p>
                    <p class="mt-1 text-[10px] text-teal-800/70 leading-tight">{{ $statTravelersLabel }}</p>
                    <div class="mx-auto mt-1.5 h-0.5 w-6 bg-orange-400"></div>
                </div>
                <div class="text-center border-l border-cream-200/80">
                    <svg class="w-6 h-6 mx-auto text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/></svg>
                    <p class="font-display text-2xl mt-2 leading-none">{{ $statYears }}</p>
                    <p class="mt-1 text-[10px] text-teal-800/70 leading-tight">{{ $statYearsLabel }}</p>
                    <div class="mx-auto mt-1.5 h-0.5 w-6 bg-orange-400"></div>
                </div>
                <div class="text-center border-l border-cream-200/80">
                    <svg class="w-6 h-6 mx-auto text-teal-800" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z"/></svg>
                    <p class="font-display text-2xl mt-2 leading-none">{{ $statTours }}</p>
                    <p class="mt-1 text-[10px] text-teal-800/70">{{ $statToursLabel }}</p>
                    <div class="mx-auto mt-1.5 h-0.5 w-6 bg-orange-400"></div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================================================
     STATS CARD DESKTOP — solo lg+, overlap sobre hero
     ============================================================ --}}
<section class="hidden lg:block relative z-10 pb-12 bg-cream-100"
         aria-labelledby="stats-title">
    <h2 id="stats-title" class="sr-only">Estadísticas</h2>
    <div class="container mx-auto px-5 lg:px-10 max-w-7xl -mt-24 xl:-mt-28">
        <div class="bg-white text-teal-800 rounded-3xl
                    shadow-2xl ring-1 ring-black/5
                    px-12 py-10
                    grid grid-cols-4 gap-6">

            {{-- Valoración --}}
            <div class="text-center">
                <svg class="w-6 h-6 lg:w-9 lg:h-9 mx-auto text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                <p class="font-display text-2xl lg:text-5xl mt-2 leading-none">{{ $statRating }}</p>
                <p class="mt-1 text-[10px] lg:text-sm text-teal-800/70">{{ $statRatingLabel }}</p>
                <p class="mt-0.5 text-orange-400 text-[10px] lg:text-base tracking-tight" aria-hidden="true">★★★★★</p>
            </div>

            {{-- Viajeros --}}
            <div class="text-center border-l border-cream-200/80">
                <svg class="w-6 h-6 lg:w-9 lg:h-9 mx-auto text-teal-800" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                <p class="font-display text-2xl lg:text-5xl mt-2 leading-none">{{ $statTravelers }}</p>
                <p class="mt-1 text-[10px] lg:text-sm text-teal-800/70 leading-tight">{{ $statTravelersLabel }}</p>
                <div class="mx-auto mt-1.5 h-0.5 w-6 lg:w-10 bg-orange-400"></div>
            </div>

            {{-- Años --}}
            <div class="text-center border-l border-cream-200/80">
                <svg class="w-6 h-6 lg:w-9 lg:h-9 mx-auto text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/></svg>
                <p class="font-display text-2xl lg:text-5xl mt-2 leading-none">{{ $statYears }}</p>
                <p class="mt-1 text-[10px] lg:text-sm text-teal-800/70 leading-tight">{{ $statYearsLabel }}</p>
                <div class="mx-auto mt-1.5 h-0.5 w-6 lg:w-10 bg-orange-400"></div>
            </div>

            {{-- Tours --}}
            <div class="text-center border-l border-cream-200/80">
                <svg class="w-6 h-6 lg:w-9 lg:h-9 mx-auto text-teal-800" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z"/></svg>
                <p class="font-display text-2xl lg:text-5xl mt-2 leading-none">{{ $statTours }}</p>
                <p class="mt-1 text-[10px] lg:text-sm text-teal-800/70">{{ $statToursLabel }}</p>
                <div class="mx-auto mt-1.5 h-0.5 w-6 lg:w-10 bg-orange-400"></div>
            </div>
        </div>
    </div>
</section>


{{-- ============================================================
     SECCIÓN 2 — MÁS COMPRADOS (carrusel compacto con peek)
     ============================================================ --}}
<section class="bg-cream-100 px-5 py-10 lg:py-20" aria-labelledby="bestseller-title">
    <div class="container mx-auto max-w-7xl lg:px-5">

        {{-- Header --}}
        <div class="text-center mb-8">
            <p class="text-[10px] uppercase tracking-[0.25em] text-orange-600 font-bold">
                {{ \App\Models\Setting::get('home_sec_featured_eyebrow_' . $locale) ?: 'NUESTROS TOURS' }}
            </p>
            <h2 id="bestseller-title" class="mt-2 font-display text-3xl lg:text-5xl text-teal-800">
                {{ \App\Models\Setting::get('home_sec_featured_title_' . $locale) ?: 'Más Comprados' }}
            </h2>
            {!! $sep !!}
        </div>

        @php
            $mcTours = $featuredTours->isNotEmpty()
                ? $normalizeTours($featuredTours->take(8))
                : $normalizeTours($staticFeatured);
            $mcCount = count($mcTours);
        @endphp

        {{-- Carrusel Alpine scroll-snap (mismo patrón que sección 2B) --}}
        <div x-data="{
                current: 0,
                total: {{ $mcCount }},
                nav(dir) {
                    let el = document.getElementById('mc-carousel');
                    let cx = el.getBoundingClientRect().left + el.clientWidth / 2;
                    let cur = 0, bd = Infinity;
                    [...el.children].forEach((ch, i) => {
                        let r = ch.getBoundingClientRect();
                        let d = Math.abs((r.left + r.width / 2) - cx);
                        if (d < bd) { bd = d; cur = i; }
                    });
                    this.goTo(Math.min(Math.max(cur + dir, 0), this.total - 1));
                },
                goTo(i) {
                    let el = document.getElementById('mc-carousel');
                    let c = el.children[i];
                    if (!c) return;
                    let target = el.scrollLeft + c.getBoundingClientRect().left - el.getBoundingClientRect().left - (el.clientWidth - c.clientWidth) / 2;
                    target = Math.max(0, Math.min(target, el.scrollWidth - el.clientWidth));
                    this.current = i;
                    // Snap mandatory cancela el scroll suave programático: lo apagamos durante la animación.
                    el.style.scrollSnapType = 'none';
                    el.scrollTo({ left: target, behavior: 'smooth' });
                    // Fallback fiable: si el suave no progresó (motion reducido / headless), saltar; luego restaurar snap.
                    clearTimeout(this._snapT);
                    this._snapT = setTimeout(() => {
                        if (Math.abs(el.scrollLeft - target) > 4) el.scrollTo({ left: target, behavior: 'instant' });
                        el.style.scrollSnapType = 'x mandatory';
                    }, 420);
                }
             }" class="relative">

            {{-- Track --}}
            <div class="carousel-track gap-4 pb-2 -mx-5 px-4 sm:mx-0"
                 style="padding-left:1rem;padding-right:1rem;"
                 id="mc-carousel"
                 @scroll.debounce.100ms="
                     let el = $el;
                     let cx = el.getBoundingClientRect().left + el.clientWidth / 2;
                     let best = 0, bestD = Infinity;
                     [...el.children].forEach((ch, i) => {
                         let r = ch.getBoundingClientRect();
                         let d = Math.abs((r.left + r.width / 2) - cx);
                         if (d < bestD) { bestD = d; best = i; }
                     });
                     current = best;
                 ">

                @foreach ($mcTours as $i => $mct)
                    <div class="tour-slide">
                        <x-tour-card
                            :title="$mct['title']"
                            :slug="$mct['slug']"
                            :img="$mct['img']"
                            :before="$mct['before']"
                            :now="$mct['now']"
                            :badge="$mct['badge']"
                            :badgeType="$mct['badgeType']"
                            :rating="$mct['rating']"
                            :reviews="$mct['reviews']"
                            :isMasVendido="$mct['showBestSeller']"
                            :showBestSeller="$mct['showBestSeller']"
                            :showOffer="$mct['showOffer']"
                            :pickup="true"
                        />
                    </div>
                @endforeach
            </div>

            {{-- Flechas DESKTOP (slider) --}}
            <button type="button" @click="nav(-1)" aria-label="Tour anterior"
                    class="hidden lg:flex"
                    style="position:absolute;top:42%;left:0;transform:translateY(-50%);margin-left:-12px;width:46px;height:46px;border-radius:9999px;background:#fff;box-shadow:0 6px 18px rgba(0,0,0,.14);align-items:center;justify-content:center;color:#15474B;border:1px solid rgba(20,71,75,.1);z-index:20;cursor:pointer;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button type="button" @click="nav(1)" aria-label="Tour siguiente"
                    class="hidden lg:flex"
                    style="position:absolute;top:42%;right:0;transform:translateY(-50%);margin-right:-12px;width:46px;height:46px;border-radius:9999px;background:#fff;box-shadow:0 6px 18px rgba(0,0,0,.14);align-items:center;justify-content:center;color:#15474B;border:1px solid rgba(20,71,75,.1);z-index:20;cursor:pointer;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>

            {{-- Controles inferiores: flecha + dots + flecha (mobile/tablet) --}}
            <div class="mt-5 flex items-center justify-center gap-3 lg:hidden">
                <button type="button" @click="nav(-1)" aria-label="Tour anterior"
                        class="w-8 h-8 rounded-full bg-white shadow ring-1 ring-teal-800/10 grid place-items-center text-teal-800 hover:bg-teal-800 hover:text-white transition shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <div class="flex items-center gap-2" aria-hidden="true">
                    @for ($i = 0; $i < $mcCount; $i++)
                        <button type="button" @click="goTo({{ $i }})"
                                class="transition-all duration-200 rounded-full"
                                :class="current === {{ $i }} ? 'h-2 w-6 bg-orange-500' : 'h-2 w-2 bg-cream-300'"
                                :aria-label="'Ir a tour {{ $i + 1 }}'"></button>
                    @endfor
                </div>
                <button type="button" @click="nav(1)" aria-label="Siguiente tour"
                        class="w-8 h-8 rounded-full bg-white shadow ring-1 ring-teal-800/10 grid place-items-center text-teal-800 hover:bg-teal-800 hover:text-white transition shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>

        </div>

        {{-- Link ver todos (mobile, centrado debajo del hint) --}}
        <div class="mt-6 text-center lg:hidden">
            <a href="{{ route('tours.index', ['locale' => $locale]) }}"
               class="inline-flex items-center gap-2 text-teal-800 hover:text-orange-500 transition text-sm font-semibold uppercase tracking-wider">
                Ver todos los tours
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
            </a>
        </div>

        {{-- Link ver todos (desktop, alineado a la derecha) --}}
        <div class="hidden lg:flex justify-end mt-6">
            <a href="{{ route('tours.index', ['locale' => $locale]) }}"
               class="inline-flex items-center gap-2 bg-teal-800 hover:bg-teal-900 text-white rounded-full px-6 py-3 font-semibold text-sm uppercase tracking-wider transition">
                Ver todos los tours
                <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
            </a>
        </div>
    </div>
</section>


{{-- ============================================================
     SECCIÓN 2B — TOURS EN LIMA, ICA Y CUSCO (carrusel tour-card)
     ============================================================ --}}
<section class="bg-cream-100 px-5 py-12 lg:py-16" aria-labelledby="lima-ica-cusco-title">
    <div class="container mx-auto max-w-7xl lg:px-5">

        {{-- Título centrado, sin eyebrow --}}
        <div class="text-center mb-8">
            <h2 id="lima-ica-cusco-title" class="font-display text-3xl lg:text-5xl text-teal-800 leading-tight">
                {{ \App\Models\Setting::get('home_sec_cities_title_' . $locale) ?: 'Tours en Lima, Ica y Cusco' }}
            </h2>
            {!! $sep !!}
        </div>

        @php
            // Usar tours reales si existen (aunque sean pocos); solo demo si la BD está vacía.
            $dbTours = $featuredTours->isNotEmpty()
                ? $featuredTours->take(6)
                : $staticFeatured;
            $limaCuscoCols  = $normalizeTours($dbTours);
            $limaCuscoCount = count($limaCuscoCols);
        @endphp

        {{-- Carrusel Alpine + scroll-snap --}}
        <div x-data="{ current: 0, total: {{ $limaCuscoCount }} }" class="relative">

            {{-- Track --}}
            <div class="carousel-track gap-5 pb-4 -mx-5 px-4 sm:mx-0"
                 style="padding-left:1rem;padding-right:1rem;"
                 id="lc-carousel"
                 @scroll.debounce.100ms="
                     let el = $el;
                     let w = el.scrollWidth - el.clientWidth;
                     if (w > 0) { current = Math.round((el.scrollLeft / w) * (total - 1)); }
                 ">

                @foreach ($limaCuscoCols as $i => $lct)
                    <div class="tour-slide">
                        <x-tour-card
                            :title="$lct['title']"
                            :slug="$lct['slug']"
                            :img="$lct['img']"
                            :before="$lct['before']"
                            :now="$lct['now']"
                            :badge="$lct['badge']"
                            :badgeType="$lct['badgeType']"
                            :rating="$lct['rating']"
                            :reviews="$lct['reviews']"
                            :isMasVendido="$lct['showBestSeller']"
                            :showBestSeller="$lct['showBestSeller']"
                            :showOffer="$lct['showOffer']"
                            :pickup="true"
                        />
                    </div>
                @endforeach
            </div>

            {{-- Dots (mobile/tablet) --}}
            <div class="mt-5 flex items-center justify-center gap-2 lg:hidden" aria-hidden="true">
                @for ($i = 0; $i < $limaCuscoCount; $i++)
                    <button type="button"
                            @click="
                                current = {{ $i }};
                                document.getElementById('lc-carousel').scrollTo({
                                    left: document.getElementById('lc-carousel').scrollWidth / {{ $limaCuscoCount }} * {{ $i }},
                                    behavior: 'smooth'
                                });
                            "
                            class="transition-all duration-200 rounded-full"
                            :class="current === {{ $i }} ? 'h-2 w-6 bg-orange-500' : 'h-2 w-2 bg-cream-300'"
                            :aria-label="'Ir a tour {{ $i + 1 }}'">
                    </button>
                @endfor
            </div>

            {{-- Flechas desktop --}}
            <button type="button"
                    class="hidden lg:flex absolute left-0 top-1/2 -translate-y-1/2 -translate-x-5
                           w-10 h-10 rounded-full bg-white shadow-md ring-1 ring-teal-800/10
                           items-center justify-center text-teal-800 hover:bg-teal-800 hover:text-white transition z-10"
                    @click="
                        let el = document.getElementById('lc-carousel');
                        el.scrollBy({ left: -el.offsetWidth / 3, behavior: 'smooth' });
                    "
                    aria-label="Anterior">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button type="button"
                    class="hidden lg:flex absolute right-0 top-1/2 -translate-y-1/2 translate-x-5
                           w-10 h-10 rounded-full bg-white shadow-md ring-1 ring-teal-800/10
                           items-center justify-center text-teal-800 hover:bg-teal-800 hover:text-white transition z-10"
                    @click="
                        let el = document.getElementById('lc-carousel');
                        el.scrollBy({ left: el.offsetWidth / 3, behavior: 'smooth' });
                    "
                    aria-label="Siguiente">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>
</section>


{{-- ============================================================
     SECCIÓN 3 — MÁS VISITADAS (Destinos)
     ============================================================ --}}
<section class="bg-cream-100 px-5 py-10 lg:py-20" aria-labelledby="destinos-title">
    <div class="container mx-auto max-w-7xl lg:px-5">

        {{-- Header --}}
        <div class="text-center mb-8">
            <p class="inline-flex items-center gap-3 text-[10px] uppercase tracking-[0.25em] text-orange-600 font-bold">
                <span class="h-px w-6 bg-orange-500/50"></span>{{ \App\Models\Setting::get('home_sec_destinos_eyebrow_' . $locale) ?: 'MÁS VISITADOS' }}<span class="h-px w-6 bg-orange-500/50"></span>
            </p>
            <h2 id="destinos-title" class="mt-3 font-display text-3xl lg:text-5xl text-teal-800 leading-tight">
                {!! \App\Models\Setting::get('home_sec_destinos_title_' . $locale) ?: 'Descubre las Ciudades<br>más Visitadas del Perú' !!}
            </h2>
            {!! $sep !!}
        </div>

        @php
            $defaultDestinos = [
                [
                    'title_es' => 'Tours en Cusco', 'title_en' => 'Tours in Cusco', 'title_pt' => 'Tours em Cusco',
                    'badge_es' => 'CULTURA E HISTORIA', 'badge_en' => 'CULTURE & HISTORY', 'badge_pt' => 'CULTURA E HISTÓRIA',
                    'desc_es'  => 'Descubre el corazón del Imperio Inca. Historia, arquitectura y tradiciones que te transportarán en el tiempo.',
                    'desc_en'  => 'Discover the heart of the Inca Empire. History, architecture and traditions that will transport you through time.',
                    'desc_pt'  => 'Descubra o coração do Império Inca. História, arquitetura e tradições que vão transportá-lo no tempo.',
                    'img'      => 'Rectangle 19218.jpg',
                    'iconPath' => 'M3 21h18M5 21V10.5L12 6l7 4.5V21M9 21v-6h6v6',
                    'deco'     => '<path d="M40 80 Q40 25, 50 15 Q60 25, 60 80 M47 55 L47 80 M53 55 L53 80 M50 15 L50 8 M46 22 L54 22"/>',
                ],
                [
                    'title_es' => 'Tours en Lima', 'title_en' => 'Tours in Lima', 'title_pt' => 'Tours em Lima',
                    'badge_es' => 'COSTA Y MODERNIDAD', 'badge_en' => 'COAST & MODERNITY', 'badge_pt' => 'COSTA E MODERNIDADE',
                    'desc_es'  => 'Vive la energía de la capital y disfruta de su cultura, gastronomía y atractivos únicos.',
                    'desc_en'  => 'Experience the energy of the capital and enjoy its culture, gastronomy and unique attractions.',
                    'desc_pt'  => 'Viva a energia da capital e desfrute de sua cultura, gastronomia e atrações únicas.',
                    'img'      => 'Rectangle 19216.jpg',
                    'iconPath' => 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0zM19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z',
                    'deco'     => '<path d="M40 80 L40 30 L50 20 L60 30 L60 80 M48 50 L52 50 M48 65 L52 65 M50 20 L50 13 L55 17"/>',
                ],
                [
                    'title_es' => 'Tours en Ica', 'title_en' => 'Tours in Ica', 'title_pt' => 'Tours em Ica',
                    'badge_es' => 'NATURALEZA Y AVENTURA', 'badge_en' => 'NATURE & ADVENTURE', 'badge_pt' => 'NATUREZA E AVENTURA',
                    'desc_es'  => 'Aventura, viñedos y oasis en el desierto de Ica. Naturaleza que sorprende en cada momento.',
                    'desc_en'  => 'Adventure, vineyards and oasis in the Ica desert. Nature that surprises at every moment.',
                    'desc_pt'  => 'Aventura, vinhedos e oásis no deserto de Ica. Natureza que surpreende a cada momento.',
                    'img'      => 'Rectangle 19219.jpg',
                    'iconPath' => 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z',
                    'deco'     => '<path d="M25 80 L40 50 L48 70 L60 35 L75 80 M55 70 Q60 60, 65 70"/>',
                ],
            ];
            $rawDestinos = \App\Models\Setting::get('home_destinos');
            $destinos = (is_array($rawDestinos) && count($rawDestinos)) ? $rawDestinos : $defaultDestinos;
        @endphp

        {{-- Banners: apilados en mobile, grid 3 columnas en desktop --}}
        <div class="space-y-5 lg:space-y-0 lg:grid lg:grid-cols-3 lg:gap-6">
            @foreach ($destinos as $dIdx => $destino)
                <article class="relative block w-full rounded-3xl overflow-hidden shadow-md min-h-[280px] lg:min-h-0 lg:aspect-[3/4]">
                    {{-- Imagen de fondo --}}
                    <img src="{{ \App\Support\ImagePath::homeImage(\App\Models\Setting::get('home_destino_img_' . ($dIdx + 1))) ?: (\App\Support\ImagePath::homeImage($destino['img'] ?? null) ?: asset('assets/banners/Rectangle 19218.jpg')) }}"
                         alt="{{ $destino['title_' . $locale] ?? $destino['title_es'] ?? '' }}"
                         class="absolute inset-0 w-full h-full object-cover object-center"
                         loading="lazy"
                         width="1280" height="560">

                    {{-- Overlay degradado teal: izquierda opaco → derecha transparente --}}
                    <div class="absolute inset-0"
                         style="background: linear-gradient(to right, rgba(10,35,38,0.92) 0%, rgba(10,35,38,0.75) 40%, rgba(10,35,38,0.25) 75%, transparent 100%);"></div>
                    {{-- Refuerzo extra en mobile (toda la superficie) --}}
                    <div class="absolute inset-0 md:hidden"
                         style="background: rgba(10,35,38,0.45);"></div>

                    {{-- Contenido sobre imagen --}}
                    <div class="relative z-10 h-full flex items-end lg:items-center px-6 md:px-8 lg:px-8 py-8 max-w-full">
                        <div>
                            {{-- Icono circular borde dorado --}}
                            <div class="w-12 h-12 lg:w-14 lg:h-14 rounded-full border-2 border-amber-400 bg-teal-900/60 grid place-items-center mb-3 shadow-md">
                                <svg class="w-6 h-6 lg:w-7 lg:h-7 text-amber-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $destino['iconPath'] }}"/>
                                </svg>
                            </div>

                            {{-- Badge --}}
                            <span class="inline-block text-[9px] font-bold uppercase tracking-[0.2em] text-amber-300 mb-2">
                                {{ $destino['badge_' . $locale] ?? $destino['badge_es'] ?? '' }}
                            </span>

                            {{-- Título --}}
                            <h3 class="font-display text-2xl lg:text-2xl text-white leading-tight">
                                {{ $destino['title_' . $locale] ?? $destino['title_es'] ?? '' }}
                            </h3>

                            {{-- Descripción: oculta en columnas desktop para no colapsar --}}
                            <p class="mt-2 text-sm text-white/80 leading-relaxed max-w-sm lg:hidden xl:block xl:max-w-[180px]">
                                {{ $destino['desc_' . $locale] ?? $destino['desc_es'] ?? '' }}
                            </p>

                            {{-- CTA outline dorado --}}
                            <a href="{{ route('tours.index', ['locale' => $locale]) }}"
                               class="mt-4 inline-flex items-center gap-2 border border-amber-400 text-amber-300 hover:bg-amber-400/10 rounded-full px-4 py-2 font-semibold text-xs uppercase tracking-wider transition">
                                VER TOURS
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Cierre: línea experiencias auténticas --}}
        <p class="mt-8 text-center text-sm text-teal-800/70 flex items-center justify-center gap-2 flex-wrap">
            <svg class="w-4 h-4 text-orange-500 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"/>
            </svg>
            {{ \App\Models\Setting::get('home_destinos_footer_' . $locale) ?: 'Experiencias auténticas, memorias inolvidables. Viaja con' }} <span class="text-amber-600 font-semibold ml-1">Lima View Tours.</span>
        </p>
    </div>
</section>


{{-- ============================================================
     SECCIÓN 4 — POR QUÉ ELEGIRNOS
     ============================================================ --}}
<section class="bg-cream-100 px-5 py-10 lg:py-20" aria-labelledby="why-title">
    <div class="container mx-auto max-w-7xl lg:px-5">

        {{-- Header centrado --}}
        <div class="text-center mb-8 max-w-lg mx-auto">
            <p class="text-[10px] uppercase tracking-[0.25em] text-orange-600 font-bold">{{ \App\Models\Setting::get('home_sec_why_eyebrow_' . $locale) ?: 'VIAJA CON CONFIANZA' }}</p>
            <h2 id="why-title" class="mt-3 font-display text-3xl lg:text-5xl text-teal-800 leading-tight">{!! \App\Models\Setting::get('home_sec_why_title_' . $locale) ?: '¿Por qué elegir<br>Lima View Tours?' !!}</h2>
            {!! $sep !!}
            <p class="mt-5 text-sm text-teal-800/70">{!! \App\Models\Setting::get('home_sec_why_subtitle_' . $locale) ?: 'Más que un tour, te ofrecemos<br>experiencias inolvidables.' !!}</p>
        </div>

        {{-- Lista de razones en card blanca --}}
        <div class="bg-white rounded-3xl shadow-sm ring-1 ring-teal-800/5 divide-y divide-cream-200/60 mb-5 max-w-2xl mx-auto lg:max-w-4xl">
            @php
                $defaultWhyItems = [
                    ['title_es' => 'Reserva segura',      'title_en' => 'Secure booking',       'title_pt' => 'Reserva segura',
                     'desc_es'  => 'Tus datos protegidos y transacciones 100% seguras.',
                     'desc_en'  => 'Your data protected and 100% secure transactions.',
                     'desc_pt'  => 'Seus dados protegidos e transações 100% seguras.',
                     'iconPath' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z'],
                    ['title_es' => 'Guías expertos',      'title_en' => 'Expert guides',         'title_pt' => 'Guias especializados',
                     'desc_es'  => 'Profesionales locales con amplia experiencia.',
                     'desc_en'  => 'Local professionals with extensive experience.',
                     'desc_pt'  => 'Profissionais locais com ampla experiência.',
                     'iconPath' => 'M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z'],
                    ['title_es' => 'Mejores experiencias','title_en' => 'Best experiences',      'title_pt' => 'Melhores experiências',
                     'desc_es'  => 'Tours seleccionados y diseñados para ti.',
                     'desc_en'  => 'Tours selected and designed for you.',
                     'desc_pt'  => 'Tours selecionados e criados para você.',
                     'iconPath' => 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0'],
                    ['title_es' => 'Soporte 24/7',        'title_en' => 'Support 24/7',          'title_pt' => 'Suporte 24/7',
                     'desc_es'  => 'Estamos para ayudarte antes, durante y después de tu viaje.',
                     'desc_en'  => 'We are here to help you before, during and after your trip.',
                     'desc_pt'  => 'Estamos aqui para ajudá-lo antes, durante e após sua viagem.',
                     'iconPath' => 'M2.25 6.75a4.5 4.5 0 004.5 4.5v8.25a3 3 0 003 3h6a3 3 0 003-3v-8.25a4.5 4.5 0 004.5-4.5h-21z'],
                    ['title_es' => 'Turismo responsable', 'title_en' => 'Responsible tourism',   'title_pt' => 'Turismo responsável',
                     'desc_es'  => 'Cuidamos nuestro Perú y apoyamos a las comunidades locales.',
                     'desc_en'  => 'We care for our Peru and support local communities.',
                     'desc_pt'  => 'Cuidamos do nosso Peru e apoiamos as comunidades locais.',
                     'iconPath' => 'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418'],
                ];
                $rawWhyItems = \App\Models\Setting::get('home_why_items');
                $whyItems = (is_array($rawWhyItems) && count($rawWhyItems)) ? $rawWhyItems : $defaultWhyItems;
            @endphp

            @foreach ($whyItems as $wItem)
            @php
                $wTitle = $wItem['title_' . $locale] ?? $wItem['title_es'] ?? '';
                $wDesc  = $wItem['desc_' . $locale]  ?? $wItem['desc_es']  ?? '';
                $wPath  = $wItem['iconPath'] ?? '';
            @endphp
                <div class="flex items-center gap-4 px-5 py-4 lg:py-5">
                    <span class="w-12 h-12 lg:w-14 lg:h-14 rounded-full bg-teal-800 grid place-items-center shrink-0">
                        <svg class="w-6 h-6 text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $wPath }}"/>
                        </svg>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block font-bold text-teal-800 text-[15px] lg:text-base leading-tight">{{ $wTitle }}</span>
                        <span class="block text-xs lg:text-sm text-teal-800/60 leading-snug mt-1">{{ $wDesc }}</span>
                    </span>
                    <svg class="w-5 h-5 text-orange-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </div>
            @endforeach
        </div>

        {{-- Trust banner --}}
        <div class="relative bg-cream-200/70 rounded-2xl px-5 py-4 flex items-center gap-4 overflow-hidden max-w-2xl mx-auto lg:max-w-4xl">
            <span class="w-12 h-12 rounded-full bg-cream-100 grid place-items-center shrink-0 relative z-10">
                <svg class="w-6 h-6 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
            </span>
            <p class="flex-1 text-sm text-teal-800 leading-tight relative z-10">
                {!! \App\Models\Setting::get('home_trust_banner_' . $locale) ?: 'Reserva fácil, segura y<br><strong class="font-bold text-orange-500">100% garantizada</strong>' !!}
            </p>
            <svg class="absolute right-0 top-0 h-full w-1/2 text-orange-400/40 pointer-events-none" viewBox="0 0 120 80" fill="none" stroke="currentColor" stroke-width="1" preserveAspectRatio="xMaxYMid slice" aria-hidden="true">
                <path d="M0 60 L20 45 L35 55 L50 30 L70 50 L85 25 L100 45 L120 35 L120 80 L0 80 Z"/>
            </svg>
        </div>
    </div>
</section>


{{-- ============================================================
     SECCIÓN 5 — ¿QUÉ TIPO DE TOUR ESTÁS BUSCANDO?
     ============================================================ --}}
<section class="bg-white px-5 py-10 lg:py-20"
         x-data="{ tab: 'cult' }"
         aria-labelledby="tour-type-title">
    <div class="container mx-auto max-w-7xl lg:px-5">

        {{-- Header --}}
        <div class="text-center mb-8">
            {!! $brujula !!}
            <p class="mt-2 text-[10px] uppercase tracking-[0.25em] text-orange-600 font-bold">{{ \App\Models\Setting::get('home_sec_types_eyebrow_' . $locale) ?: 'CATEGORÍAS' }}</p>
            <h2 id="tour-type-title" class="mt-2 font-display text-3xl lg:text-5xl text-teal-800 leading-tight">
                {!! \App\Models\Setting::get('home_sec_types_title_' . $locale) ?: '¿Qué tipo de tour <br class="sm:hidden">estás buscando?' !!}
            </h2>
            {!! $sep !!}
        </div>

        @php
            $defaultTourTypeTabs = [
                ['id'=>'cult','label_es'=>'TOURS CULTURALES','label_en'=>'CULTURAL TOURS','label_pt'=>'TOURS CULTURAIS',
                 'title_es'=>'Tours Culturales','title_en'=>'Cultural Tours','title_pt'=>'Tours Culturais',
                 'img'=>'Rectangle 19215.jpg','eyebrow_es'=>'CUPOS LIMITADOS','eyebrow_en'=>'LIMITED SPOTS','eyebrow_pt'=>'VAGAS LIMITADAS',
                 'desc_es'=>'Descubre el legado milenario de los incas con visitas guiadas a Machu Picchu, Sacsayhuamán, museos en Lima y centros históricos.',
                 'desc_en'=>'Discover the millenary legacy of the Incas with guided visits to Machu Picchu, Sacsayhuamán, museums in Lima and historic centers.',
                 'desc_pt'=>'Descubra o legado milenar dos incas com visitas guiadas a Machu Picchu, Sacsayhuamán, museus em Lima e centros históricos.',
                 'price'=>200,
                 'iconPath'=>'M3 21h18M5 21V10.5L12 6l7 4.5V21M9 21v-6h6v6'],
                ['id'=>'adv','label_es'=>'TOURS DE AVENTURA','label_en'=>'ADVENTURE TOURS','label_pt'=>'TOURS DE AVENTURA',
                 'title_es'=>'Tours de Aventura','title_en'=>'Adventure Tours','title_pt'=>'Tours de Aventura',
                 'img'=>'Rectangle 19219.jpg','eyebrow_es'=>'CUPOS LIMITADOS','eyebrow_en'=>'LIMITED SPOTS','eyebrow_pt'=>'VAGAS LIMITADAS',
                 'desc_es'=>'Sandboarding en Huacachina, trekking a Machu Picchu, tirolesa en el Valle Sagrado y kayak en las Islas Ballestas.',
                 'desc_en'=>'Sandboarding in Huacachina, trekking to Machu Picchu, zip line in the Sacred Valley and kayaking at the Ballestas Islands.',
                 'desc_pt'=>'Sandboarding em Huacachina, trekking a Machu Picchu, tirolesa no Vale Sagrado e caiaque nas Ilhas Ballestas.',
                 'price'=>250,
                 'iconPath'=>'M2.25 17.25L8 11l4 4 4-7 5.75 9.25'],
                ['id'=>'cul','label_es'=>'EXPERIENCIAS CULINARIAS','label_en'=>'CULINARY EXPERIENCES','label_pt'=>'EXPERIÊNCIAS CULINÁRIAS',
                 'title_es'=>'Experiencias Culinarias','title_en'=>'Culinary Experiences','title_pt'=>'Experiências Culinárias',
                 'img'=>'Rectangle 19211.jpg','eyebrow_es'=>'TOUR DEGUSTACIÓN','eyebrow_en'=>'TASTING TOUR','eyebrow_pt'=>'TOUR DEGUSTAÇÃO',
                 'desc_es'=>'Recorrido por la gastronomía peruana premiada mundialmente: ceviche en Barranco, pisco sour en bares históricos.',
                 'desc_en'=>'A tour of Peru\'s world-award-winning gastronomy: ceviche in Barranco, pisco sour at historic bars.',
                 'desc_pt'=>'Um roteiro pela gastronomia peruana premiada mundialmente: ceviche em Barranco, pisco sour em bares históricos.',
                 'price'=>180,
                 'iconPath'=>'M3 11h18M7 11V5m10 6V5M5 11v10m14-10v10M9 18h6'],
                ['id'=>'oth','label_es'=>'OTROS','label_en'=>'OTHERS','label_pt'=>'OUTROS',
                 'title_es'=>'Otras experiencias','title_en'=>'Other experiences','title_pt'=>'Otras experiências',
                 'img'=>'Rectangle 19216.jpg','eyebrow_es'=>'NUEVO','eyebrow_en'=>'NEW','eyebrow_pt'=>'NOVO',
                 'desc_es'=>'Tours fotográficos, observación de aves, retiros wellness en el Valle Sagrado y experiencias místicas.',
                 'desc_en'=>'Photography tours, birdwatching, wellness retreats in the Sacred Valley and mystical experiences.',
                 'desc_pt'=>'Tours fotográficos, observação de aves, retiros wellness no Vale Sagrado e experiências místicas.',
                 'price'=>220,
                 'iconPath'=>'M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z'],
            ];
            $rawTourTypeTabs = \App\Models\Setting::get('home_tour_type_tabs');
            $tourTypeTabs = (is_array($rawTourTypeTabs) && count($rawTourTypeTabs)) ? $rawTourTypeTabs : $defaultTourTypeTabs;
        @endphp

        {{-- Tabs grid 2×2 --}}
        <div role="tablist" class="grid grid-cols-2 gap-2.5 mb-6 max-w-2xl mx-auto lg:max-w-4xl lg:grid-cols-4">
            @foreach ($tourTypeTabs as $idx => $tab)
            @php
                $id       = $tab['id'] ?? 'tab' . $idx;
                $label    = $tab['label_' . $locale] ?? $tab['label_es'] ?? '';
                $iconPath = $tab['iconPath'] ?? '';
            @endphp
                <button type="button"
                        role="tab"
                        id="tab-{{ $id }}"
                        aria-controls="panel-{{ $id }}"
                        :aria-selected="tab === '{{ $id }}' ? 'true' : 'false'"
                        @click="tab = '{{ $id }}'"
                        class="rounded-2xl px-3 py-3.5 flex items-center gap-2 text-[11px] font-bold uppercase tracking-tight transition border-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-500"
                        :class="tab === '{{ $id }}'
                            ? 'bg-orange-500 text-white border-orange-500 shadow-sm'
                            : 'bg-white text-teal-800 border-teal-800/15 hover:border-teal-800/30'">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}"/>
                    </svg>
                    <span class="text-left leading-tight">{{ $label }}</span>
                </button>
            @endforeach
        </div>

        {{-- Paneles de contenido --}}
        @foreach ($tourTypeTabs as $ttIdx => $tab)
        @php
            $id       = $tab['id'] ?? 'tab0';
            $label    = $tab['label_' . $locale] ?? $tab['label_es'] ?? '';
            $tTitle   = $tab['title_' . $locale] ?? $tab['title_es'] ?? '';
            $tImg     = $tab['img'] ?? '';
            $tEyebrow = $tab['eyebrow_' . $locale] ?? $tab['eyebrow_es'] ?? '';
            $tDesc    = $tab['desc_' . $locale] ?? $tab['desc_es'] ?? '';
            $tPrice   = $tab['price'] ?? 0;
            $iconPath = $tab['iconPath'] ?? '';
        @endphp
            <article role="tabpanel"
                     id="panel-{{ $id }}"
                     aria-labelledby="tab-{{ $id }}"
                     x-show="tab === '{{ $id }}'"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     class="bg-white rounded-3xl overflow-hidden shadow-sm ring-1 ring-teal-800/5 max-w-2xl mx-auto lg:max-w-4xl lg:grid lg:grid-cols-2">

                {{-- Imagen --}}
                <div class="relative">
                    <img src="{{ \App\Support\ImagePath::homeImage(\App\Models\Setting::get('home_tourtype_img_' . ($ttIdx + 1))) ?: (\App\Support\ImagePath::homeImage($tImg) ?: asset('assets/banners/Rectangle 19215.jpg')) }}"
                         alt="{{ $tTitle }}"
                         class="w-full h-48 lg:h-full object-cover" loading="lazy">
                    <span class="absolute top-4 left-4 inline-flex items-center gap-2 bg-teal-800 text-white text-[10px] font-bold uppercase tracking-wider px-3 py-1.5 rounded-full">
                        <svg class="w-3 h-3 text-orange-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        {{ $tEyebrow }}
                    </span>
                </div>

                {{-- Texto --}}
                <div class="p-5 lg:p-7">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="w-10 h-10 rounded-full bg-teal-800 grid place-items-center shrink-0">
                            <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}"/>
                            </svg>
                        </span>
                        <h3 class="font-display text-2xl text-teal-800 leading-tight">{{ $tTitle }}</h3>
                    </div>
                    <p class="mt-2 text-[11px] uppercase tracking-[0.15em] text-orange-600 font-bold">{{ $tEyebrow }}</p>
                    <p class="mt-3 text-sm text-teal-800/75 leading-relaxed">{{ $tDesc }}</p>
                    <hr class="my-5 border-cream-200/80">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-[0.15em] text-teal-800/60 font-semibold">{{ __('ui.from') }}</p>
                            <p class="font-price text-2xl text-teal-800 font-semibold leading-none">US${{ $tPrice }}</p>
                            <p class="text-[10px] uppercase tracking-[0.15em] text-teal-800/60 font-semibold">{{ __('ui.per_person') }}</p>
                        </div>
                        <a href="{{ route('tours.index', ['locale' => $locale]) }}"
                           class="inline-flex items-center gap-2 bg-teal-800 hover:bg-teal-900 text-white rounded-full px-5 py-3 font-semibold text-xs uppercase tracking-wider transition shrink-0">
                            RESERVAR TOUR
                            <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                        </a>
                    </div>
                </div>
            </article>
        @endforeach

        {{-- Footer 4 features --}}
        @php
            $defaultFooterFeatures = [
                ['label_es' => 'Reserva fácil',        'label_en' => 'Easy booking',         'label_pt' => 'Reserva fácil',        'iconPath' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z'],
                ['label_es' => 'Soporte 24/7',         'label_en' => 'Support 24/7',          'label_pt' => 'Suporte 24/7',         'iconPath' => 'M2.25 6.75a4.5 4.5 0 004.5 4.5v8.25a3 3 0 003 3h6a3 3 0 003-3v-8.25a4.5 4.5 0 004.5-4.5h-21z'],
                ['label_es' => 'Cancelación gratuita', 'label_en' => 'Free cancellation',     'label_pt' => 'Cancelamento grátis', 'iconPath' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5'],
                ['label_es' => 'Mejores experiencias', 'label_en' => 'Best experiences',      'label_pt' => 'Melhores experiências','iconPath' => 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497'],
            ];
            $rawFooterFeatures = \App\Models\Setting::get('home_footer_features');
            $footerFeatures = (is_array($rawFooterFeatures) && count($rawFooterFeatures)) ? $rawFooterFeatures : $defaultFooterFeatures;
        @endphp
        <div class="mt-8 grid grid-cols-4 gap-2 text-center max-w-2xl mx-auto lg:max-w-4xl">
            @foreach ($footerFeatures as $feat)
            @php
                $fLabel = $feat['label_' . $locale] ?? $feat['label_es'] ?? '';
                $fPath  = $feat['iconPath'] ?? '';
            @endphp
                <div class="flex flex-col items-center gap-1.5">
                    <svg class="w-6 h-6 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $fPath }}"/></svg>
                    <p class="text-[9px] font-bold uppercase tracking-tight text-teal-800 leading-tight">{{ $fLabel }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>


{{-- ============================================================
     SECCIÓN 6 — DESCUBRE EXPERIENCIAS ÚNICAS (Carrusel)
     ============================================================ --}}
<section class="bg-cream-100 px-5 py-10 lg:py-20" aria-labelledby="exp-title">
    <div class="container mx-auto max-w-7xl lg:px-5">

        {{-- Header --}}
        <div class="text-center mb-8">
            {!! $brujula !!}
            <p class="mt-2 text-[10px] uppercase tracking-[0.25em] text-orange-600 font-bold">{{ \App\Models\Setting::get('home_sec_exp_eyebrow_' . $locale) ?: 'EXPERIENCIAS' }}</p>
            <h2 id="exp-title" class="mt-2 font-display text-3xl lg:text-5xl text-teal-800 leading-tight">
                {!! \App\Models\Setting::get('home_sec_exp_title_' . $locale) ?: 'Descubre <br class="sm:hidden">experiencias únicas' !!}
            </h2>
            {!! $sep !!}
        </div>

        {{-- Carrusel Alpine + scroll-snap --}}
        @php
            $defaultExpTours = [
                ['img'=>'Rectangle 19216.jpg','title_es'=>'Excursión de día completo en Lima','title_en'=>'Full-day excursion in Lima','title_pt'=>'Excursão de dia inteiro em Lima','badge_es'=>'MÁS VENDIDO','badge_en'=>'BEST SELLER','badge_pt'=>'MAIS VENDIDO','badgeBg'=>'teal-800','slug'=>'excursion-dia-completo-lima'],
                ['img'=>'Rectangle 19218.jpg','title_es'=>'Misterios del Imperio Inca en Cusco','title_en'=>'Mysteries of the Inca Empire in Cusco','title_pt'=>'Mistérios do Império Inca em Cusco','badge_es'=>'MÁS VENDIDO','badge_en'=>'BEST SELLER','badge_pt'=>'MAIS VENDIDO','badgeBg'=>'teal-800','slug'=>'misterios-imperio-inca-cusco'],
                ['img'=>'Rectangle 19219.jpg','title_es'=>'Oasis de Huacachina y Líneas de Nazca','title_en'=>'Huacachina Oasis and Nazca Lines','title_pt'=>'Oásis de Huacachina e Linhas de Nazca','badge_es'=>'DESTACADO','badge_en'=>'FEATURED','badge_pt'=>'DESTAQUE','badgeBg'=>'orange-500','slug'=>'oasis-huacachina-lineas-nazca'],
                ['img'=>'Rectangle 19215.jpg','title_es'=>'Tour Cultural Lima Colonial y Moderna','title_en'=>'Colonial and Modern Lima Cultural Tour','title_pt'=>'Tour Cultural Lima Colonial e Moderna','badge_es'=>'MÁS VENDIDO','badge_en'=>'BEST SELLER','badge_pt'=>'MAIS VENDIDO','badgeBg'=>'teal-800','slug'=>'tour-cultural-lima-colonial-moderna'],
            ];
            $rawExpTours = \App\Models\Setting::get('home_exp_tours');
            $expTours = (is_array($rawExpTours) && count($rawExpTours)) ? $rawExpTours : $defaultExpTours;
        @endphp

        <div x-data="{ current: 0, total: {{ count($expTours) }} }" class="relative">

            {{-- Carrusel scroll-snap --}}
            <div class="carousel-track gap-4 pb-4 -mx-5 px-4 sm:mx-0"
                 style="padding-left:1rem;padding-right:1rem;"
                 id="exp-carousel"
                 @scroll.debounce.100ms="
                     let el = $el;
                     let w = el.scrollWidth - el.clientWidth;
                     if (w > 0) {
                         current = Math.round((el.scrollLeft / w) * (total - 1));
                     }
                 ">

                @foreach ($expTours as $i => $expItem)
                @php
                    $eImg      = $expItem['img'] ?? '';
                    $eTitle    = $expItem['title_' . $locale] ?? $expItem['title_es'] ?? '';
                    $eBadge    = $expItem['badge_' . $locale] ?? $expItem['badge_es'] ?? '';
                    $eBadgeBg  = $expItem['badgeBg'] ?? 'teal-800';
                    $eSlug     = $expItem['slug'] ?? '';
                @endphp
                    @php
                        $eUrl = route('tours.index', ['locale' => $locale]);
                        $eBadgeIsOrange = $eBadgeBg === 'orange-500';
                    @endphp
                    <a href="{{ $eUrl }}"
                       class="tour-slide-4
                              block group rounded-2xl overflow-hidden shadow-sm ring-1 ring-teal-800/5
                              bg-white hover:shadow-md transition-shadow">
                        <div class="relative aspect-[4/3] overflow-hidden">
                            <img src="{{ \App\Support\ImagePath::homeImage(\App\Models\Setting::get('home_exp_img_' . ($i + 1))) ?: (\App\Support\ImagePath::homeImage($eImg) ?: asset('assets/banners/Rectangle 19216.jpg')) }}"
                                 alt="{{ $eTitle }}"
                                 class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                 loading="lazy">
                            <span class="absolute top-3 left-3 inline-flex items-center gap-1.5 text-white text-[10px] font-bold uppercase tracking-wider px-3 py-1.5 rounded-full
                                         {{ $eBadgeIsOrange ? 'bg-orange-500' : 'bg-teal-800' }}">
                                <svg class="w-3 h-3 text-yellow-400 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                {{ $eBadge }}
                            </span>
                        </div>
                        <div class="p-4">
                            <h3 class="font-display text-lg text-teal-800 leading-tight line-clamp-2 group-hover:text-orange-500 transition-colors">{{ $eTitle }}</h3>
                            <div class="mt-3 grid grid-cols-3 divide-x divide-cream-200/80 text-center text-[11px] text-teal-800/75">
                                <div class="px-2">
                                    <svg class="w-4 h-4 mx-auto mb-1 text-teal-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                                    <span class="leading-tight">Español/<br>Inglés</span>
                                </div>
                                <div class="px-2">
                                    <svg class="w-4 h-4 mx-auto mb-1 text-teal-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
                                    <span class="leading-tight">Full Day</span>
                                </div>
                                <div class="px-2">
                                    <svg class="w-4 h-4 mx-auto mb-1 text-teal-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0z"/></svg>
                                    <span class="leading-tight">Tour<br>Grupal</span>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Dots paginación (solo mobile/tablet) --}}
            <div class="mt-5 flex items-center justify-center gap-2 lg:hidden" aria-hidden="true">
                @foreach ($expTours as $i => $__)
                    <button type="button"
                            @click="
                                current = {{ $i }};
                                document.getElementById('exp-carousel').scrollTo({
                                    left: document.getElementById('exp-carousel').scrollWidth / {{ count($expTours) }} * {{ $i }},
                                    behavior: 'smooth'
                                });
                            "
                            class="transition-all duration-200 rounded-full"
                            :class="current === {{ $i }} ? 'h-2 w-6 bg-orange-500' : 'h-2 w-2 bg-cream-300'"
                            :aria-label="'Ir a experiencia {{ $i + 1 }}'">
                    </button>
                @endforeach
            </div>
        </div>
    </div>
</section>


{{-- ============================================================
     SECCIÓN 6B — OPINIONES DE VIAJEROS (Testimonios)
     ============================================================ --}}
@php
    $reviews = ($testimonials ?? collect());
    if ($reviews->isEmpty()) {
        $reviews = collect([
            (object)['name' => 'María Fernanda G.', 'country' => 'México',         'rating' => 5, 'source' => 'Tripadvisor', 'quote' => 'Una experiencia impecable de principio a fin. El guía fue excelente y todo estuvo perfectamente organizado. ¡Volvería sin dudarlo!', 'avatar' => null],
            (object)['name' => 'James K.',          'country' => 'Estados Unidos', 'rating' => 5, 'source' => 'Google',      'quote' => 'Best tour experience in Peru. Punctual pickup, knowledgeable guide and breathtaking views. Highly recommended.', 'avatar' => null],
            (object)['name' => 'Carla R.',          'country' => 'Perú',           'rating' => 5, 'source' => 'Viator',      'quote' => 'Atención de primera, premium en todo sentido. Huacachina y las Islas Ballestas fueron mágicas. Gracias Lima View Tours.', 'avatar' => null],
            (object)['name' => 'Sofia M.',          'country' => 'Brasil',         'rating' => 5, 'source' => 'GetYourGuide', 'quote' => 'Tudo perfeito! Organização excelente e guia muito atencioso. Recomendo demais para quem visita o Peru.', 'avatar' => null],
        ]);
    }
    $reviewCount = $reviews->count();
@endphp
<section id="opiniones" class="bg-white px-5 py-10 lg:py-20 scroll-mt-24" aria-labelledby="opiniones-title"
         x-data="{ reviewModalOpen: false, review: {} }">
    <div class="container mx-auto max-w-7xl lg:px-5">

        {{-- Header --}}
        <div class="text-center mb-8">
            <p class="inline-flex items-center gap-3 text-[10px] uppercase tracking-[0.25em] text-orange-600 font-bold">
                <span class="h-px w-6 bg-orange-500/50"></span>{{ \App\Models\Setting::get('home_sec_reviews_eyebrow_' . $locale) ?: 'OPINIONES REALES' }}<span class="h-px w-6 bg-orange-500/50"></span>
            </p>
            <h2 id="opiniones-title" class="mt-3 font-display text-3xl lg:text-5xl text-teal-800 leading-tight">
                {{ \App\Models\Setting::get('home_sec_reviews_title_' . $locale) ?: 'Lo que dicen nuestros viajeros' }}
            </h2>
            {!! $sep !!}
            <p class="mt-5 text-sm text-teal-800/70">
                {!! \App\Models\Setting::get('home_sec_reviews_subtitle_' . $locale) ?: 'Miles de viajeros han vivido el Perú con nosotros.<br class="hidden sm:block">Estas son algunas de sus experiencias.' !!}
            </p>
        </div>

        {{-- Carrusel mobile / grid desktop --}}
        <div x-data="{ current: 0, total: {{ $reviewCount }} }" class="relative">
            <div class="carousel-track gap-4 pb-4"
                 id="reviews-carousel"
                 @scroll.debounce.100ms="
                     let el = $el; let w = el.scrollWidth - el.clientWidth;
                     if (w > 0) { current = Math.round((el.scrollLeft / w) * (total - 1)); }
                 ">
                @foreach ($reviews as $rev)
                    @php
                        $rvName    = is_object($rev) ? ($rev->name ?? 'Viajero') : ($rev['name'] ?? 'Viajero');
                        $rvCountry = is_object($rev) ? ($rev->country ?? null) : ($rev['country'] ?? null);
                        $rvRating  = max(0, min(5, (int) round(is_object($rev) ? ($rev->rating ?? 5) : ($rev['rating'] ?? 5))));
                        $rvSource  = is_object($rev) ? ($rev->source ?? null) : ($rev['source'] ?? null);
                        $rvQuote   = is_object($rev) ? ($rev->quote ?? '') : ($rev['quote'] ?? '');
                        $rvAvatar  = is_object($rev) ? ($rev->avatar ?? null) : ($rev['avatar'] ?? null);
                        $rvAvatarUrl = $rvAvatar ? (\Illuminate\Support\Str::startsWith($rvAvatar, ['http', '/']) ? $rvAvatar : asset($rvAvatar)) : null;
                        $rvInitial = mb_strtoupper(mb_substr(trim($rvName), 0, 1));
                        $rvMeta = trim(implode(' · ', array_filter([$rvCountry, $rvSource])));
                        $rvQuoteFull   = trim((string) $rvQuote);
                        $rvQuoteShort  = \Illuminate\Support\Str::limit($rvQuoteFull, 80);
                        $rvQuoteIsLong = mb_strlen($rvQuoteFull) > 80;
                    @endphp
                    <figure class="review-card tour-slide-4 snap-start
                                   bg-cream-100 rounded-3xl ring-1 ring-teal-800/5 shadow-sm p-6 flex flex-col">
                        {{-- Comillas + estrellas --}}
                        <div class="flex items-center justify-between mb-3">
                            <svg class="w-9 h-9 text-orange-400/40" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7.17 6A5.17 5.17 0 002 11.17V18h6.83v-6.83H5.5A1.67 1.67 0 017.17 9.5V6zm9 0A5.17 5.17 0 0011 11.17V18h6.83v-6.83H14.5a1.67 1.67 0 011.67-1.67V6z"/></svg>
                            <span class="text-orange-400 text-base leading-none tracking-tight" aria-label="{{ $rvRating }} de 5 estrellas">{{ str_repeat('★', $rvRating) }}{{ str_repeat('☆', 5 - $rvRating) }}</span>
                        </div>
                        {{-- Texto: recortado a 40 caracteres; "Ver más" abre el popup con el comentario completo --}}
                        <blockquote class="text-sm text-teal-800/85 leading-relaxed">“{{ $rvQuoteShort }}”</blockquote>
                        @if ($rvQuoteIsLong)
                            <button type="button"
                                    class="mt-2 self-start text-xs font-bold text-orange-600 hover:text-orange-700 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-400"
                                    @click="review = { name: @js($rvName), meta: @js($rvMeta), quote: @js($rvQuoteFull), rating: {{ $rvRating }}, avatar: @js($rvAvatarUrl), initial: @js($rvInitial) }; reviewModalOpen = true">
                                {{ __('ui.see_more') }}
                            </button>
                        @endif
                        <div class="flex-1"></div>
                        {{-- Autor --}}
                        <figcaption class="mt-5 pt-4 border-t border-teal-800/10 flex items-center gap-3">
                            @if ($rvAvatarUrl)
                                <img src="{{ $rvAvatarUrl }}" alt="{{ $rvName }}" class="w-11 h-11 rounded-full object-cover shrink-0" loading="lazy" width="44" height="44">
                            @else
                                <span class="w-11 h-11 rounded-full bg-teal-800 text-white grid place-items-center font-display text-lg shrink-0" aria-hidden="true">{{ $rvInitial }}</span>
                            @endif
                            <div class="min-w-0">
                                <p class="font-bold text-teal-800 text-sm leading-tight truncate">{{ $rvName }}</p>
                                <p class="text-[11px] text-teal-800/55 leading-tight truncate">{{ $rvMeta }}</p>
                            </div>
                            <svg class="w-5 h-5 text-emerald-500 ml-auto shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" title="Reseña verificada"><path fill-rule="evenodd" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" clip-rule="evenodd"/></svg>
                        </figcaption>
                    </figure>
                @endforeach
            </div>

            {{-- Dots (mobile/tablet) --}}
            <div class="mt-5 flex items-center justify-center gap-2 lg:hidden" aria-hidden="true">
                @for ($i = 0; $i < $reviewCount; $i++)
                    <button type="button"
                            @click="current = {{ $i }}; document.getElementById('reviews-carousel').scrollTo({ left: document.getElementById('reviews-carousel').scrollWidth / {{ $reviewCount }} * {{ $i }}, behavior: 'smooth' });"
                            class="transition-all duration-200 rounded-full"
                            :class="current === {{ $i }} ? 'h-2 w-6 bg-orange-500' : 'h-2 w-2 bg-cream-300'"
                            :aria-label="'Ir a opinión {{ $i + 1 }}'"></button>
                @endfor
            </div>
        </div>

        {{-- CTA: ver todas las reseñas reales en Google (editable en Configuración → Redes sociales) --}}
        @php
            $googleReviewsUrl = \App\Models\Setting::get('social_google_reviews')
                ?: 'https://www.google.com/maps/place/Lima+view+tours/@-12.0457338,-77.0281479,17z/data=!3m1!4b1!4m6!3m5!1s0x9105c90e82ab0695:0xad0c3b21492d1a96!8m2!3d-12.0457338!4d-77.0281479!16s%2Fg%2F11w7s4b74q!18m1!1e1';
        @endphp
        <div class="mt-8 text-center">
            <a href="{{ $googleReviewsUrl }}" target="_blank" rel="noopener nofollow"
               class="inline-flex items-center gap-2 rounded-full bg-white ring-1 ring-teal-800/15 px-6 py-3 text-sm font-bold text-teal-800 shadow-sm hover:ring-teal-800/30 hover:shadow transition">
                <svg class="w-5 h-5" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1Z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.15-4.53H2.18v2.84A11 11 0 0 0 12 23Z"/><path fill="#FBBC05" d="M5.85 14.1a6.6 6.6 0 0 1 0-4.2V7.06H2.18a11 11 0 0 0 0 9.88l3.67-2.84Z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1A11 11 0 0 0 2.18 7.06l3.67 2.84C6.71 7.3 9.14 5.38 12 5.38Z"/></svg>
                {{ __('ui.see_all_google_reviews') }}
                <span aria-hidden="true">→</span>
            </a>
        </div>

        {{-- Popup: comentario completo (se abre desde "Ver más") --}}
        <div x-cloak
             x-show="reviewModalOpen"
             x-transition.opacity
             @keydown.escape.window="reviewModalOpen = false"
             class="fixed inset-0 z-[70] flex items-center justify-center p-4"
             role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-teal-950/60 backdrop-blur-sm" @click="reviewModalOpen = false"></div>
            <div x-show="reviewModalOpen"
                 x-transition.scale.origin.center
                 class="relative z-10 w-full max-w-md bg-cream-100 rounded-3xl shadow-2xl p-6 max-h-[85vh] overflow-y-auto">
                <button type="button" @click="reviewModalOpen = false"
                        class="absolute top-4 right-4 w-8 h-8 grid place-items-center rounded-full bg-teal-800/10 text-teal-800 hover:bg-teal-800/20"
                        aria-label="Cerrar">✕</button>
                <div class="text-orange-400 text-lg leading-none mb-3"
                     x-text="'★'.repeat(review.rating || 5) + '☆'.repeat(5 - (review.rating || 5))"></div>
                <blockquote class="text-sm text-teal-800/90 leading-relaxed whitespace-pre-line"
                            x-text="'“' + (review.quote || '') + '”'"></blockquote>
                <div class="mt-5 pt-4 border-t border-teal-800/10 flex items-center gap-3">
                    <template x-if="review.avatar">
                        <img :src="review.avatar" :alt="review.name" class="w-11 h-11 rounded-full object-cover shrink-0">
                    </template>
                    <template x-if="!review.avatar">
                        <span class="w-11 h-11 rounded-full bg-teal-800 text-white grid place-items-center font-display text-lg shrink-0"
                              x-text="review.initial"></span>
                    </template>
                    <div class="min-w-0">
                        <p class="font-bold text-teal-800 text-sm leading-tight" x-text="review.name"></p>
                        <p class="text-[11px] text-teal-800/55 leading-tight" x-text="review.meta"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


{{-- ============================================================
     SECCIÓN 7 — PREGUNTAS FRECUENTES (FAQ)
     ============================================================ --}}
<section class="bg-cream-100 px-5 py-10 lg:py-20"
         x-data="{ open: null }"
         aria-labelledby="faq-title">
    <div class="container mx-auto max-w-7xl lg:px-5">

        {{-- Header --}}
        <div class="text-center mb-8">
            {!! $brujula !!}
            <h2 id="faq-title" class="mt-2 font-display text-3xl lg:text-5xl text-teal-800 leading-tight">
                {{ \App\Models\Setting::get('home_sec_faq_title_' . $locale) ?: 'Preguntas frecuentes' }}
            </h2>
            {!! $sep !!}
            <p class="mt-5 text-sm text-teal-800/70">
                {!! \App\Models\Setting::get('home_sec_faq_subtitle_' . $locale) ?: 'Resolvemos las dudas más comunes<br>antes de vivir tu experiencia en Perú.' !!}
            </p>
        </div>

        @php
            $defaultHomeFaqs = [
                ['q_es'=>'¿Qué tipo de tours ofrecen?','q_en'=>'What types of tours do you offer?','q_pt'=>'Que tipos de tours vocês oferecem?',
                 'a_es'=>'Ofrecemos tours culturales, de aventura, gastronómicos y experiencias únicas en Lima, Ica y Cusco.',
                 'a_en'=>'We offer cultural, adventure, gastronomic tours and unique experiences in Lima, Ica and Cusco.',
                 'a_pt'=>'Oferecemos tours culturais, de aventura, gastronômicos e experiências únicas em Lima, Ica e Cusco.',
                 'iconPath'=>'M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c-.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z'],
                ['q_es'=>'¿Los tours son aptos para todos los viajeros?','q_en'=>'Are the tours suitable for all travelers?','q_pt'=>'Os tours são adequados para todos os viajantes?',
                 'a_es'=>'La mayoría de nuestros tours son aptos para todas las edades y niveles físicos. Consúltanos para detalles.',
                 'a_en'=>'Most of our tours are suitable for all ages and fitness levels. Ask us for details.',
                 'a_pt'=>'A maioria de nossos tours é adequada para todas as idades e níveis físicos. Consulte-nos para detalhes.',
                 'iconPath'=>'M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486'],
                ['q_es'=>'¿Incluyen recojo desde el hotel?','q_en'=>'Do you offer hotel pickup?','q_pt'=>'Vocês oferecem traslado do hotel?',
                 'a_es'=>'Sí, todos nuestros tours en Lima, Ica y Cusco incluyen recojo y retorno a tu hotel sin costo adicional.',
                 'a_en'=>'Yes, all our tours in Lima, Ica and Cusco include hotel pickup and drop-off at no extra cost.',
                 'a_pt'=>'Sim, todos os nossos tours em Lima, Ica e Cusco incluem busca e retorno ao hotel sem custo adicional.',
                 'iconPath'=>'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12'],
                ['q_es'=>'¿Cómo puedo reservar un tour?','q_en'=>'How can I book a tour?','q_pt'=>'Como posso reservar um tour?',
                 'a_es'=>'Puedes reservar online seleccionando tu tour, fecha y número de pasajeros. Te enviaremos la confirmación al instante.',
                 'a_en'=>'You can book online by selecting your tour, date and number of passengers. We\'ll send you instant confirmation.',
                 'a_pt'=>'Você pode reservar online selecionando seu tour, data e número de passageiros. Enviaremos a confirmação instantaneamente.',
                 'iconPath'=>'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z'],
                ['q_es'=>'¿Hay opciones para familias o grupos?','q_en'=>'Are there options for families or groups?','q_pt'=>'Há opções para famílias ou grupos?',
                 'a_es'=>'Sí, ofrecemos tarifas especiales y tours privados diseñados para familias y grupos de cualquier tamaño.',
                 'a_en'=>'Yes, we offer special rates and private tours designed for families and groups of any size.',
                 'a_pt'=>'Sim, oferecemos tarifas especiais e tours privados criados para famílias e grupos de qualquer tamanho.',
                 'iconPath'=>'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0z'],
                ['q_es'=>'¿Es seguro viajar con Lima View Tours?','q_en'=>'Is it safe to travel with Lima View Tours?','q_pt'=>'É seguro viajar com a Lima View Tours?',
                 'a_es'=>'Contamos con +11 años de experiencia, guías certificados, vehículos asegurados y protocolos de seguridad.',
                 'a_en'=>'We have 11+ years of experience, certified guides, insured vehicles and safety protocols.',
                 'a_pt'=>'Temos +11 anos de experiência, guias certificados, veículos segurados e protocolos de segurança.',
                 'iconPath'=>'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z'],
            ];
            $rawHomeFaqs = \App\Models\Setting::get('home_faqs');
            $faqs = (is_array($rawHomeFaqs) && count($rawHomeFaqs)) ? $rawHomeFaqs : $defaultHomeFaqs;
        @endphp

        {{-- Accordion --}}
        <div class="space-y-3 mb-6 max-w-2xl mx-auto lg:max-w-4xl lg:grid lg:grid-cols-2 lg:gap-4 lg:space-y-0">
            @foreach ($faqs as $i => $faqItem)
            @php
                $q        = $faqItem['q_' . $locale] ?? $faqItem['q_es'] ?? '';
                $a        = $faqItem['a_' . $locale] ?? $faqItem['a_es'] ?? '';
                $iconPath = $faqItem['iconPath'] ?? '';
            @endphp
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-teal-800/5 overflow-hidden">
                    <button type="button"
                            @click="open = open === {{ $i }} ? null : {{ $i }}"
                            :aria-expanded="open === {{ $i }} ? 'true' : 'false'"
                            class="w-full flex items-center gap-4 px-4 py-4 text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-500">
                        <span class="w-11 h-11 rounded-xl bg-teal-800 grid place-items-center shrink-0">
                            <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}"/>
                            </svg>
                        </span>
                        <span class="flex-1 font-display text-base text-teal-800 leading-tight">{{ $q }}</span>
                        <span class="w-7 h-7 grid place-items-center text-orange-500 text-2xl font-light shrink-0 transition-transform duration-200"
                              :class="open === {{ $i }} ? 'rotate-45' : ''"
                              aria-hidden="true">+</span>
                    </button>
                    <div x-show="open === {{ $i }}" x-collapse>
                        <p class="px-4 pb-4 text-sm text-teal-800/75 leading-relaxed">{{ $a }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Trust banner footer --}}
        <div class="relative bg-cream-200/70 rounded-2xl px-5 py-5 flex items-center gap-4 overflow-hidden max-w-2xl mx-auto lg:max-w-4xl">
            <span class="w-12 h-12 rounded-full bg-cream-100 grid place-items-center shrink-0 relative z-10">
                <svg class="w-6 h-6 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
            </span>
            <div class="flex-1 relative z-10 min-w-0">
                <p class="text-sm text-teal-800 leading-tight font-bold">
                    Reserva fácil, segura y<br><span class="text-orange-500">100% garantizada</span>
                </p>
                <p class="mt-1 text-[11px] text-teal-800/70 leading-snug">
                    Tu información está protegida y<br>tus reservas están en buenas manos.
                </p>
            </div>
            <svg class="absolute right-0 top-0 h-full w-1/2 text-orange-400/30 pointer-events-none" viewBox="0 0 120 80" fill="none" stroke="currentColor" stroke-width="1" preserveAspectRatio="xMaxYMid slice" aria-hidden="true">
                <path d="M0 60 L20 45 L35 55 L50 30 L70 50 L85 25 L100 45 L120 35 L120 80 L0 80 Z"/>
            </svg>
        </div>
    </div>
</section>


{{-- ============================================================
     SECCIÓN 8 — VERIFICADO Y RECOMENDADO
     ============================================================ --}}
<section class="relative bg-cream-100 px-5 pt-12 pb-14 lg:py-20 overflow-hidden" aria-labelledby="reco-title">
    <div class="container mx-auto max-w-5xl lg:px-5">

        {{-- Eyebrow pill centrado --}}
        <div class="flex items-center justify-center gap-3 mb-10">
            <span class="h-px flex-1 bg-orange-400/40 max-w-[60px]"></span>
            <span class="inline-flex items-center gap-2 border border-teal-800 rounded-full px-4 py-1.5 bg-teal-800 shadow-sm">
                <svg class="w-3.5 h-3.5 text-orange-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 1.5L14.5 8H21l-5.5 4 2 6.5L12 14.5 6.5 18.5l2-6.5L3 8h6.5z"/>
                </svg>
                <span class="text-[10px] uppercase tracking-[0.18em] text-white font-bold">{{ \App\Models\Setting::get('home_sec_reco_eyebrow_' . $locale) ?: 'VERIFICADO Y RECOMENDADO POR' }}</span>
            </span>
            <span class="h-px flex-1 bg-orange-400/40 max-w-[60px]"></span>
        </div>

        {{-- Bloque hero: medallón + texto centrados en mobile, 2 col en desktop --}}
        <div class="flex flex-col items-center gap-8 lg:flex-row lg:gap-16 lg:items-center mb-14">

            {{-- Medallón eliminado a pedido del cliente --}}
            @if (false)
            <div class="flex justify-center shrink-0">
                <div class="relative w-48 h-48 lg:w-60 lg:h-60 drop-shadow-2xl" aria-hidden="true">
                    <svg viewBox="0 0 220 220" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                        <defs>
                            <linearGradient id="goldRing" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%"   stop-color="#fde68a"/>
                                <stop offset="35%"  stop-color="#f59e0b"/>
                                <stop offset="65%"  stop-color="#d97706"/>
                                <stop offset="100%" stop-color="#92400e"/>
                            </linearGradient>
                            <linearGradient id="tealBg" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%"   stop-color="#0f3438"/>
                                <stop offset="100%" stop-color="#0a2326"/>
                            </linearGradient>
                            <filter id="medallionShadow" x="-10%" y="-10%" width="120%" height="120%">
                                <feDropShadow dx="0" dy="4" stdDeviation="6" flood-color="#00000055"/>
                            </filter>
                            <!-- Arco de texto superior -->
                            <path id="arcTop" d="M 110,110 m -72,0 a 72,72 0 1,1 144,0" fill="none"/>
                            <path id="arcBottom" d="M 110,110 m -60,0 a 60,60 0 0,0 120,0" fill="none"/>
                        </defs>

                        <!-- Sombra exterior -->
                        <circle cx="110" cy="110" r="104" fill="url(#goldRing)" filter="url(#medallionShadow)"/>
                        <!-- Anillo exterior dorado -->
                        <circle cx="110" cy="110" r="104" fill="url(#goldRing)"/>
                        <!-- Anillo interior delgado dorado -->
                        <circle cx="110" cy="110" r="96" fill="none" stroke="#fde68a" stroke-width="1.2" opacity="0.6"/>
                        <!-- Fondo teal interior -->
                        <circle cx="110" cy="110" r="90" fill="url(#tealBg)"/>
                        <!-- Segundo anillo interior decorativo -->
                        <circle cx="110" cy="110" r="83" fill="none" stroke="#f59e0b" stroke-width="0.8" opacity="0.4"/>

                        <!-- Texto en arco superior "BEST TOURS" -->
                        <text font-family="Georgia, serif" font-size="13" font-weight="700" fill="#fcd34d" letter-spacing="3" text-anchor="middle">
                            <textPath href="#arcTop" startOffset="50%">B E S T · T O U R S</textPath>
                        </text>

                        <!-- Laureles izquierdo -->
                        <g fill="#fbbf24" opacity="0.85">
                            <ellipse cx="47" cy="78"  rx="7" ry="4" transform="rotate(-40 47 78)"/>
                            <ellipse cx="41" cy="93"  rx="7" ry="4" transform="rotate(-30 41 93)"/>
                            <ellipse cx="38" cy="109" rx="7" ry="4" transform="rotate(-15 38 109)"/>
                            <ellipse cx="40" cy="125" rx="7" ry="4" transform="rotate(10 40 125)"/>
                            <ellipse cx="47" cy="139" rx="7" ry="4" transform="rotate(30 47 139)"/>
                            <!-- Tallo -->
                            <line x1="50" y1="73" x2="52" y2="147" stroke="#fbbf24" stroke-width="1.5" opacity="0.5"/>
                        </g>
                        <!-- Laureles derecho (espejado) -->
                        <g fill="#fbbf24" opacity="0.85">
                            <ellipse cx="173" cy="78"  rx="7" ry="4" transform="rotate(40 173 78)"/>
                            <ellipse cx="179" cy="93"  rx="7" ry="4" transform="rotate(30 179 93)"/>
                            <ellipse cx="182" cy="109" rx="7" ry="4" transform="rotate(15 182 109)"/>
                            <ellipse cx="180" cy="125" rx="7" ry="4" transform="rotate(-10 180 125)"/>
                            <ellipse cx="173" cy="139" rx="7" ry="4" transform="rotate(-30 173 139)"/>
                            <line x1="170" y1="73" x2="168" y2="147" stroke="#fbbf24" stroke-width="1.5" opacity="0.5"/>
                        </g>

                        <!-- Separador superior -->
                        <line x1="70" y1="82" x2="150" y2="82" stroke="#f59e0b" stroke-width="0.8" opacity="0.5"/>

                        <!-- "IN" pequeño -->
                        <text x="110" y="96" font-family="Georgia, serif" font-size="10" font-weight="400" fill="#fcd34d" letter-spacing="4" text-anchor="middle" opacity="0.9">I N</text>

                        <!-- "LIMA" grande central -->
                        <text x="110" y="130" font-family="Georgia, serif" font-size="38" font-weight="700" fill="#ffffff" text-anchor="middle" letter-spacing="2">LIMA</text>

                        <!-- Separador inferior -->
                        <line x1="70" y1="137" x2="150" y2="137" stroke="#f59e0b" stroke-width="0.8" opacity="0.5"/>

                        <!-- 5 estrellas doradas -->
                        <text x="110" y="154" font-family="Arial" font-size="12" fill="#fbbf24" text-anchor="middle" letter-spacing="2">★★★★★</text>

                        <!-- Texto arco inferior "LIMA VIEW TOURS" -->
                        <text font-family="Georgia, serif" font-size="9.5" font-weight="400" fill="#fcd34d" letter-spacing="2" text-anchor="middle" opacity="0.75">
                            <textPath href="#arcBottom" startOffset="50%">LIMA · VIEW · TOURS</textPath>
                        </text>
                    </svg>

                    <!-- Cintas decorativas -->
                    <span class="absolute -bottom-4 left-[42px] w-7 h-12 bg-amber-500 shadow-md" style="clip-path: polygon(0 0, 100% 0, 100% 100%, 50% 78%, 0 100%);"></span>
                    <span class="absolute -bottom-4 right-[42px] w-7 h-12 bg-amber-500 shadow-md" style="clip-path: polygon(0 0, 100% 0, 100% 100%, 50% 78%, 0 100%);"></span>
                </div>
            </div>
            @endif

            {{-- Texto + card premio --}}
            <div class="text-center lg:text-left flex-1">
                <p class="text-xs text-teal-800/70 uppercase tracking-[0.18em] font-semibold">{{ \App\Models\Setting::get('home_reco_intro_' . $locale) ?: 'Estamos recomendados por' }}</p>
                <h2 id="reco-title" class="mt-2 font-display text-3xl md:text-4xl lg:text-5xl text-teal-800 leading-tight">
                    <em class="not-italic text-amber-700 italic">{{ \App\Models\Setting::get('home_reco_headline_' . $locale) ?: 'Best Tours in Lima' }}</em>
                </h2>
                <div class="mt-3 flex items-center justify-center lg:justify-start gap-2" aria-hidden="true">
                    <span class="h-px w-8 bg-orange-500/50"></span>
                    <svg class="w-3 h-3 text-orange-500" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l1.5 8.5L22 12l-8.5 1.5L12 22l-1.5-8.5L2 12l8.5-1.5L12 2z"/></svg>
                    <span class="h-px w-8 bg-orange-500/50"></span>
                </div>
                <p class="mt-4 text-sm text-teal-800/75 leading-relaxed max-w-md mx-auto lg:mx-0">
                    {{ \App\Models\Setting::get('home_reco_body_' . $locale) ?: 'Una de las mejores empresas que brindan experiencias turísticas verificadas.' }}
                </p>

                {{-- Card premio --}}
                <div class="mt-6 bg-white rounded-2xl ring-1 ring-orange-400/30 shadow-sm p-5 flex items-start gap-4 text-left max-w-md mx-auto lg:mx-0">
                    <span class="w-14 h-14 rounded-full bg-orange-50 grid place-items-center shrink-0 ring-1 ring-orange-400/40 mt-0.5">
                        <svg class="w-7 h-7 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0"/></svg>
                    </span>
                    <div>
                        <p class="font-bold text-teal-800 text-sm uppercase tracking-tight leading-tight">{!! \App\Models\Setting::get('home_reco_award_title_' . $locale) ?: 'GANAMOS EL PREMIO<br>DE VERIFICACIÓN' !!}</p>
                        <p class="mt-1.5 text-xs text-teal-800/70 leading-snug">{{ \App\Models\Setting::get('home_reco_award_desc_' . $locale) ?: 'para los mejores tours y empresas que brindan experiencias de alta calidad en Lima.' }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Divider decorativo --}}
        <div class="flex items-center gap-3 my-10" aria-hidden="true">
            <span class="h-px flex-1 bg-orange-400/25"></span>
            <svg class="w-4 h-4 text-orange-400/60 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l1.5 8.5L22 12l-8.5 1.5L12 22l-1.5-8.5L2 12l8.5-1.5L12 2z"/></svg>
            <span class="h-px flex-1 bg-orange-400/25"></span>
        </div>

        {{-- ¿Por qué somos recomendados? --}}
        <div>
            <h3 class="text-center text-[10px] uppercase tracking-[0.22em] text-teal-800/70 font-bold mb-6">
                {{ \App\Models\Setting::get('home_sec_why_reco_title_' . $locale) ?: '¿Por qué somos recomendados?' }}
            </h3>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-5">
                @php
                    $defaultRecoItems = [
                        ['title_es'=>'Calidad Verificada',         'title_en'=>'Verified Quality',          'title_pt'=>'Qualidade Verificada',
                         'desc_es'=>'Operadores evaluados bajo estrictos estándares de calidad.',
                         'desc_en'=>'Operators evaluated under strict quality standards.',
                         'desc_pt'=>'Operadores avaliados sob rigorosos padrões de qualidade.',
                         'icon'=>'shield'],
                        ['title_es'=>'Experiencias Auténticas',    'title_en'=>'Authentic Experiences',     'title_pt'=>'Experiências Autênticas',
                         'desc_es'=>'Tours diseñados para brindar experiencias únicas y memorables.',
                         'desc_en'=>'Tours designed to deliver unique and memorable experiences.',
                         'desc_pt'=>'Tours criados para oferecer experiências únicas e memoráveis.',
                         'icon'=>'star'],
                        ['title_es'=>'Soporte Confiable',          'title_en'=>'Reliable Support',          'title_pt'=>'Suporte Confiável',
                         'desc_es'=>'Acompañamiento antes, durante y después de tu viaje.',
                         'desc_en'=>'Support before, during and after your trip.',
                         'desc_pt'=>'Acompanhamento antes, durante e após sua viagem.',
                         'icon'=>'headset'],
                        ['title_es'=>'Altos Estándares de Calidad','title_en'=>'High Quality Standards',    'title_pt'=>'Altos Padrões de Qualidade',
                         'desc_es'=>'Comprometidos con la excelencia y la satisfacción del viajero.',
                         'desc_en'=>'Committed to excellence and traveler satisfaction.',
                         'desc_pt'=>'Comprometidos com a excelência e a satisfação do viajante.',
                         'icon'=>'medal'],
                    ];
                    $rawRecoItems = \App\Models\Setting::get('home_reco_items');
                    $recoItems = (is_array($rawRecoItems) && count($rawRecoItems)) ? $rawRecoItems : $defaultRecoItems;
                @endphp
                @foreach ($recoItems as $rItem)
                @php
                    $rTitle = $rItem['title_' . $locale] ?? $rItem['title_es'] ?? '';
                    $rDesc  = $rItem['desc_' . $locale]  ?? $rItem['desc_es']  ?? '';
                    $rIcon  = $rItem['icon'] ?? 'shield';
                @endphp
                    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-cream-200 p-5 lg:p-6 text-center">
                        <span class="w-12 h-12 lg:w-14 lg:h-14 rounded-full bg-teal-800 grid place-items-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                @if ($rIcon === 'shield')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                                @elseif ($rIcon === 'star')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                                @elseif ($rIcon === 'headset')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75a4.5 4.5 0 004.5 4.5v8.25a3 3 0 003 3h6a3 3 0 003-3v-8.25a4.5 4.5 0 004.5-4.5h-21z"/>
                                @else
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0"/>
                                @endif
                            </svg>
                        </span>
                        <p class="font-bold text-teal-800 text-sm leading-tight">{{ $rTitle }}</p>
                        <p class="mt-2 text-[11px] text-teal-800/65 leading-snug">{{ $rDesc }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Divider decorativo --}}
        <div class="flex items-center gap-3 my-10" aria-hidden="true">
            <span class="h-px flex-1 bg-orange-400/25"></span>
            <svg class="w-4 h-4 text-orange-400/60 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l1.5 8.5L22 12l-8.5 1.5L12 22l-1.5-8.5L2 12l8.5-1.5L12 2z"/></svg>
            <span class="h-px flex-1 bg-orange-400/25"></span>
        </div>

        {{-- Y también nos recomiendan --}}
        <div>
            <h3 class="text-center text-[10px] uppercase tracking-[0.22em] text-teal-800/70 font-bold mb-7">
                {{ \App\Models\Setting::get('home_sec_verified_in_' . $locale) ?: 'Recomendado y verificado en' }}
            </h3>

            {{-- Strip de logos reales, bien distribuido --}}
            <div class="bg-white rounded-3xl ring-1 ring-cream-200 shadow-sm max-w-3xl mx-auto
                        grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-cream-200">

                {{-- Tripadvisor --}}
                <div class="flex flex-col items-center justify-center gap-2.5 px-5 py-7">
                    <div class="flex items-center gap-2 h-7">
                        <svg viewBox="0 0 40 24" class="h-[22px] w-auto" aria-hidden="true">
                            <circle cx="13" cy="12" r="9.5" fill="#00AA6C"/>
                            <circle cx="27" cy="12" r="9.5" fill="#00AA6C"/>
                            <circle cx="13" cy="12" r="4.4" fill="#fff"/>
                            <circle cx="27" cy="12" r="4.4" fill="#fff"/>
                            <circle cx="13" cy="12" r="2" fill="#0a2326"/>
                            <circle cx="27" cy="12" r="2" fill="#0a2326"/>
                        </svg>
                        <span class="font-bold text-[15px] tracking-tight" style="color:#0a2326;">Tripadvisor</span>
                    </div>
                    <span class="flex items-center gap-1.5 text-[11px] font-semibold text-teal-800/60">
                        <span class="tracking-tight" style="color:#00AA6C;letter-spacing:1px;">●●●●●</span> 4.9
                    </span>
                </div>

                {{-- Viator --}}
                <div class="flex flex-col items-center justify-center gap-2.5 px-5 py-7">
                    <span class="font-sans font-extrabold text-[26px] tracking-tight leading-7 h-7" style="color:#0a2326;">Viator</span>
                    <span class="flex items-center gap-1.5 text-[11px] font-semibold text-teal-800/60">
                        <span class="text-orange-400">★★★★★</span> 4.8
                    </span>
                </div>

                {{-- GetYourGuide --}}
                <div class="flex flex-col items-center justify-center gap-2.5 px-5 py-7">
                    <span class="font-sans font-extrabold text-[19px] tracking-tight leading-7 h-7" style="color:#FF5533;">GetYourGuide</span>
                    <span class="flex items-center gap-1.5 text-[11px] font-semibold text-teal-800/60">
                        <span class="text-orange-400">★★★★★</span> 4.9
                    </span>
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-teal-800/55 max-w-md mx-auto leading-relaxed">
                {{ \App\Models\Setting::get('home_reviews_footer_' . $locale) ?: 'Miles de reseñas verificadas de viajeros de todo el mundo respaldan nuestras experiencias.' }}
            </p>
        </div>
    </div>
</section>

{{-- ============================================================
     SECCIÓN FAQ — AEO accordion (renderiza solo si hay FAQs)
     ============================================================ --}}
<x-faq-section />

@endsection

@push('schema')
@include('partials.faq-schema')
@endpush
