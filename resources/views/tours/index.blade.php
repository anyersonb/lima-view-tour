@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $L = fn (string $es, string $en, string $pt): string => $locale === 'pt' ? $pt : ($locale === 'en' ? $en : $es);
    $cat = $categoria ?? null;
    $titles = [
        'lima'  => $L('Tours en Lima', 'Tours in Lima', 'Tours em Lima'),
        'ica'   => $L('Tours en Ica', 'Tours in Ica', 'Tours em Ica'),
        'cusco' => $L('Tours en Cusco', 'Tours in Cusco', 'Tours em Cusco'),
        null    => $L('Todos nuestros tours', 'All our tours', 'Todos os nossos tours'),
    ];
    $banners = [
        'lima'  => 'Rectangle 19216.jpg',
        'ica'   => 'Rectangle 19219.jpg',
        'cusco' => 'Rectangle 19218.jpg',
        null    => 'banner-hero.jpg',
    ];
    $eyebrow = [
        'lima'  => $L('EXPLORA LA CAPITAL', 'EXPLORE THE CAPITAL', 'EXPLORE A CAPITAL'),
        'ica'   => $L('AVENTURA EN EL DESIERTO', 'DESERT ADVENTURE', 'AVENTURA NO DESERTO'),
        'cusco' => $L('CIUDADELA SAGRADA', 'SACRED CITADEL', 'CIDADELA SAGRADA'),
        null    => $L('NUESTRO CATÁLOGO', 'OUR CATALOG', 'NOSSO CATÁLOGO'),
    ][$cat ?? null] ?? $L('NUESTRO CATÁLOGO', 'OUR CATALOG', 'NOSSO CATÁLOGO');
    $sectionTitle = $titles[$cat ?? null] ?? 'Tours';
    $seoPageKey = \App\Support\PageSeo::toursKey($cat);
    // `regions.seo_title` / `seo_description` son los campos viejos del CMS de
    // Regiones: uno solo para los tres idiomas. Se respetan como escalón
    // intermedio para no tirar lo que ya esté cargado ahí, pero lo que manda
    // es la meta por idioma de Configuración → SEO → Metas por página.
    $regionSeoTitle = ($region ?? null)?->seo_title ?: null;
    $regionSeoDescription = ($region ?? null)?->seo_description ?: null;
    $bannerImg = $banners[$cat ?? null] ?? 'banner-hero.jpg';

    // Sin fallback ficticio: si no hay tours publicados, el catálogo se
    // muestra vacío (antes caía en 6 tours inventados con rating/reviews_count fijos).
    $toursCollection = isset($tours) && method_exists($tours, 'map') ? $tours : collect();

    // ── Tarea D: filtros de /tours — helpers de normalización ──────────────
    // Todo sale del dato REAL de cada tour (duration/language/group_type/
    // region_id), nunca cableado. El defecto de la referencia del cliente
    // (besttoursinlima.com/destinos-peru/cusco) era mostrar data-duracion="0"
    // y data-idiomas="" cuando el dato no existía: aquí, si no hay dato, la
    // faceta correspondiente queda null/vacía y el tour simplemente no
    // aparece bajo ESA faceta (no se inventa un valor para que "calce").
    // La normalización vive en App\Support\TourListing (testeada); aquí solo
    // se enlazan los nombres que usa el resto de la vista.
    $normalizeAscii = fn (?string $s): string => \App\Support\TourListing::ascii($s);
    $classifyDuration = fn (?string $raw): array => \App\Support\TourListing::classifyDuration($raw);
    $classifyLanguages = fn (?string $raw): array => \App\Support\TourListing::languageCodes($raw);

    $regionsById = (isset($regions) ? $regions : collect())->keyBy('id');

    $cardData = $toursCollection->map(function ($t) use ($classifyDuration, $classifyLanguages, $regionsById, $normalizeAscii) {
        $img = is_object($t) ? ($t->cover_image ?? '') : ($t['cover_image'] ?? '');
        // Única fuente de verdad: reseñas publicadas reales del tour.
        $stats = is_object($t) && method_exists($t, 'reviewStats') ? $t->reviewStats() : ['average' => null, 'total' => 0];

        $titleVal = is_object($t) ? ($t->title ?? $t->title_es ?? '') : ($t['title'] ?? $t['title_es'] ?? '');
        $now = (float) (is_object($t) ? $t->price : ($t['price'] ?? 0));
        $beforeRaw = is_object($t) ? ($t->price_before ?? null) : ($t['price_before'] ?? null);
        $before = $beforeRaw !== null && $beforeRaw !== '' ? (float) $beforeRaw : null;
        $durationRaw = is_object($t) ? ($t->duration ?? '') : ($t['duration'] ?? '');
        $languageRaw = is_object($t) ? ($t->language ?? '') : ($t['language'] ?? '');
        $groupRaw = trim(is_object($t) ? ($t->group_type ?? '') : ($t['group_type'] ?? ''));
        $modality = \App\Support\TourListing::modality($groupRaw);
        // Título en los 3 idiomas (sin acentos, minúsculas) para que el buscador
        // en cliente encuentre el tour sin importar el idioma activo.
        $searchTitles = collect(['es', 'en', 'pt'])
            ->map(fn ($l) => is_object($t) ? ($t->{'title_'.$l} ?? null) : ($t['title_'.$l] ?? null))
            ->push($titleVal)
            ->filter()
            ->map(fn ($v) => $normalizeAscii((string) $v))
            ->unique()
            ->implode(' ');
        $regionId = is_object($t) ? ($t->region_id ?? null) : ($t['region_id'] ?? null);
        $region = $regionId ? $regionsById->get($regionId) : null;
        $durationInfo = $classifyDuration($durationRaw);

        return [
            'title' => $titleVal,
            'before' => $before,
            'now' => $now,
            'badge' => is_object($t) ? ($t->badge_text ?? null) : ($t['badge_text'] ?? null),
            'badgeType' => is_object($t) ? ($t->badge_type ?? 'warn') : ($t['badge_type'] ?? 'warn'),
            'rating' => $stats['average'] !== null ? number_format($stats['average'], 1) : null,
            'reviews' => $stats['total'],
            'duration' => $durationRaw,
            // Etiquetas ya localizadas (es/en/pt); null = el tour no tiene el dato => la card no lo pinta.
            'durationLabel' => \App\Support\TourListing::durationLabel($durationRaw),
            'lang' => $languageRaw,
            'languageLabel' => \App\Support\TourListing::languageLabel($languageRaw),
            'group' => $groupRaw,
            'modalityLabel' => $modality['label'] ?? null,
            'imgPath' => $img ?: null,
            'img' => \App\Support\ImagePath::url($img) ?? asset('assets/banners/banner-hero.jpg'),
            // Miniatura liviana (480 px) para la card móvil; cae a la original si no existe.
            'imgThumb' => \App\Support\TourThumbnail::url($img) ?? asset('assets/banners/banner-hero.jpg'),
            'slug' => is_object($t) ? ($t instanceof \App\Models\Tour ? $t->slugFor(app()->getLocale()) : ($t->slug ?? '')) : ($t['slug'] ?? ''),
            'showBestSeller' => (bool) (is_object($t) ? ($t->show_best_seller ?? true) : ($t['show_best_seller'] ?? true)),
            'showOffer'      => (bool) (is_object($t) ? ($t->show_offer_badge ?? true) : ($t['show_offer_badge'] ?? true)),
            // Interruptores por tour del CMS (null = columna aún sin migrar => como antes: visibles).
            'pickup' => (bool) (is_object($t) ? ($t->pickup_included ?? true) : ($t['pickup_included'] ?? true)),
            'freeCancellation' => (bool) (is_object($t) ? ($t->free_cancellation ?? true) : ($t['free_cancellation'] ?? true)),
            // Facetas de filtro (Tarea D) — normalizadas del dato real.
            'durationBucket' => $durationInfo['bucket'],
            'durationWeight' => $durationInfo['weight'],
            'languageCodes'  => $classifyLanguages($languageRaw),
            'groupSlug'      => $modality['slug'] ?? null,
            'searchText'     => $searchTitles,
            'regionSlug'     => $region?->slug,
            'isOffer'        => $before !== null && $before > $now,
            'nameSort'       => $normalizeAscii($titleVal),
        ];
    })->values();

    // Paginación cliente: se ven $pageSize y el botón revela otras tantas (tour-filters.js).
    $pageSize = 9;

    // ── Facetas: solo se listan opciones con ≥1 tour real ──────────────────
    $priceValues = $cardData->pluck('now')->filter(fn ($v) => $v > 0)->values();
    $priceMin = $priceValues->isNotEmpty() ? (float) floor($priceValues->min()) : 0.0;
    $priceMax = $priceValues->isNotEmpty() ? (float) ceil($priceValues->max()) : 0.0;
    if ($priceMax <= $priceMin) {
        $priceMax = $priceMin + 10;
    }

    $durationBucketLabels = [
        'medio-dia'  => __('ui.duration_half_day'),
        'full-day'   => __('ui.duration_full_day'),
        '2-dias'     => __('ui.duration_2_days'),
        '3-mas-dias' => __('ui.duration_multi_day'),
        'otros'      => __('ui.duration_other'),
    ];
    $durationOptions = collect($durationBucketLabels)
        ->map(fn ($label, $key) => [
            'value' => $key,
            'label' => $label,
            'count' => $cardData->where('durationBucket', $key)->count(),
        ])
        ->filter(fn ($o) => $o['count'] > 0)
        ->values();

    $languageLabels = [
        'es' => __('ui.lang_es'),
        'en' => __('ui.lang_en'),
        'pt' => __('ui.lang_pt'),
        'fr' => __('ui.lang_fr'),
    ];
    $languageCounts = [];
    foreach ($cardData as $c) {
        foreach ($c['languageCodes'] as $code) {
            $languageCounts[$code] = ($languageCounts[$code] ?? 0) + 1;
        }
    }
    $languageOptions = collect($languageCounts)
        ->map(fn ($count, $code) => ['value' => $code, 'label' => $languageLabels[$code] ?? ucfirst($code), 'count' => $count])
        ->sortByDesc('count')
        ->values();

    $groupOptions = $cardData->filter(fn ($c) => $c['groupSlug'])
        ->groupBy('groupSlug')
        ->map(fn ($items, $slug) => ['value' => $slug, 'label' => $items->first()['modalityLabel'], 'count' => $items->count()])
        ->sortByDesc('count')
        ->values();

    $regionTourCounts = $cardData->filter(fn ($c) => $c['regionSlug'])->groupBy('regionSlug')->map->count();
    $regionOptions = ($regions ?? collect())
        ->map(fn ($r) => ['value' => $r->slug, 'label' => $r->name, 'count' => $regionTourCounts->get($r->slug, 0)])
        ->filter(fn ($o) => $o['count'] > 0)
        ->values();

    $offerCount = $cardData->where('isOffer', true)->count();
    $totalToursCount = $cardData->count();

    // JSON embebido para que el JS de filtros arme "N tours encontrados" /
    // "Ver N tours" sin reimplementar la pluralización de Laravel.
    $filtersI18n = [
        'toursFoundZero'  => __('ui.tours_found_zero'),
        'toursFoundOne'   => __('ui.tours_found_one'),
        'toursFoundOther' => __('ui.tours_found_other'),
        'seeToursOne'     => __('ui.see_tours_count_one'),
        'seeToursOther'   => __('ui.see_tours_count_other'),
        'upToPrice'       => __('ui.up_to') . ' US$:count',
        'searchEmpty'     => __('ui.tours_search_empty'),
        'filtersEmpty'    => __('ui.no_tours_found'),
    ];
