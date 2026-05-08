@extends('layouts.app')

@section('title', __('seo.home_title'))
@section('description', __('seo.home_description'))

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
    $brujula = '<svg viewBox="0 0 64 64" class="w-12 h-12 text-teal-700" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.2"><circle cx="32" cy="32" r="22"/><circle cx="32" cy="32" r="14"/><path d="M32 14 L34 30 L50 32 L34 34 L32 50 L30 34 L14 32 L30 30 Z" fill="currentColor" opacity=".15" stroke="none"/><path d="M32 16v3M32 45v3M16 32h3M45 32h3"/></svg>';

    $featuredTours = $featuredTours ?? collect();
    $toursIca      = $toursIca      ?? collect();
    $toursLima     = $toursLima     ?? collect();
    $toursCusco    = $toursCusco    ?? collect();
    $regions       = $regions       ?? collect();
    $testimonials  = $testimonials  ?? collect();
    $offers        = $offers        ?? collect();
    $st            = $siteSettings  ?? [];

    // Static fallback cards shown when a DB collection is empty.
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

    /**
     * Normalize a collection (Eloquent models or plain objects) into a flat
     * array of view-friendly scalars understood by the tour-card partial.
     */
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
                    ? (\Illuminate\Support\Str::contains($t->cover_image ?? '', '/') ? basename($t->cover_image) : ($t->cover_image ?? 'banner-hero.jpg'))
                    : (\Illuminate\Support\Str::contains($t['cover_image'] ?? '', '/') ? basename($t['cover_image']) : ($t['cover_image'] ?? 'banner-hero.jpg')),
                'slug'      => is_object($t) ? ($t->slug ?? '')                                  : ($t['slug'] ?? ''),
            ];
        })->all();
    };

    $toursCarousels = [
        ['eyebrow' => 'ENJOY WORLD-CLASS STAY EXPERIENCE', 'title' => 'Nuestros Tours más Comprados', 'bg' => 'bg-cream-100', 'items' => $normalizeTours($featuredTours)],
        ['eyebrow' => 'ENJOY WORLD-CLASS STAY EXPERIENCE', 'title' => 'Nuestros Destinos en Ica',     'bg' => 'bg-cream-100', 'items' => $normalizeTours($toursIca)],
        ['eyebrow' => 'ENJOY WORLD-CLASS STAY EXPERIENCE', 'title' => 'Nuestros Destinos en Lima',    'bg' => 'bg-cream-100', 'items' => $normalizeTours($toursLima)],
        ['eyebrow' => 'ENJOY WORLD-CLASS STAY EXPERIENCE', 'title' => 'Nuestros Destinos en Cusco',   'bg' => 'bg-cream-100', 'items' => $normalizeTours($toursCusco)],
    ];
@endphp

@section('content')

