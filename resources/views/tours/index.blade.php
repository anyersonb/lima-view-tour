@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $cat = $categoria ?? null;
    $titles = [
        'lima'  => 'Tours en Lima',
        'ica'   => 'Tours en Ica',
        'cusco' => 'Tours en Cusco',
        null    => 'Todos nuestros tours',
    ];
    $banners = [
        'lima'  => 'Rectangle 19216.jpg',
        'ica'   => 'Rectangle 19219.jpg',
        'cusco' => 'Rectangle 19218.jpg',
        null    => 'banner-hero.jpg',
    ];
    $eyebrow = [
        'lima'  => 'EXPLORA LA CAPITAL',
        'ica'   => 'AVENTURA EN EL DESIERTO',
        'cusco' => 'CIUDADELA SAGRADA',
        null    => 'NUESTRO CATÁLOGO',
    ][$cat ?? null] ?? 'NUESTRO CATÁLOGO';
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
            'img' => $img ? (\Illuminate\Support\Str::startsWith($img, ['http', '/']) ? $img : asset($img)) : asset('assets/banners/banner-hero.jpg'),
            'slug' => is_object($t) ? ($t->slug ?? '') : ($t['slug'] ?? ''),
        ];
    });
@endphp

@section('title', $sectionTitle . ' — ' . __('seo.site_name'))
@section('description', 'Descubre nuestros tours por ' . $sectionTitle . '. Itinerarios con guías expertos, transporte cómodo y experiencias auténticas en Perú.')

@section('content')

{{-- ───────── HERO ───────── --}}
<section class="relative isolate text-white">
    <div class="absolute inset-0 -z-10">
        <img src="{{ asset('assets/banners/' . $bannerImg) }}" alt="" class="w-full h-full object-cover" loading="eager" fetchpriority="high">
        <div class="absolute inset-0 bg-gradient-to-b from-teal-900/40 via-teal-900/50 to-teal-900/70"></div>
    </div>

    <div class="container mx-auto px-5 lg:px-10">
        <nav aria-label="Breadcrumb" class="pt-6 text-xs text-white/85">
            <a href="{{ route('home', ['locale' => $locale]) }}" class="hover:text-orange-400">Inicio</a> &gt; <span>{{ $sectionTitle }}</span>
        </nav>
    </div>

    <div class="container mx-auto px-5 lg:px-10 pt-12 pb-20 md:pb-28 text-center max-w-4xl">
        <p class="text-[11px] uppercase tracking-[0.25em] font-semibold opacity-90">{{ $eyebrow }}</p>
        <h1 class="mt-4 font-display font-normal text-5xl md:text-6xl lg:text-7xl leading-[1.05]">
            {{ $sectionTitle }}
        </h1>
        <p class="mt-5 mx-auto max-w-2xl text-sm md:text-base text-white/85">
            Vive una aventura inolvidable. Tours diseñados con seguridad, comodidad y guías expertos.
        </p>

        <form action="{{ route('tours.results', ['locale' => $locale]) }}" method="get"
              class="mt-10 mx-auto max-w-3xl bg-white text-teal-800 rounded-pill shadow-2xl flex items-center gap-2 p-2">
            <label class="flex-1 flex items-center gap-3 px-4">
                <svg class="w-5 h-5 text-teal-700/60" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/>
                </svg>
                <span class="sr-only">Buscar tour</span>
                <input type="search" name="q" placeholder="¿Qué experiencia buscas?"
                       class="w-full bg-transparent border-0 focus:ring-0 placeholder:text-teal-700/50 text-base">
            </label>
            <button type="submit" class="btn--primary !py-3.5 !px-8">Buscar</button>
        </form>
    </div>
</section>