@endphp

{{-- Editables desde el admin: Configuración → SEO → Metas por página →
     Catálogo de tours / Catálogo — Lima|Ica|Cusco (una meta por categoría,
     porque cada una es una URL distinta). Vacío = texto por defecto. --}}
@section('title', \App\Support\PageSeo::title($seoPageKey, $regionSeoTitle ?: (\App\Support\PageSeo::langDefault($seoPageKey, 'title') ?? ($sectionTitle . ' — ' . __('seo.site_name')))))
@section('description', \App\Support\PageSeo::description($seoPageKey, $regionSeoDescription ?: (\App\Support\PageSeo::langDefault($seoPageKey, 'description') ?? ($cat
    ? __('ui.tours_meta_description_region', ['region' => ($region ?? null)?->name ?: ucfirst($cat)])
    : __('ui.tours_meta_description_all')))))

@php
    // Metas por página → Catálogo de tours / Catálogo — Lima|Ica|Cusco →
    // Datos estructurados. Misma clave $seoPageKey que title/description de
    // arriba, para que cada URL (catálogo completo o por región) tenga su
    // propio schema. Vacío = no imprime nada extra.
    $toursCustomSchema = \App\Support\PageSeo::schemaJsonLd($seoPageKey);
@endphp
@if ($toursCustomSchema)
@push('schema')
<x-schema-raw :json="$toursCustomSchema" />
@endpush
@endif