{{-- ───────── HERO ───────── --}}
<section class="home-hero relative isolate overflow-hidden text-white" aria-labelledby="hero-title">
    <div class="absolute inset-0 -z-10">
        <img src="{{ asset('assets/banners/banner-hero.jpg') }}" alt=""
             class="w-full h-full object-cover" loading="eager" fetchpriority="high">
        <div class="absolute inset-0 bg-gradient-to-b from-teal-900/30 via-teal-900/40 to-teal-900/65"></div>
    </div>

    <div class="container mx-auto px-5 lg:px-10 pt-16 lg:pt-24 pb-32 lg:pb-40 text-center max-w-5xl">
        <p class="inline-flex items-center gap-3 text-sm font-medium">
            <span class="font-semibold">4.8</span>
            <span class="flex gap-0.5 text-orange-400" aria-hidden="true">
                <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
            </span>
            <span class="hidden sm:inline-flex -space-x-2 ml-2" aria-hidden="true">
                @for ($i=1;$i<=5;$i++)
                    <span class="w-6 h-6 rounded-full ring-2 ring-white/80 bg-cream-200/80 inline-block"></span>
                @endfor
            </span>
            <span class="ml-2">+2000 Clientes han probado nuestros tours</span>
        </p>

        <h1 id="hero-title" class="mt-6 font-display font-normal text-5xl md:text-6xl lg:text-7xl leading-[1.05]">
            Descubre la magia de Perú,<br>un viaje que transforma
        </h1>

        <p class="mt-6 mx-auto max-w-2xl text-base md:text-lg text-white/85">
            Vive una aventura inolvidable por los destinos más impresionantes del Perú. Desde Machu Picchu hasta la Huacachina, nuestros tours están diseñados para que disfrutes lo mejor del país con seguridad, comodidad y guías expertos.
        </p>

        <form action="{{ route('tours.index', ['locale' => $locale]) }}" method="get"
              class="home-hero__search mt-10 mx-auto max-w-3xl bg-white text-teal-800 rounded-pill shadow-2xl flex items-center gap-2 p-2">
            <label class="flex-1 flex items-center gap-3 px-4">
                <svg class="w-5 h-5 text-teal-700/60" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/>
                </svg>
                <span class="sr-only">{{ __('common.search') }}</span>
                <input type="search" name="q" placeholder="¿A dónde quieres ir?"
                       class="w-full bg-transparent border-0 focus:ring-0 placeholder:text-teal-700/50 text-base">
            </label>
            <button type="submit" class="btn--primary !py-3.5 !px-8">
                Reservar tour
            </button>
        </form>
    </div>
</section>

{{-- ───────── STATS ───────── --}}
<section class="bg-teal-700 text-white" aria-labelledby="stats-title">
    <h2 id="stats-title" class="sr-only">Estadísticas</h2>
    <div class="container mx-auto px-5 lg:px-10 py-10 grid grid-cols-2 md:grid-cols-4 gap-y-8 gap-x-6 text-center divide-y md:divide-y-0 md:divide-x divide-white/10">
        @foreach ([
            [$st['stats_travelers'] ?? '+824', 'Viajeros felices'],
            [$st['stats_years'] ?? '+11', 'Años de experiencia brindando servicios turísticos'],
            [$st['stats_rating'] ?? '4.8', 'En reseñas verificadas'],
            [$st['stats_tours'] ?? '+50', 'Tours y experiencias únicas'],
        ] as [$num, $label])
            <div class="px-4">
                <p class="font-display font-normal text-5xl lg:text-6xl">{{ $num }}</p>
                <p class="mt-2 text-xs lg:text-sm text-white/80 max-w-[14rem] mx-auto leading-snug">{{ $label }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- ───────── TOUR CAROUSELS (featured + 3 destinos) ───────── --}}
