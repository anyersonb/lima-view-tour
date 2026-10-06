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
    $normalizeAscii = function (?string $s): string {
        return \Illuminate\Support\Str::of((string) $s)->lower()->ascii()->trim()->toString();
    };

    $classifyDuration = function (?string $raw) use ($normalizeAscii): array {
        $n = $normalizeAscii($raw);
        if ($n === '') {
            return ['bucket' => null, 'weight' => null];
        }
        if (preg_match('/(\d+)\s*(?:d|dias?|days?)\b/', $n, $m)) {
            $days = (int) $m[1];
            if ($days >= 3) return ['bucket' => '3-mas-dias', 'weight' => $days * 24];
            if ($days === 2) return ['bucket' => '2-dias', 'weight' => 48];
            // 1 día declarado explícitamente ⇒ jornada completa.
        }
        if (preg_match('/full\s*day|dia completo|todo el dia/', $n)) {
            return ['bucket' => 'full-day', 'weight' => 8];
        }
        if (preg_match('/(\d+)\s*(?:h|horas?|hours?)\b/', $n, $m)) {
            $hours = (int) $m[1];
            return $hours <= 5
                ? ['bucket' => 'medio-dia', 'weight' => $hours]
                : ['bucket' => 'full-day', 'weight' => $hours];
        }
        if (preg_match('/medio\s*dia|half\s*day/', $n)) {
            return ['bucket' => 'medio-dia', 'weight' => 4];
        }
        // Texto real que no matchea ningún patrón conocido: se agrupa en
        // "Otros" (visible solo si hay ≥1 tour así) en vez de desaparecer.
        return ['bucket' => 'otros', 'weight' => 999];
    };

    $classifyLanguages = function (?string $raw) use ($normalizeAscii): array {
        $n = $normalizeAscii($raw);
        if ($n === '') return [];
        $parts = preg_split('/[\/,&+]| y | e | and /', $n) ?: [];
        $codes = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') continue;
            $code = match (true) {
                str_contains($p, 'espan') || str_contains($p, 'spanish') || $p === 'es' => 'es',
                str_contains($p, 'ingl') || str_contains($p, 'english') || $p === 'en' => 'en',
                str_contains($p, 'portugu') || $p === 'pt' => 'pt',
                str_contains($p, 'franc') || str_contains($p, 'french') || $p === 'fr' => 'fr',
                default => \Illuminate\Support\Str::slug($p) ?: null,
            };
            if ($code && ! in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }
        return $codes;
    };

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
            'lang' => $languageRaw,
            'group' => $groupRaw,
            'img' => \App\Support\ImagePath::url($img) ?? asset('assets/banners/banner-hero.jpg'),
            'slug' => is_object($t) ? ($t->slug ?? '') : ($t['slug'] ?? ''),
            'showBestSeller' => (bool) (is_object($t) ? ($t->show_best_seller ?? true) : ($t['show_best_seller'] ?? true)),
            'showOffer'      => (bool) (is_object($t) ? ($t->show_offer_badge ?? true) : ($t['show_offer_badge'] ?? true)),
            // Facetas de filtro (Tarea D) — normalizadas del dato real.
            'durationBucket' => $durationInfo['bucket'],
            'durationWeight' => $durationInfo['weight'],
            'languageCodes'  => $classifyLanguages($languageRaw),
            'groupSlug'      => $groupRaw !== '' ? (\Illuminate\Support\Str::slug($groupRaw) ?: null) : null,
            'regionSlug'     => $region?->slug,
            'isOffer'        => $before !== null && $before > $now,
            'nameSort'       => $normalizeAscii($titleVal),
        ];
    })->values();

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
        ->map(fn ($items, $slug) => ['value' => $slug, 'label' => $items->first()['group'], 'count' => $items->count()])
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
    ];
@endphp

{{-- Editables desde el admin: Configuración → SEO → Metas por página →
     Catálogo de tours / Catálogo — Lima|Ica|Cusco (una meta por categoría,
     porque cada una es una URL distinta). Vacío = texto por defecto. --}}
@section('title', \App\Support\PageSeo::title($seoPageKey, $regionSeoTitle ?: ($sectionTitle . ' — ' . __('seo.site_name'))))
@section('description', \App\Support\PageSeo::description($seoPageKey, $regionSeoDescription ?: __('ui.tours_meta_description', ['section' => $sectionTitle])))

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