{{-- ───────── GRID DE TOURS ───────── --}}
<section class="bg-white py-16 lg:py-20" id="catalogo">
    <div class="container mx-auto px-5 lg:px-10">
        <header class="flex flex-col md:flex-row md:items-end md:justify-between gap-6 mb-10">
            <div>
                <p class="text-[11px] uppercase tracking-[0.2em] text-teal-800/70 font-semibold">CATÁLOGO</p>
                <h2 class="mt-2 font-display text-3xl md:text-4xl text-teal-800 leading-tight">Todos nuestros {{ $cat ? 'tours en ' . ucfirst($cat) : 'tours' }}</h2>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('tours.index', ['locale' => $locale]) }}"
                   class="px-5 py-2.5 rounded-pill text-xs font-semibold uppercase tracking-wide border transition
                   {{ !$cat ? 'bg-teal-700 text-white border-teal-700' : 'bg-cream-100 text-teal-800 border-teal-800/10 hover:border-orange-400' }}">
                    Todos
                </a>
                @foreach (['lima', 'ica', 'cusco'] as $r)
                    <a href="{{ route('tours.category', ['locale' => $locale, 'categoria' => $r]) }}"
                       class="px-5 py-2.5 rounded-pill text-xs font-semibold uppercase tracking-wide border transition
                       {{ $cat === $r ? 'bg-teal-700 text-white border-teal-700' : 'bg-cream-100 text-teal-800 border-teal-800/10 hover:border-orange-400' }}">
                        {{ ucfirst($r) }}
                    </a>
                @endforeach
            </div>
        </header>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($cardData as $tour)
                <article class="group bg-white rounded-2xl overflow-hidden shadow-sm ring-1 ring-teal-800/5 flex flex-col">
                    <a href="{{ route('tours.show', ['locale' => $locale, 'slug' => $tour['slug']]) }}" class="relative block">
                        <img src="{{ $tour['img'] }}" alt="{{ $tour['title'] }}"
                             class="w-full h-52 object-cover transition-transform group-hover:scale-105" loading="lazy">
                        @php
                            $badgeColor = match ($tour['badgeType']) {
                                'error' => 'bg-state-error text-white',
                                'success' => 'bg-state-success text-white',
                                default => 'bg-orange-500 text-white',
                            };
                        @endphp
                        @if ($tour['badge'])
                            <span class="absolute top-3 left-3 {{ $badgeColor }} text-[10px] font-semibold uppercase tracking-wider px-3 py-1 rounded">
                                {{ $tour['badge'] }}
                            </span>
                        @endif
                    </a>

                    <div class="p-5 flex flex-col flex-1">
                        <div class="flex items-center gap-2 text-xs text-teal-800/70">
                            <span class="text-orange-400" aria-hidden="true">&#9733;</span>
                            <span class="font-semibold text-teal-800">{{ $tour['rating'] }}</span>
                            <span>({{ $tour['reviews'] }} reseñas)</span>
                        </div>

                        <h3 class="mt-3 font-display text-lg text-teal-800 leading-snug">
                            <a href="{{ route('tours.show', ['locale' => $locale, 'slug' => $tour['slug']]) }}" class="hover:text-orange-500 transition-colors">
                                {{ $tour['title'] }}
                            </a>
                        </h3>

                        <ul class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-teal-800/65">
                            <li>&#128100; {{ $tour['lang'] }}</li>
                            <li>&#9202; {{ $tour['duration'] }}</li>
                            <li>&#128101; {{ $tour['group'] }}</li>
                        </ul>

                        <div class="mt-auto pt-5 flex items-end justify-between">
                            <div>
                                <p class="text-xs text-teal-800/60 line-through">${{ $tour['before'] }}</p>
                                <p class="font-price text-2xl text-teal-800 leading-none">${{ $tour['now'] }}<span class="text-[10px] uppercase tracking-[0.2em] text-teal-800/60 font-sans block mt-1">POR PERSONA</span></p>
                            </div>
                            <a href="{{ route('tours.show', ['locale' => $locale, 'slug' => $tour['slug']]) }}" class="btn--primary !py-2.5 !px-5 text-xs">Reservar</a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-12 flex justify-center">
            <button type="button" class="btn--primary-outline">Cargar más tours</button>
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
                <span class="block text-xs text-teal-800/60">Cliente verificado</span>
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
                <p class="text-[11px] uppercase tracking-[0.2em] font-semibold">CLIENTES SATISFECHOS</p>
                <h3 class="mt-3 font-display text-3xl lg:text-4xl leading-tight">Nuestros clientes opinan de nuestros tours</h3>
                <p class="mt-4 inline-flex items-center gap-2 text-sm">
                    <span class="font-semibold">4.8</span>
                    <span class="text-orange-400">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
                </p>
                <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="btn--primary mt-auto self-start">Ver tours</a>
            </div>
        </article>
    </div>
</section>

{{-- ───────── ¿POR QUÉ RESERVAR CON NOSOTROS? ───────── --}}
<section class="bg-white py-16 lg:py-20">
    <div class="container mx-auto px-5 lg:px-10">
        <header class="text-center max-w-2xl mx-auto mb-12">
            <h2 class="font-display text-3xl md:text-4xl lg:text-5xl text-teal-800 leading-tight">¿Por qué reservar con nosotros?</h2>
            <p class="mt-3 text-teal-800/70 text-sm">Cuatro razones para confiarnos tu próxima aventura por Perú.</p>
        </header>

        <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Experiencias Únicas', 'Itinerarios diseñados para que descubras lo mejor de cada destino, lejos de las rutas masificadas.'],
                ['Turismo responsable', 'Trabajamos con comunidades y aliados que respetan el patrimonio y el medio ambiente.'],
                ['Diversos paquetes', 'Encuentra el plan ideal para cada presupuesto: aventura, cultural, gastronómico o premium.'],
                ['Guías profesionales', 'Equipo local certificado, apasionado y con dominio de español e inglés.'],
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