@foreach ($toursCarousels as $sectionIdx => $section)
<section class="{{ $section['bg'] }} py-16 lg:py-20" aria-labelledby="carousel-{{ $sectionIdx }}-title">
    <div class="container mx-auto px-5 lg:px-10">
        <div class="flex items-center gap-4 md:gap-5 mb-8 md:mb-10">
            <x-icon-compass class="w-12 h-12 lg:w-[58px] lg:h-[60px] text-teal-700 shrink-0" />
            <div>
                <p class="text-[11px] uppercase tracking-[0.2em] text-teal-800/70 font-semibold">{{ $section['eyebrow'] }}</p>
                <h2 id="carousel-{{ $sectionIdx }}-title" class="font-display text-3xl md:text-4xl lg:text-5xl text-teal-800 leading-tight mt-1">{{ $section['title'] }}</h2>
            </div>
        </div>

        <div class="owl-carousel owl-theme owl-tours-wide" data-owl-tours-wide x-ignore>
            @foreach ($section['items'] as $i => $tour)
                <article class="item tour-card bg-white rounded-2xl shadow-sm overflow-hidden grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_minmax(0,40%)] h-full">
                    <div class="p-5 md:p-6 flex flex-col">
                        @if ($tour['badge'])
                            <span class="text-[11px] uppercase tracking-[0.2em] text-orange-500 font-semibold">{{ $tour['badge'] }}</span>
                        @endif
                        <h3 class="font-display text-xl md:text-2xl text-teal-800 leading-snug mt-1">{{ $tour['title'] }}</h3>
                        <p class="mt-3 flex items-center gap-2 text-sm text-teal-800/80">
                            <span class="font-semibold">{{ $tour['rating'] }}</span>
                            <span aria-hidden="true" class="text-orange-400 tracking-tight">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
                            <span class="text-teal-800/60 text-xs">( {{ $tour['reviews'] }} Comentarios )</span>
                        </p>

                        <ul class="mt-4 grid grid-cols-4 gap-2 text-[10px] text-teal-800/70 text-center">
                            @foreach (['Español/Inglés','Full Day','Tour Grupal','Recojo y retorno'] as $feat)
                                <li class="flex flex-col items-center gap-1">
                                    <span class="w-8 h-8 rounded-full border border-teal-800/20 grid place-items-center text-teal-700" aria-hidden="true">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/></svg>
                                    </span>
                                    <span>{{ $feat }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <div class="mt-auto pt-5">
                            <div class="rounded-lg border border-teal-800/15 overflow-hidden">
                                <p class="bg-teal-700 text-white text-[10px] tracking-[0.2em] uppercase text-center py-1.5">PRECIO POR PERSONA</p>
                                <p class="flex items-center justify-around py-3 text-sm">
                                    <span><span class="text-[11px] tracking-[0.2em] uppercase text-teal-800/60">Antes</span> <span class="font-price text-base text-teal-800/60 line-through">${{ $tour['before'] }}</span></span>
                                    <span><span class="text-[11px] tracking-[0.2em] uppercase text-teal-800/60">Ahora</span> <span class="font-price text-2xl text-state-error">${{ $tour['now'] }}</span></span>
                                </p>
                            </div>
                            <a href="{{ $tour['slug'] ? route('tours.show', ['locale' => $locale, 'slug' => $tour['slug']]) : '#' }}" class="btn--primary btn--block mt-4">Reservar tour</a>
                        </div>
                    </div>
                    <div class="relative min-h-[14rem] sm:min-h-0">
                        <img src="{{ asset('assets/banners/' . $tour['img']) }}" alt="{{ $tour['title'] }}"
                             class="absolute inset-0 w-full h-full object-cover" loading="lazy">
                        @if ($tour['badge'])
                            @php
                                $badgeBg = match($tour['badgeType']) {
                                    'success' => 'bg-state-success',
                                    'error'   => 'bg-state-error',
                                    default   => 'bg-orange-400',
                                };
                            @endphp
                            <span class="absolute top-4 left-4 right-4 {{ $badgeBg }} text-white text-[10px] uppercase tracking-[0.15em] font-semibold py-1.5 px-3 rounded-md text-center inline-flex items-center justify-center gap-1.5">
                                {{ $tour['badge'] }}
                            </span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endforeach

{{-- ───────── ¿Qué tipo de tour estás buscando? ───────── --}}
<section class="bg-white py-16 lg:py-20" x-data="{ tab: 'cult' }">
    <div class="container mx-auto px-5 lg:px-10">
        <div class="flex items-center gap-4 md:gap-5 mb-8">
            <x-icon-compass class="w-12 h-12 lg:w-[58px] lg:h-[60px] text-teal-700 shrink-0" />
            <div>
                <p class="text-[11px] uppercase tracking-[0.2em] text-teal-800/70 font-semibold">ESTANCIAS</p>
                <h2 class="font-display text-3xl md:text-4xl lg:text-5xl text-teal-800 mt-1 leading-tight">¿Qué tipo de tour estás buscando?</h2>
            </div>
        </div>

        <div role="tablist" class="border-b border-teal-800/15 flex flex-wrap gap-x-8 gap-y-2 mb-8">
            @foreach ([
                ['cult','TOURS CULTURALES'],
                ['adv','TOURS DE AVENTURA'],
                ['cul','EXPERIENCIAS CULINARIAS'],
                ['oth','OTROS'],
            ] as [$id,$label])
                <button type="button" role="tab" :aria-selected="tab === '{{ $id }}'"
                        @click="tab = '{{ $id }}'"
                        class="py-3 text-sm font-semibold uppercase tracking-wide transition"
                        :class="tab === '{{ $id }}' ? 'text-orange-500 border-b-2 border-orange-500' : 'text-teal-800/60 hover:text-teal-800'">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <article class="grid gap-6 lg:grid-cols-2 bg-cream-100 rounded-2xl overflow-hidden">
            <img src="{{ asset('assets/banners/Rectangle 19215.jpg') }}" alt="Lima nocturna"
                 class="w-full h-72 lg:h-full object-cover" loading="lazy">
            <div class="p-8 lg:p-10 flex flex-col">
                <h3 class="font-display text-3xl text-teal-800">Nombre de tour</h3>
                <p class="text-[11px] uppercase tracking-[0.2em] text-orange-500 font-semibold mt-2">CUPOS LIMITADOS</p>
                <p class="mt-4 text-sm text-teal-800/75 leading-relaxed">
                    Lorem ipsum dolor sit amet consectetur. Tellus et sollicitudin sagittis. Blandit posuere ornare nisi tristique. Erat in quam ac rhoncus consectetur nulla ornare nunc. Ut non eleifend facilisis odio turpis at non consectetur. Blandit sollicitudin cras mattis faucibus. Nunc id est ac accumsan sodales. Vitae convallis ut in nisi interdum. In sed scelerisque varius faucibus quis. Venenatis blandit nunc ultricies luctus volutpat pulvinar.
                </p>
                <div class="mt-auto pt-6 flex items-center justify-between">
                    <p class="font-price text-3xl text-teal-800">$200<span class="block text-[11px] uppercase tracking-[0.2em] text-teal-800/60 font-sans">POR PERSONA</span></p>
                    <a href="#" class="btn--primary">Reservar tour</a>
                </div>
            </div>
        </article>
    </div>
</section>

{{-- ───────── EXPERIENCIAS ÚNICAS ───────── --}}
<section class="bg-cream-100 py-14 md:py-16 lg:py-20" aria-labelledby="experiences-title">
    <div class="container mx-auto px-5 lg:px-10">
        <div class="flex items-center gap-4 md:gap-5 mb-8 md:mb-10">
            <x-icon-compass class="w-10 h-10 md:w-12 md:h-12 lg:w-[58px] lg:h-[60px] text-teal-700 shrink-0" />
            <div>
                <p class="text-[11px] uppercase tracking-[0.2em] text-teal-800/70 font-semibold">EXPERIENCIAS</p>
                <h2 id="experiences-title" class="font-display text-3xl md:text-4xl lg:text-5xl text-teal-800 mt-1 leading-tight">Descubre experiencias únicas</h2>
            </div>
        </div>

        <div class="owl-carousel owl-theme owl-experiences" data-owl-experiences>
            @foreach (['Rectangle 19216.jpg','Rectangle 19217.jpg','Rectangle 19218.jpg','Rectangle 19219.jpg'] as $i => $img)
                <article class="item bg-white rounded-2xl overflow-hidden shadow-sm">
                    <img src="{{ asset('assets/banners/' . $img) }}" alt="Experiencia turística en {{ ['Lima', 'Cusco', 'Ica', 'Paracas'][$i] ?? 'Perú' }}" class="w-full h-56 object-cover" loading="lazy">
                    <div class="p-5">
                        <h3 class="font-display text-xl text-teal-800 leading-snug">Excursión de día completo en Lima</h3>
                        <ul class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-teal-800/70">
                            <li>&#128100; Español/Inglés</li>
                            <li>&#9202; Full Day</li>
                            <li>&#128101; Tour Grupal</li>
                        </ul>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ───────── TESTIMONIOS ───────── --}}
