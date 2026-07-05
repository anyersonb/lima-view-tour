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
    $bannerImg = $banners[$cat ?? null] ?? 'banner-hero.jpg';

    $toursCollection = isset($tours) && method_exists($tours, 'map') ? $tours : collect();

    if ($toursCollection->isEmpty()) {
        $toursCollection = collect([
            (object)['title_es' => 'Tour de día Completo al Oasis de Huacachina + Islas Ballestas en Paracas', 'price_before' => 125, 'price' => 100, 'badge_text' => 'CUPOS LIMITADOS', 'badge_type' => 'warn', 'rating' => 4.6, 'reviews_count' => 30, 'duration' => 'Full Day', 'language' => 'ES/EN', 'group_type' => 'Grupal', 'cover_image' => 'assets/banners/Rectangle 19210.jpg', 'slug' => 'huacachina-paracas-full-day'],
            (object)['title_es' => 'Full Day Lima Ancestral, Colonial y Moderna', 'price_before' => 125, 'price' => 100, 'badge_text' => '5 CUPOS DE 20', 'badge_type' => 'error', 'rating' => 4.8, 'reviews_count' => 28, 'duration' => 'Full Day', 'language' => 'ES/EN', 'group_type' => 'Grupal', 'cover_image' => 'assets/banners/Rectangle 19211.jpg', 'slug' => 'lima-ancestral-colonial'],
            (object)['title_es' => 'Las Enigmáticas Líneas de Nazca + Oasis de Huacachina e Islas Ballestas', 'price_before' => 250, 'price' => 220, 'badge_text' => 'MÁS RESERVADO', 'badge_type' => 'success', 'rating' => 4.6, 'reviews_count' => 30, 'duration' => '2 Días', 'language' => 'ES/EN', 'group_type' => 'Grupal', 'cover_image' => 'assets/banners/Rectangle 19212.jpg', 'slug' => 'nazca-huacachina-2-dias'],
            (object)['title_es' => 'Full day a las Líneas de Nazca', 'price_before' => 350, 'price' => 300, 'badge_text' => 'CUPOS LIMITADOS', 'badge_type' => 'warn', 'rating' => 4.8, 'reviews_count' => 28, 'duration' => 'Full Day', 'language' => 'ES/EN', 'group_type' => 'Grupal', 'cover_image' => 'assets/banners/Rectangle 19214.jpg', 'slug' => 'nazca-full-day'],
            (object)['title_es' => 'City Tour Lima + Catacumbas y Centro Histórico', 'price_before' => 80, 'price' => 65, 'badge_text' => 'NUEVO TOUR', 'badge_type' => 'success', 'rating' => 4.7, 'reviews_count' => 18, 'duration' => '4 horas', 'language' => 'ES/EN', 'group_type' => 'Grupal', 'cover_image' => 'assets/banners/Rectangle 19215.jpg', 'slug' => 'city-tour-lima-catacumbas'],
            (object)['title_es' => 'Machu Picchu Full Day + Tren Panorámico desde Cusco', 'price_before' => 480, 'price' => 420, 'badge_text' => 'EXPERIENCIA TOP', 'badge_type' => 'success', 'rating' => 4.9, 'reviews_count' => 65, 'duration' => 'Full Day', 'language' => 'ES/EN', 'group_type' => 'Grupal', 'cover_image' => 'assets/banners/Rectangle 19217.jpg', 'slug' => 'machu-picchu-full-day'],
        ]);
    }

    $cardData = $toursCollection->map(function ($t) {
        $img = is_object($t) ? ($t->cover_image ?? '') : ($t['cover_image'] ?? '');
        return [
            'title' => is_object($t) ? ($t->title ?? $t->title_es ?? '') : ($t['title'] ?? $t['title_es'] ?? ''),
            'before' => is_object($t) ? ($t->price_before ?? null) : ($t['price_before'] ?? null),
            'now' => is_object($t) ? $t->price : ($t['price'] ?? 0),
            'badge' => is_object($t) ? ($t->badge_text ?? null) : ($t['badge_text'] ?? null),
            'badgeType' => is_object($t) ? ($t->badge_type ?? 'warn') : ($t['badge_type'] ?? 'warn'),
            'rating' => is_object($t) ? $t->rating : ($t['rating'] ?? 0),
            'reviews' => is_object($t) ? ($t->reviews_count ?? 0) : ($t['reviews_count'] ?? 0),
            'duration' => is_object($t) ? ($t->duration ?? '') : ($t['duration'] ?? ''),
            'lang' => is_object($t) ? ($t->language ?? '') : ($t['language'] ?? ''),
            'group' => is_object($t) ? ($t->group_type ?? '') : ($t['group_type'] ?? ''),
            'img' => \App\Support\ImagePath::url($img) ?? asset('assets/banners/banner-hero.jpg'),
            'slug' => is_object($t) ? ($t->slug ?? '') : ($t['slug'] ?? ''),
            'showBestSeller' => (bool) (is_object($t) ? ($t->show_best_seller ?? true) : ($t['show_best_seller'] ?? true)),
            'showOffer'      => (bool) (is_object($t) ? ($t->show_offer_badge ?? true) : ($t['show_offer_badge'] ?? true)),
        ];
    });
@endphp

@section('title', $sectionTitle . ' — ' . __('seo.site_name'))
@section('description', __('ui.tours_meta_description', ['section' => $sectionTitle]))

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

{{-- ───────── GRID DE TOURS ───────── --}}
<section class="bg-white py-16 lg:py-20" id="catalogo">
    <div class="container mx-auto px-5 lg:px-10">
        {{-- Encabezado + filtros: ocultos en mobile, visibles desde md --}}
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

        {{-- MOBILE (<640): cards horizontales tipo lista --}}
        <div class="flex flex-col gap-4 sm:hidden">
            @foreach ($cardData as $tour)
                <x-tour-card-row
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
        <div class="hidden sm:grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($cardData as $tour)
                <x-tour-card
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

        <div class="mt-12 flex justify-center">
            <button type="button" class="btn--primary-outline">{{ __('ui.load_more_tours') }}</button>
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