@section('content')

{{-- ───────── HERO ─────────
     <640: sin foto ni caja blanca. La sección sigue en el flujo y se compacta:
     breadcrumb solo para lectores de pantalla, H1 corto visible, buscador suelto
     y chips. Nada de esto va dentro de un contenedor con display:none (S-07).
     ≥640: igual que siempre. --}}
<section class="relative isolate text-teal-800 bg-[#faf9f7] sm:text-white sm:bg-transparent">
    <div class="hidden sm:block absolute inset-0 -z-10">
        {{-- Sin descarga en móvil (S-06): el <source> solo aplica desde 640 px; el <img> de respaldo es un GIF 1x1. --}}
        <picture>
            @if ($bannerImg === 'banner-hero.jpg')
                <source media="(min-width:640px)" type="image/webp" sizes="100vw" srcset="{{ collect([640, 1024, 1600, 1920])->map(fn ($w) => asset('assets/banners/banner-hero-' . $w . 'w.webp') . ' ' . $w . 'w')->implode(', ') }}">
            @endif
            <source media="(min-width:640px)" srcset="{{ asset('assets/banners/' . rawurlencode($bannerImg)) }}">
            <img src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="" width="1920" height="1000" class="w-full h-full object-cover" loading="eager" fetchpriority="high">
        </picture>
        <div class="absolute inset-0 bg-gradient-to-b from-teal-900/40 via-teal-900/50 to-teal-900/70"></div>
    </div>

    <div class="container mx-auto px-4 sm:px-5 lg:px-10">
        <nav aria-label="Breadcrumb" class="sr-only sm:not-sr-only sm:pt-6 text-xs text-white/85">
            <a href="{{ route('home', ['locale' => $locale]) }}" class="hover:text-orange-400">{{ __('ui.home') }}</a> &gt; <span>{{ $sectionTitle }}</span>
        </nav>
    </div>

    <div class="container mx-auto px-4 sm:px-5 lg:px-10 pt-2 pb-0 sm:pt-12 sm:pb-16 md:pb-24 max-w-3xl">
        {{-- Pill: TOURS SELECCIONADOS EN PERÚ --}}
        <span class="hidden sm:inline-flex items-center gap-2 rounded-full border border-white/20 bg-black/25 backdrop-blur-sm pl-3.5 pr-4 py-2 mb-5">
            <svg class="w-3.5 h-3.5 text-amber-400 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.5 8.5L22 12l-8.5 1.5L12 22l-1.5-8.5L2 12l8.5-1.5L12 2z"/></svg>
            <span class="text-[11px] sm:text-xs uppercase tracking-[0.18em] text-amber-200 font-bold">{{ $L('Tours seleccionados en Perú', 'Selected tours in Peru', 'Tours selecionados no Peru') }}</span>
        </span>

        {{-- Único H1 de la página. Categorías: "Tours en {Destino}" en todos los tamaños y el eslogan pasa a <p>.
             /tours: título corto en móvil, titular de marketing desde 640 px (sin cambios). --}}
        @if ($cat)
            <h1 class="tours-hero__title font-display font-normal text-[22px] leading-[1.2] py-2 sm:py-0 sm:text-[54px] sm:leading-[0.98] md:text-[64px] lg:text-[72px] lg:leading-[0.96]">{{ \App\Support\PageSeo::toursHeading($cat) }}</h1>
            <p class="tours-hero__tagline hidden sm:block mt-4 font-display font-normal text-[24px] leading-[1.15] md:text-[30px] text-white/95">
                {{ $L('Descubre experiencias con un estilo más', 'Discover experiences with a more', 'Descubra experiências com um estilo mais') }}
                <span class="italic text-orange-400 font-light">premium.</span>
            </p>
        @else
            <h1 class="tours-hero__title font-display font-normal text-[22px] leading-[1.2] py-2 sm:py-0 sm:text-[54px] sm:leading-[0.98] md:text-[64px] lg:text-[72px] lg:leading-[0.96]">
                <span class="sm:hidden">{{ $sectionTitle }}</span>
                <span class="hidden sm:inline">
                    {{ $L('Descubre experiencias con un estilo más', 'Discover experiences with a more', 'Descubra experiências com um estilo mais') }}
                    <span class="italic text-orange-400 font-light">premium.</span>
                </span>
            </h1>
        @endif

        {{-- Buscador: suelto en móvil (filtra la lista en cliente); caja blanca desde 640 px --}}
        <div class="sm:mt-8 sm:bg-white sm:text-teal-800 sm:rounded-3xl sm:shadow-2xl sm:ring-1 sm:ring-black/5 sm:p-5 sm:max-w-2xl">
            <p class="hidden sm:block text-[11px] font-bold uppercase tracking-[0.18em] text-teal-800/70 mb-3">{{ $L('Buscar experiencia', 'Search experience', 'Buscar experiência') }}</p>
            <form method="GET" action="{{ route('tours.results', ['locale' => $locale]) }}" class="flex items-center gap-2.5" role="search" data-tour-search-form>
                <div class="relative flex-1 min-w-0">
                    <span class="absolute left-3 sm:left-3.5 top-1/2 -translate-y-1/2 text-teal-800/60 sm:text-teal-800/60" aria-hidden="true">
                        <svg class="w-[18px] h-[18px] sm:w-5 sm:h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                    </span>
                    <input type="search" name="q" data-tour-search enterkeyhint="search" autocomplete="off" placeholder="{{ __('ui.search_tour') }}"
                           class="w-full h-8 sm:h-auto rounded-lg sm:rounded-2xl border border-teal-800/15 bg-cream-100 pl-10 sm:pl-11 pr-3 py-0 sm:py-3.5 text-base text-teal-800 placeholder-teal-800/70 sm:placeholder-teal-800/70 focus:border-teal-600 focus:ring-1 focus:ring-teal-600 outline-none"
                           aria-label="{{ __('ui.search_tour_label') }}">
                </div>
                <button type="submit" class="hidden sm:inline-block shrink-0 bg-teal-800 hover:bg-teal-900 text-white font-bold text-sm rounded-2xl px-6 py-3.5 transition">{{ $L('Buscar', 'Search', 'Buscar') }}</button>
            </form>
            <div class="mt-2 sm:mt-3 flex items-center gap-1.5 sm:gap-2 overflow-x-auto scrollbar-hide -mx-1 px-1">
                <a href="{{ route('tours.index', ['locale' => $locale]) }}"
                   class="shrink-0 rounded-full text-[13px] sm:text-sm font-semibold sm:font-bold px-4 py-1 leading-[18px] sm:leading-5 sm:px-5 sm:py-2.5 transition {{ !$cat ? 'bg-teal-800 text-white' : 'bg-cream-100 text-teal-800 sm:ring-1 sm:ring-teal-800/10 hover:bg-cream-200' }}">{{ $L('Todos', 'All', 'Todos') }}</a>
                @foreach (['lima' => 'Lima', 'ica' => 'Ica', 'cusco' => 'Cusco'] as $rSlug => $rLabel)
                    <a href="{{ route('tours.category', ['locale' => $locale, 'categoria' => $rSlug]) }}"
                       class="shrink-0 rounded-full text-[13px] sm:text-sm font-semibold sm:font-bold px-4 py-1 leading-[18px] sm:leading-5 sm:px-5 sm:py-2.5 transition {{ $cat === $rSlug ? 'bg-teal-800 text-white' : 'bg-cream-100 text-teal-800 sm:ring-1 sm:ring-teal-800/10 hover:bg-cream-200' }}">{{ $rLabel }}</a>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ───────── GRID DE TOURS + FILTROS (Tarea D) ───────── --}}