<section class="bg-cream-100 pb-14 md:pb-16 lg:pb-20" aria-labelledby="home-testimonials-title">
    <h2 id="home-testimonials-title" class="sr-only">Testimonios de clientes</h2>
    <div class="container mx-auto px-5 lg:px-10 grid gap-6 lg:grid-cols-[1fr_1fr_minmax(0,1.05fr)] items-stretch">
        <div class="lg:col-span-2 owl-carousel owl-theme owl-testimonials" data-owl-testimonials>
            @foreach ([['Sara Fernández','Spain'],['Rebeca Figueroa','Colombia'],['Liam Carter','USA'],['Ana Suárez','México']] as [$name,$country])
                <figure class="item bg-white rounded-2xl overflow-hidden flex flex-col h-full">
                    <div class="p-6">
                        <span class="text-orange-400 text-3xl leading-none" aria-hidden="true">”</span>
                        <blockquote class="mt-2 text-sm text-teal-800/85 leading-relaxed">
                            Lorem ipsum dolor sit amet consectetur. Dolor semper vehicula sit id nulla nibh senectus sit. Erat aliquet suspendisse purus consequat vestibulum gravida.
                        </blockquote>
                        <p class="mt-3 text-orange-400 text-sm" aria-hidden="true">★★★★★ <span class="text-teal-800/60">Google</span></p>
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
            <img src="{{ asset('assets/banners/Rectangle 19210.jpg') }}" alt=""
                 class="absolute inset-0 w-full h-full object-cover" loading="lazy">
            <div class="absolute inset-0 bg-gradient-to-t from-teal-900/85 via-teal-900/40 to-teal-900/20"></div>
            <div class="relative h-full p-8 flex flex-col">
                <p class="text-[11px] uppercase tracking-[0.2em] font-semibold">CLIENTES SATISFECHOS</p>
                <h3 class="mt-3 font-display text-3xl lg:text-4xl leading-tight">Nuestros Clientes Opinan de nuestros tours</h3>
                <p class="mt-4 inline-flex items-center gap-2 text-sm">
                    <span class="font-semibold">4.8</span>
                    <span class="text-orange-400">★★★★★</span>
                    <span class="ml-1 inline-flex -space-x-2" aria-hidden="true">
                        @for ($i=0;$i<5;$i++)<span class="w-5 h-5 rounded-full bg-cream-200/80 ring-2 ring-white/80"></span>@endfor
                    </span>
                </p>
                <a href="#" class="btn--primary mt-auto self-start">Ver tours</a>
            </div>
        </article>
    </div>