{{-- ───────── HERO ───────── --}}
<section class="relative isolate text-white">
    <div class="absolute inset-0 -z-10">
        <img src="{{ asset('assets/banners/' . $bannerImg) }}" alt="" class="w-full h-full object-cover" loading="eager" fetchpriority="high">
        <div class="absolute inset-0 bg-gradient-to-b from-teal-900/40 via-teal-900/50 to-teal-900/70"></div>
    </div>

    <div class="container mx-auto px-5 lg:px-10">
        <nav aria-label="Breadcrumb" class="pt-6 text-xs text-white/85">
            <a href="{{ route('home', ['locale' => $locale]) }}" class="hover:text-orange-400">{{ __('ui.home') }}</a> &gt; <span>{{ $sectionTitle }}</span>
        </nav>
    </div>

    <div class="container mx-auto px-5 lg:px-10 pt-12 pb-16 md:pb-24 max-w-3xl">
        {{-- Pill: TOURS SELECCIONADOS EN PERÚ --}}
        <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-black/25 backdrop-blur-sm pl-3.5 pr-4 py-2 mb-5">
            <svg class="w-3.5 h-3.5 text-amber-400 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.5 8.5L22 12l-8.5 1.5L12 22l-1.5-8.5L2 12l8.5-1.5L12 2z"/></svg>
            <span class="text-[11px] sm:text-xs uppercase tracking-[0.18em] text-amber-200 font-bold">{{ $L('Tours seleccionados en Perú', 'Selected tours in Peru', 'Tours selecionados no Peru') }}</span>
        </span>

        <h1 class="tours-hero__title font-display font-normal text-[40px] leading-[1.02] sm:text-[54px] sm:leading-[0.98] md:text-[64px] lg:text-[72px] lg:leading-[0.96]">
            {{ $L('Descubre experiencias con un estilo más', 'Discover experiences with a more', 'Descubra experiências com um estilo mais') }}
            <span class="italic text-orange-400 font-light">premium.</span>
        </h1>

        {{-- Buscador (estilo Image #10) --}}
        <div class="mt-8 bg-white text-teal-800 rounded-3xl shadow-2xl ring-1 ring-black/5 p-4 sm:p-5 max-w-2xl">
            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-teal-800/60 mb-3">{{ $L('Buscar experiencia', 'Search experience', 'Buscar experiência') }}</p>
            <form method="GET" action="{{ route('tours.results', ['locale' => $locale]) }}" class="flex items-center gap-2.5" role="search">
                <div class="relative flex-1 min-w-0">
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-teal-800/40" aria-hidden="true">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                    </span>
                    <input type="search" name="q" placeholder="{{ $L('Buscar tour...', 'Search a tour...', 'Buscar tour...') }}"
                           class="w-full rounded-2xl border border-teal-800/15 bg-cream-100 pl-11 pr-3 py-3.5 text-base text-teal-800 placeholder-teal-800/40 focus:border-teal-600 focus:ring-1 focus:ring-teal-600 outline-none"
                           aria-label="{{ $L('Buscar tour', 'Search a tour', 'Buscar tour') }}">
                </div>
                <button type="submit" class="shrink-0 bg-teal-800 hover:bg-teal-900 text-white font-bold text-sm rounded-2xl px-6 py-3.5 transition">{{ $L('Buscar', 'Search', 'Buscar') }}</button>
            </form>
            <div class="mt-3 flex items-center gap-2 overflow-x-auto scrollbar-hide -mx-1 px-1">
                <a href="{{ route('tours.index', ['locale' => $locale]) }}"
                   class="shrink-0 rounded-full text-sm font-bold px-5 py-2.5 transition {{ !$cat ? 'bg-teal-800 text-white' : 'bg-cream-100 text-teal-800 ring-1 ring-teal-800/10 hover:bg-cream-200' }}">{{ $L('Todos', 'All', 'Todos') }}</a>
                @foreach (['lima' => 'Lima', 'ica' => 'Ica', 'cusco' => 'Cusco'] as $rSlug => $rLabel)
                    <a href="{{ route('tours.category', ['locale' => $locale, 'categoria' => $rSlug]) }}"
                       class="shrink-0 rounded-full text-sm font-bold px-5 py-2.5 transition {{ $cat === $rSlug ? 'bg-teal-800 text-white' : 'bg-cream-100 text-teal-800 ring-1 ring-teal-800/10 hover:bg-cream-200' }}">{{ $rLabel }}</a>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ───────── GRID DE TOURS + FILTROS (Tarea D) ───────── --}}
<section class="bg-white py-16 lg:py-20" id="catalogo">
    <div class="container mx-auto px-5 lg:px-10">
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
                        <button type="button" data-filters-clear class="text-xs font-semibold text-orange-600 hover:text-orange-700 underline underline-offset-2">
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
                        <button type="button" data-filters-clear class="text-xs font-semibold text-orange-600 hover:text-orange-700 underline underline-offset-2 shrink-0">
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
                {{-- Barra: botón Filtros (<lg) + contador + orden --}}
                <div class="flex flex-wrap items-center gap-3 mb-6">
                    <button type="button" data-filters-open aria-haspopup="dialog" aria-expanded="false"
                            class="lg:hidden inline-flex items-center gap-2 rounded-full border border-teal-800/15 bg-cream-100 px-5 py-2.5 text-sm font-bold text-teal-800 hover:border-orange-400 transition">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18M6 9.75h12M10.5 15h3"/>
                        </svg>
                        {{ __('ui.filters') }}
                    </button>

                    @php
                        $tourCountKey = $totalToursCount === 0
                            ? 'ui.tours_found_zero'
                            : ($totalToursCount === 1 ? 'ui.tours_found_one' : 'ui.tours_found_other');
                    @endphp
                    <p data-tour-count aria-live="polite" class="order-3 lg:order-none w-full lg:w-auto text-sm text-teal-800/70 font-medium">
                        {{ __($tourCountKey, ['count' => $totalToursCount]) }}
                    </p>

                    <label class="ml-auto flex items-center gap-2 text-sm text-teal-800/80">
                        <span class="hidden sm:inline">{{ __('ui.sort_by') }}</span>
                        <select data-sort class="rounded-xl border-teal-800/15 text-sm text-teal-800 focus:border-teal-600 focus:ring-1 focus:ring-teal-600">
                            <option value="recommended">{{ __('ui.sort_recommended') }}</option>
                            <option value="price-asc">{{ __('ui.sort_price_asc') }}</option>
                            <option value="price-desc">{{ __('ui.sort_price_desc') }}</option>
                            <option value="duration">{{ __('ui.sort_duration') }}</option>
                            <option value="name">{{ __('ui.sort_name') }}</option>
                        </select>
                    </label>
                </div>

                {{-- MOBILE (<640): cards horizontales tipo lista --}}
                <div class="flex flex-col gap-4 sm:hidden" data-tour-cards>
                    @foreach ($cardData as $i => $tour)
                        <x-tour-card-row
                            data-tour-card
                            data-index="{{ $i }}"
                            data-price="{{ $tour['now'] }}"
                            data-duration="{{ $tour['durationBucket'] }}"
                            data-duration-weight="{{ $tour['durationWeight'] }}"
                            data-languages="{{ implode(',', $tour['languageCodes']) }}"
                            data-group="{{ $tour['groupSlug'] }}"
                            data-region="{{ $tour['regionSlug'] }}"
                            data-offer="{{ $tour['isOffer'] ? '1' : '0' }}"
                            data-name="{{ $tour['nameSort'] }}"
                            :title="$tour['title']"
                            :slug="$tour['slug']"
                            :img="$tour['img']"
                            :before="$tour['before']"
                            :now="$tour['now']"
                            :rating="$tour['rating']"
                            :reviews="$tour['reviews']"
                            :duration="$tour['duration']"
                            :language="$tour['lang']"
                            :badge="$tour['badge']"
                            :badge-type="$tour['badgeType']"
                            :showBestSeller="$tour['showBestSeller']"
                            :showOffer="$tour['showOffer']"
                            :location-label="$cat ? ucfirst($cat) : null"
                            currency="US$"
                        />
                    @endforeach
                </div>

                {{-- TABLET / DESKTOP (≥640): grid de cards verticales --}}
                <div class="hidden sm:grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-tour-cards>
                    @foreach ($cardData as $i => $tour)
                        <x-tour-card
                            data-tour-card
                            data-index="{{ $i }}"
                            data-price="{{ $tour['now'] }}"
                            data-duration="{{ $tour['durationBucket'] }}"
                            data-duration-weight="{{ $tour['durationWeight'] }}"
                            data-languages="{{ implode(',', $tour['languageCodes']) }}"
                            data-group="{{ $tour['groupSlug'] }}"
                            data-region="{{ $tour['regionSlug'] }}"
                            data-offer="{{ $tour['isOffer'] ? '1' : '0' }}"
                            data-name="{{ $tour['nameSort'] }}"
                            :title="$tour['title']"
                            :slug="$tour['slug']"
                            :img="$tour['img']"
                            :before="$tour['before']"
                            :now="$tour['now']"
                            :rating="$tour['rating']"
                            :reviews="$tour['reviews']"
                            :duration="$tour['duration']"
                            :language="$tour['lang']"
                            :badge="$tour['badge']"
                            :badge-type="$tour['badgeType']"
                            :showBestSeller="$tour['showBestSeller']"
                            :showOffer="$tour['showOffer']"
                            currency="US$"
                        />
                    @endforeach
                </div>

                {{-- Estado vacío (0 resultados tras filtrar) --}}
                <div data-tour-empty class="hidden text-center py-16">
                    <p class="text-teal-800/70">{{ __('ui.no_tours_found') }}</p>
                    <button type="button" data-filters-clear class="mt-4 inline-flex items-center rounded-full bg-teal-900 hover:bg-teal-950 text-white px-6 py-2.5 text-sm font-semibold transition">
                        {{ __('ui.clear_filters') }}
                    </button>
                </div>

                <div class="mt-12 flex justify-center">
                    <button type="button" class="btn--primary-outline">{{ __('ui.load_more_tours') }}</button>
                </div>
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
                <span class="block text-xs text-teal-800/60">{{ __('ui.verified_client') }}</span>
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
                        <p class="mt-3 text-orange-400 text-sm" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733; <span class="text-teal-800/60">Google</span></p>
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
                <p class="mt-4 inline-flex items-center gap-2 text-sm">
                    <span class="font-semibold">4.8</span>
                    <span class="text-orange-400">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
                </p>
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