<section class="bg-[#faf9f7] pt-[7px] pb-10 sm:bg-white sm:py-16 lg:py-20" id="catalogo">
    <div class="container mx-auto px-4 sm:px-5 lg:px-10">
        <h2 class="sr-only md:hidden">{{ __('ui.tours_list_heading') }}</h2>
        {{-- Encabezado + categorías (rutas propias, SEO): ocultos en mobile --}}
        <div class="hidden md:block mb-10">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
            <div>
                <p class="text-[11px] uppercase tracking-[0.2em] text-teal-800/70 font-semibold">{{ $L('CATÁLOGO', 'CATALOG', 'CATÁLOGO') }}</p>
                <h2 class="mt-2 font-display text-3xl md:text-4xl text-teal-800 leading-tight">{{ $sectionTitle }}</h2>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('tours.index', ['locale' => $locale]) }}"
                   class="px-5 py-2.5 rounded-pill text-xs font-semibold uppercase tracking-wide border transition
                   {{ !$cat ? 'bg-teal-700 text-white border-teal-700' : 'bg-cream-100 text-teal-800 border-teal-800/10 hover:border-orange-400' }}">
                    {{ __('ui.all') }}
                </a>
                @foreach (['lima', 'ica', 'cusco'] as $r)
                    <a href="{{ route('tours.category', ['locale' => $locale, 'categoria' => $r]) }}"
                       class="px-5 py-2.5 rounded-pill text-xs font-semibold uppercase tracking-wide border transition
                       {{ $cat === $r ? 'bg-teal-700 text-white border-teal-700' : 'bg-cream-100 text-teal-800 border-teal-800/10 hover:border-orange-400' }}">
                        {{ ucfirst($r) }}
                    </a>
                @endforeach
            </div>
        </div>
        </div>

        {{-- Filtros en cliente (data-* sobre las tarjetas ya renderizadas).
             No agregan parámetros a la URL: ver resources/js/tour-filters.js --}}
        <div class="tours-filters lg:grid lg:grid-cols-[250px_1fr] lg:gap-10 lg:items-start"
             data-tour-filters
             data-i18n='@json($filtersI18n)'>

            {{-- ASIDE escritorio (≥1024px), sticky --}}
            <aside class="hidden lg:block">
                <div class="sticky top-24">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-display text-lg text-teal-800">{{ __('ui.filters') }}</h2>
                        <button type="button" data-filters-clear class="text-xs font-semibold text-orange-700 hover:text-orange-800 underline underline-offset-2">
                            {{ __('ui.clear_filters') }}
                        </button>
                    </div>
                    @include('tours.partials.filter-groups', ['prefix' => 'desktop'])
                </div>
            </aside>

            {{-- Panel deslizante móvil / tablet (<1024px) --}}
            <div class="lg:hidden">
                <div data-filters-overlay class="hidden fixed inset-0 z-[70] bg-teal-900/50"></div>
                <div data-filters-panel
                     role="dialog"
                     aria-modal="true"
                     aria-label="{{ __('ui.filters') }}"
                     aria-hidden="true"
                     inert
                     tabindex="-1"
                     class="tours-filters__panel fixed top-0 right-0 z-[80] h-full w-[88%] bg-white shadow-2xl flex flex-col translate-x-full transition-transform duration-300"
                     style="height:auto; bottom:var(--cookie-banner-h, 0px);">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-teal-800/10">
                        <h2 class="font-display text-lg text-teal-800">{{ __('ui.filters') }}</h2>
                        <button type="button" data-filters-close aria-label="{{ __('ui.close_filters') }}"
                                class="w-9 h-9 rounded-full grid place-items-center text-teal-800 hover:bg-cream-100 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto px-5 py-5">
                        @include('tours.partials.filter-groups', ['prefix' => 'mobile'])
                    </div>
                    <div class="px-5 py-4 border-t border-teal-800/10 flex items-center gap-3">
                        <button type="button" data-filters-clear class="text-xs font-semibold text-orange-700 hover:text-orange-800 underline underline-offset-2 shrink-0">
                            {{ __('ui.clear_filters') }}
                        </button>
                        <button type="button" data-filters-close
                                class="flex-1 bg-teal-900 hover:bg-teal-950 text-white rounded-full px-5 py-3 font-semibold text-sm transition">
                            <span data-see-count>{{ __($totalToursCount === 1 ? 'ui.see_tours_count_one' : 'ui.see_tours_count_other', ['count' => $totalToursCount]) }}</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- RESULTADOS --}}
            <div>
                {{-- Barra: botón Filtros (<lg) + contador + orden.
                     <640: una sola fila Filtros | separador | orden | contador. --}}
                <div class="flex flex-nowrap sm:flex-wrap items-center gap-[15px] sm:gap-3 mb-3 sm:mb-6">
                    <button type="button" data-filters-open aria-haspopup="dialog" aria-expanded="false"
                            class="order-1 sm:order-none shrink-0 lg:hidden inline-flex items-center gap-1.5 sm:gap-2 rounded-xl sm:rounded-full border border-teal-800/15 bg-cream-100 px-2.5 py-1 sm:px-5 sm:py-2.5 text-sm font-bold text-teal-800 hover:border-orange-400 transition">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18M6 9.75h12M10.5 15h3"/>
                        </svg>
                        {{ __('ui.filters') }}
                    </button>

                    <span class="order-2 sm:hidden self-stretch my-1 w-px shrink-0 bg-teal-800/15" aria-hidden="true"></span>

                    @php
                        $tourCountKey = $totalToursCount === 0
                            ? 'ui.tours_found_zero'
                            : ($totalToursCount === 1 ? 'ui.tours_found_one' : 'ui.tours_found_other');
                    @endphp
                    <p data-tour-count aria-live="polite" class="order-4 w-[66px] shrink-0 text-xs leading-tight sm:order-3 lg:order-none sm:w-full lg:w-auto sm:text-sm text-teal-800/70 sm:font-medium">
                        {{ __($tourCountKey, ['count' => $totalToursCount]) }}
                    </p>

                    <label class="order-3 sm:order-none flex-1 min-w-0 sm:flex-none sm:ml-auto flex items-center gap-2 text-sm text-teal-800/80">
                        <span class="hidden sm:inline">{{ __('ui.sort_by') }}</span>
                        <select data-sort aria-label="{{ __('ui.sort_by') }}" class="w-full min-w-0 sm:w-auto h-[27px] sm:h-auto py-0 sm:py-2 pl-2.5 sm:pl-3 max-sm:pr-7 max-sm:text-[13px] max-sm:bg-[length:1.25rem_1.25rem] max-sm:bg-[right_0.15rem_center] rounded-lg sm:rounded-xl border-teal-800/15 text-sm text-teal-800 focus:border-teal-600 focus:ring-1 focus:ring-teal-600">
                            <option value="recommended">{{ __('ui.sort_recommended') }}</option>
                            <option value="price-asc">{{ __('ui.sort_price_asc') }}</option>
                            <option value="price-desc">{{ __('ui.sort_price_desc') }}</option>
                            <option value="duration">{{ __('ui.sort_duration') }}</option>
                            <option value="name">{{ __('ui.sort_name') }}</option>
                        </select>
                    </label>
                </div>

                {{-- UN solo <article> por tour: fila compacta <640, card vertical (2/3 col.) desde 640 --}}
                <div class="flex flex-col gap-2 -mx-[3px] sm:mx-0 sm:grid sm:gap-6 sm:grid-cols-2 lg:grid-cols-3" data-tour-cards>
                    @foreach ($cardData as $i => $tour)
                        <x-tour-card-responsive
                            variant="list"
                            :eager="$i < 2"
                            data-tour-card
                            data-index="{{ $i }}"
                            :style="$i >= $pageSize ? 'display:none' : null"
                            data-price="{{ $tour['now'] }}"
                            data-duration="{{ $tour['durationBucket'] }}"
                            data-duration-weight="{{ $tour['durationWeight'] }}"
                            data-languages="{{ implode(',', $tour['languageCodes']) }}"
                            data-group="{{ $tour['groupSlug'] }}"
                            data-region="{{ $tour['regionSlug'] }}"
                            data-offer="{{ $tour['isOffer'] ? '1' : '0' }}"
                            data-name="{{ $tour['nameSort'] }}"
                            data-search="{{ $tour['searchText'] }}"
                            :title="$tour['title']"
                            :slug="$tour['slug']"
                            :img="$tour['img']"
                            :img-thumb="$tour['imgThumb']"
                            :img-path="$tour['imgPath']"
                            :before="$tour['before']"
                            :now="$tour['now']"
                            :rating="$tour['rating']"
                            :reviews="$tour['reviews']"
                            :duration="$tour['durationLabel']"
                            :language="$tour['languageLabel']"
                            :show-offer="$tour['showOffer']"
                            :pickup="$tour['pickup']"
                            :free-cancellation="$tour['freeCancellation']"
                            currency="US$"
                        />
                    @endforeach
                </div>

                {{-- Estado vacío (0 resultados tras filtrar) --}}
                <div data-tour-empty class="hidden text-center py-16">
                    <p data-tour-empty-text class="text-teal-800/70">{{ __('ui.no_tours_found') }}</p>
                    <button type="button" data-filters-clear class="mt-4 inline-flex items-center rounded-full bg-teal-900 hover:bg-teal-950 text-white px-6 py-2.5 text-sm font-semibold transition">
                        {{ __('ui.clear_filters') }}
                    </button>
                </div>

                @if (count($cardData) > $pageSize)
                <div class="mt-12 flex justify-center" data-load-more data-page-size="{{ $pageSize }}">
                    <button type="button" class="btn--primary-outline" data-load-more-btn>{{ __('ui.load_more_tours') }}</button>
                </div>
                @endif
            </div>
        </div>
    </div>