</section>

{{-- ───────── TRIPADVISOR QUOTE ───────── --}}
<section class="bg-cream-100 py-12 lg:py-16">
    <div class="container mx-auto px-5 lg:px-10 max-w-4xl text-center">
        <p class="text-teal-800/70 inline-flex items-center gap-2 text-sm uppercase tracking-[0.18em] font-semibold">
            <span aria-hidden="true">🦉</span> Tripadvisor
        </p>
        <blockquote class="mt-6 font-display text-2xl md:text-3xl text-teal-800 leading-snug">
            “Lorem ipsum dolor sit amet consectetur. Aliquet eget eu tellus libero ornare augue scelerisque eget ac. Velit consequat viverra sed tincidunt lacinia. Aliquam vitae auctor feugiat et suscipit viverra consectetur.”
        </blockquote>
        <figcaption class="mt-6 inline-flex items-center gap-3 text-left">
            <span class="w-10 h-10 rounded-full bg-cream-200"></span>
            <span class="leading-tight">
                <span class="block font-semibold text-teal-800">Valeriy Roberts</span>
                <span class="block text-xs text-teal-800/60">Ex clienta</span>
            </span>
        </figcaption>
        <div class="mt-8 flex justify-center gap-3">
            <button type="button" class="w-11 h-11 rounded-full bg-orange-500 text-white grid place-items-center" aria-label="Anterior">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button type="button" class="w-11 h-11 rounded-full bg-orange-500 text-white grid place-items-center" aria-label="Siguiente">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>
</section>

{{-- ───────── OFERTAS ESPECIALES ───────── --}}
<section class="relative isolate text-white py-16 lg:py-24">
    <div class="absolute inset-0 -z-10">
        <img src="{{ asset('assets/banners/banner-hero.jpg') }}" alt=""
             class="w-full h-full object-cover" loading="lazy">
        <div class="absolute inset-0 bg-teal-900/60"></div>
    </div>
    <div class="container mx-auto px-5 lg:px-10">
        <div class="flex items-center gap-4 md:gap-5 mb-8 md:mb-10 text-white">
            <x-icon-compass class="w-10 h-10 md:w-12 md:h-12 lg:w-[58px] lg:h-[60px] text-white shrink-0" stroke="#ffffff" />
            <div>
                <p class="text-[11px] uppercase tracking-[0.2em] font-semibold opacity-80">OFERTAS POR ANTICIPACIÓN</p>
                <h2 class="font-display text-3xl md:text-4xl lg:text-5xl mt-1 leading-tight">Ofertas Especiales</h2>
            </div>
        </div>

        <div class="owl-carousel owl-theme owl-offers" data-owl-offers>
            @foreach (['Rectangle 19211.jpg','Rectangle 19212.jpg','Rectangle 19214.jpg'] as $img)
                <article class="item relative rounded-2xl overflow-hidden min-h-[26rem] flex">
                    <img src="{{ asset('assets/banners/' . $img) }}" alt="" class="absolute inset-0 w-full h-full object-cover" loading="lazy">
                    <div class="absolute inset-0 bg-gradient-to-b from-black/30 via-black/55 to-black/80"></div>
                    <div class="relative w-full p-6 flex flex-col text-center">
                        <span class="self-start bg-white/95 text-teal-800 text-[11px] font-semibold uppercase tracking-wide rounded-md px-3 py-1">$200 / Por persona</span>
                        <div class="mt-auto pb-2">
                            <h3 class="font-display text-2xl">10% de dscto haciendo tu reserva</h3>
                            <p class="mt-3 text-sm text-white/85 leading-relaxed">Lorem ipsum dolor sit amet consectetur. Elit nibh dolor et turpis tempus. Urna aliquet lacus faucibus facilisis phasellus.</p>
                            <a href="#" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-wider border border-white/70 rounded-pill px-6 py-2.5 hover:bg-white hover:text-teal-800 transition">
                                Leer más <span aria-hidden="true">›</span>
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ───────── PORQUE RESERVAR CON NOSOTROS ───────── --}}
<section class="bg-white py-16 lg:py-20">
    <div class="container mx-auto px-5 lg:px-10 grid gap-10 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] items-center">
        <div class="grid grid-cols-2 gap-4">
            <img src="{{ asset('assets/images/personas.png') }}" alt="" class="w-full h-80 object-cover rounded-2xl" loading="lazy">
            <img src="{{ asset('assets/banners/Rectangle 19215.jpg') }}" alt="" class="w-full h-80 object-cover rounded-2xl translate-y-8" loading="lazy">
        </div>
        <div>
            <p class="text-[11px] uppercase tracking-[0.2em] text-teal-800/70 font-semibold inline-flex items-center gap-2">
                <span class="w-9 h-9 rounded-full border border-teal-800/20 grid place-items-center">
                    <svg class="w-5 h-5 text-teal-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><circle cx="12" cy="12" r="9"/></svg>
                </span>
                NOSOTROS
            </p>
            <h2 class="mt-3 font-display text-4xl lg:text-5xl text-teal-800 leading-tight">
                Porque reservar con<br>Nosotros es <span class="text-orange-500">la mejor opción</span> en Perú
            </h2>
            <p class="mt-3 inline-flex items-center gap-2 text-sm text-teal-800/70">
                <span aria-hidden="true">🦉</span> Tripadvisor
                <span class="font-semibold ml-2">4.8</span>
                <span class="text-orange-400">★★★★★</span>
            </p>
            <p class="mt-5 text-teal-800/80 leading-relaxed">
                Nuestro distintivo espiral, que parte de un punto central y se despliega con elegancia, simboliza nuestra conexión con el presente y el vibrante pasado de Perú. Este motivo visual representa nuestra misión esencial: cada tour es una auténtica travesía en el tiempo, una experiencia que conecta a nuestros visitantes con las raíces históricas y culturales del país.
            </p>
            <a href="#" class="btn--primary mt-6">Leer más</a>
        </div>
    </div>

    <div class="bg-teal-700 text-white mt-16 py-14">
        <div class="container mx-auto px-5 lg:px-10 grid gap-y-10 gap-x-12 sm:grid-cols-2 lg:grid-cols-2">
            @foreach ([
                ['Experiencias Únicas', 'Nuestra empresa se distingue por ofrecer experiencias únicas y vibrantes que trascienden lo ordinario.'],
                ['Responsabilidad y turismo sostenible', 'Nuestro distintivo espiral, que parte de un punto central, simboliza nuestra conexión con el presente y el vibrante pasado de Perú.'],
                ['Diversos paquetes', 'En nuestra agencia encontrarás una amplia variedad de paquetes de viaje diseñados para todos los gustos.'],
                ['Guías profesionales', 'Nuestras guías, apasionados expertos locales, no sólo te llevan a destinos fascinantes, sino que te sumergen en las épocas y eventos históricos.'],
            ] as [$title, $desc])
                <div class="flex gap-5">
                    <span class="w-12 h-12 rounded-full border border-white/40 grid place-items-center shrink-0">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/></svg>
                    </span>
                    <div>
                        <h3 class="font-display text-2xl">{{ $title }}</h3>
                        <p class="mt-2 text-sm text-white/80 leading-relaxed">{{ $desc }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ───────── DESTINOS MÁS VISITADOS ───────── --}}