</section>

{{-- ───────── TRIPADVISOR QUOTE ───────── --}}
<section class="bg-cream-100 py-12 lg:py-16">
    <div class="container mx-auto px-5 lg:px-10 max-w-4xl text-center">
        <p class="text-teal-800/70 inline-flex items-center gap-2 text-sm uppercase tracking-[0.18em] font-semibold">
            <span aria-hidden="true">&#127797;</span> Tripadvisor
        </p>
        <blockquote class="mt-6 font-display text-2xl md:text-3xl text-teal-800 leading-snug">
            &laquo;Una agencia confiable y muy organizada. Cada parada del recorrido fue una sorpresa positiva.&raquo;
        </blockquote>
        <figcaption class="mt-6 inline-flex items-center gap-3 text-left">
            <span class="w-10 h-10 rounded-full bg-cream-200"></span>
            <span class="leading-tight">
                <span class="block font-semibold text-teal-800">Valeriy Roberts</span>
                <span class="block text-xs text-teal-800/70">{{ __('ui.verified_client') }}</span>
            </span>
        </figcaption>
    </div>
</section>

{{-- ───────── TESTIMONIOS + CARD ───────── --}}
<section class="bg-cream-100 pb-16 lg:pb-20">
    <div class="container mx-auto px-5 lg:px-10 grid gap-6 lg:grid-cols-[1fr_1fr_minmax(0,1.05fr)] items-stretch">
        <div class="grid gap-5 sm:grid-cols-2 lg:col-span-2">
            @foreach ([['Sara Fernández','Spain'],['Rebeca Figueroa','Colombia'],['Liam Carter','USA'],['Ana Suárez','México']] as [$name,$country])
                <figure class="bg-white rounded-2xl overflow-hidden flex flex-col">
                    <div class="p-6">
                        <span class="text-orange-400 text-3xl leading-none" aria-hidden="true">&rdquo;</span>
                        <blockquote class="mt-2 text-sm text-teal-800/85 leading-relaxed">
                            Reservar fue muy fácil y la experiencia superó nuestras expectativas. Definitivamente repetiremos.
                        </blockquote>
                        <p class="mt-3 text-orange-400 text-sm" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733; <span class="text-teal-800/70">Google</span></p>
                    </div>
                    <figcaption class="bg-teal-700 text-white px-5 py-3 flex items-center gap-3 mt-auto">
                        <span class="w-9 h-9 rounded-full bg-cream-200"></span>
                        <span class="leading-tight">
                            <span class="block font-semibold text-sm tracking-wide">{{ strtoupper($name) }}</span>
                            <span class="block text-[10px] uppercase tracking-[0.15em] text-white/70">{{ $country }}</span>
                        </span>
                    </figcaption>
                </figure>
            @endforeach
        </div>

        <article class="relative rounded-2xl overflow-hidden text-white min-h-[28rem]">
            <img src="{{ asset('assets/banners/Rectangle 19211.jpg') }}" alt=""
                 class="absolute inset-0 w-full h-full object-cover" loading="lazy">
            <div class="absolute inset-0 bg-gradient-to-t from-teal-900/85 via-teal-900/40 to-teal-900/20"></div>
            <div class="relative h-full p-8 flex flex-col">
                <p class="text-[11px] uppercase tracking-[0.2em] font-semibold">{{ __('ui.happy_clients') }}</p>
                <h3 class="mt-3 font-display text-3xl lg:text-4xl leading-tight">{{ __('ui.clients_opinion') }}</h3>
                <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="btn--primary mt-auto self-start">{{ __('ui.see_tours') }}</a>
            </div>
        </article>
    </div>
</section>

{{-- ───────── ¿POR QUÉ RESERVAR CON NOSOTROS? ───────── --}}
<section class="bg-white py-16 lg:py-20">
    <div class="container mx-auto px-5 lg:px-10">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <h2 class="font-display text-3xl md:text-4xl lg:text-5xl text-teal-800 leading-tight">{{ __('ui.why_book_with_us') }}</h2>
            <p class="mt-3 text-teal-800/70 text-sm">{{ __('ui.why_book_subtitle') }}</p>
        </div>

        <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                [__('ui.feature_unique_title'), __('ui.feature_unique_desc')],
                [__('ui.feature_responsible_title'), __('ui.feature_responsible_desc')],
                [__('ui.feature_packages_title'), __('ui.feature_packages_desc')],
                [__('ui.feature_guides_title'), __('ui.feature_guides_desc')],
            ] as [$title, $desc])
                <div class="text-center">
                    <span class="mx-auto w-12 h-12 rounded-full bg-cream-100 grid place-items-center text-teal-700">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/></svg>
                    </span>
                    <h3 class="mt-4 font-display text-xl text-teal-800">{{ $title }}</h3>
                    <p class="mt-2 text-sm text-teal-800/70 leading-relaxed">{{ $desc }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

@endsection