<section class="bg-cream-100 py-16 lg:py-20">
    <div class="container mx-auto px-5 lg:px-10">
        <div class="text-center mb-10">
            <p class="text-[11px] uppercase tracking-[0.2em] text-teal-800/70 font-semibold">MÁS VISITADOS</p>
            <h2 class="mt-3 font-display text-3xl md:text-4xl lg:text-5xl text-teal-800 leading-tight">Descubre Las Ciudades<br>más Visitadas del Perú</h2>
        </div>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
            <a href="{{ route('tours.index', ['locale' => $locale]) }}#cusco"
               class="relative rounded-2xl overflow-hidden block min-h-[28rem] group">
                <img src="{{ asset('assets/banners/Rectangle 19218.jpg') }}" alt="Cusco"
                     class="absolute inset-0 w-full h-full object-cover transition-transform group-hover:scale-105" loading="lazy">
                <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                <div class="relative h-full flex items-end justify-between p-7 text-white">
                    <h3 class="font-display text-3xl">Tours en Cusco</h3>
                    <span class="border border-white/70 rounded-pill px-5 py-2 text-xs font-semibold uppercase tracking-wider">Ver tours ›</span>
                </div>
            </a>
            <div class="grid gap-5">
                @foreach ([['Tours en Lima','Rectangle 19216.jpg'],['Tours en Ica','Rectangle 19219.jpg']] as [$name,$img])
                    <a href="#" class="relative rounded-2xl overflow-hidden block min-h-[13.25rem] group">
                        <img src="{{ asset('assets/banners/' . $img) }}" alt="{{ $name }}"
                             class="absolute inset-0 w-full h-full object-cover transition-transform group-hover:scale-105" loading="lazy">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                        <div class="relative h-full flex items-end justify-between p-6 text-white">
                            <h3 class="font-display text-2xl">{{ $name }}</h3>
                            <span class="border border-white/70 rounded-pill px-5 py-2 text-xs font-semibold uppercase tracking-wider">Ver tours ›</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</section>

@endsection
