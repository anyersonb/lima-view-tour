@extends('layouts.app')

{{-- Meta título/descripción administrables desde el panel Filament →
     Páginas → "nosotros" → SEO — [idioma]. Vacío = cae al texto fijo de
     siempre (mismo criterio que tours/show.blade.php y blog/show.blade.php). --}}
@section('title', $page?->metaTitle ?: (__('nav.about') . ' — ' . __('seo.site_name')))
@section('description', $page?->metaDescription ?: __('ui.about_meta_description'))

@php
    $locale = app()->getLocale();
    $brujula = '<svg viewBox="0 0 64 64" class="w-12 h-12 text-teal-700" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.2"><circle cx="32" cy="32" r="22"/><circle cx="32" cy="32" r="14"/><path d="M32 14 L34 30 L50 32 L34 34 L32 50 L30 34 L14 32 L30 30 Z" fill="currentColor" opacity=".15" stroke="none"/><path d="M32 16v3M32 45v3M16 32h3M45 32h3"/></svg>';
    // Blocks from CMS — all keys fall back to hardcoded text when empty
    $b = $page?->blocks ?? [];
    $mediaUrl = function ($path): ?string {
        if (is_array($path)) { $path = $path[0] ?? ''; }
        $path = trim((string) $path);
        return ($path !== '' && $path !== '[]' && $path !== '""')
            ? \Illuminate\Support\Facades\Storage::disk('media')->url($path)
            : null;
    };

    // Default stats (fallback when blocks.stats is not set in CMS)
    $defaultStats = [
        [
            'title_es' => 'Misión',
            'title_en' => 'Mission',
            'title_pt' => 'Missão',
            'desc_es'  => 'Brindar experiencias memorables que conecten al viajero con la cultura peruana.',
            'desc_en'  => 'Provide memorable experiences that connect travelers with Peruvian culture.',
            'desc_pt'  => 'Proporcionar experiências memoráveis que conectem o viajante com a cultura peruana.',
        ],
        [
            'title_es' => 'Visión',
            'title_en' => 'Vision',
            'title_pt' => 'Visão',
            'desc_es'  => 'Ser la agencia referente de turismo responsable y experiencial en Perú.',
            'desc_en'  => 'Be the leading agency for responsible and experiential tourism in Peru.',
            'desc_pt'  => 'Ser a agência de referência em turismo responsável e experiencial no Peru.',
        ],
        [
            'title_es' => 'Valores',
            'title_en' => 'Values',
            'title_pt' => 'Valores',
            'desc_es'  => 'Pasión, respeto, integridad y compromiso con cada viajero y comunidad.',
            'desc_en'  => 'Passion, respect, integrity and commitment to every traveler and community.',
            'desc_pt'  => 'Paixão, respeito, integridade e comprometimento com cada viajante e comunidade.',
        ],
        [
            'title_es' => 'Equipo',
            'title_en' => 'Team',
            'title_pt' => 'Equipe',
            'desc_es'  => 'Guías locales certificados, planificadores y operadores con vocación de servicio.',
            'desc_en'  => 'Certified local guides, planners and operators with a service vocation.',
            'desc_pt'  => 'Guias locais certificados, planejadores e operadores com vocação de serviço.',
        ],
    ];

    // Default pillars (fallback when blocks.pillars is not set in CMS)
    $defaultPillars = [
        [
            'key'        => 'servicio',
            'label_es'   => 'Servicio',
            'label_en'   => 'Service',
            'label_pt'   => 'Serviço',
            'heading_es' => 'Servicio que se nota',
            'heading_en' => 'Service that shows',
            'heading_pt' => 'Serviço que se nota',
            'body_es'    => 'Atención personalizada antes, durante y después del viaje. Nuestro equipo está disponible 24/7 para resolver cualquier inquietud y asegurar que cada detalle salga como lo soñaste.',
            'body_en'    => 'Personalized attention before, during and after the trip. Our team is available 24/7 to resolve any concerns and ensure every detail goes as you dreamed.',
            'body_pt'    => 'Atenção personalizada antes, durante e depois da viagem. Nossa equipe está disponível 24/7 para resolver qualquer dúvida e garantir que cada detalhe saia como você sonhou.',
        ],
        [
            'key'        => 'calidad',
            'label_es'   => 'Calidad',
            'label_en'   => 'Quality',
            'label_pt'   => 'Qualidade',
            'heading_es' => 'Calidad sin atajos',
            'heading_en' => 'Quality without shortcuts',
            'heading_pt' => 'Qualidade sem atalhos',
            'body_es'    => 'Trabajamos con aliados certificados, vehículos modernos, hospedajes verificados y guías oficiales. Sin sorpresas: lo que ves en el itinerario es lo que vives.',
            'body_en'    => 'We work with certified partners, modern vehicles, verified accommodations and official guides. No surprises: what you see in the itinerary is what you experience.',
            'body_pt'    => 'Trabalhamos com parceiros certificados, veículos modernos, hospedagens verificadas e guias oficiais. Sem surpresas: o que você vê no itinerário é o que você vive.',
        ],
        [
            'key'        => 'propio',
            'label_es'   => 'Propio',
            'label_en'   => 'Own Operation',
            'label_pt'   => 'Operação Própria',
            'heading_es' => 'Operación propia',
            'heading_en' => 'Own operation',
            'heading_pt' => 'Operação própria',
            'body_es'    => 'Diseñamos y operamos nuestros propios tours. Eso nos permite controlar la calidad de extremo a extremo y garantizar la mejor relación experiencia-precio del mercado.',
            'body_en'    => 'We design and operate our own tours. This allows us to control quality end to end and guarantee the best experience-price ratio on the market.',
            'body_pt'    => 'Desenhamos e operamos nossos próprios tours. Isso nos permite controlar a qualidade de ponta a ponta e garantir a melhor relação experiência-preço do mercado.',
        ],
    ];

    // Resolve stats and pillars from CMS or fallback
    $statsItems   = !empty($b['stats'])   ? $b['stats']   : $defaultStats;
    $pillarsItems = !empty($b['pillars']) ? $b['pillars'] : $defaultPillars;

    // Testimonials: rating average (fallback 4.8 when table is empty)
    $avgRating = round(\App\Models\Testimonial::avg('rating') ?: 4.8, 1);

    // JSON-LD manual (panel → SEO — [idioma] → Datos estructurados). Esta
    // página no tiene un schema autogenerado propio hoy, así que el campo
    // simplemente se inyecta cuando existe — si está vacío no se emite nada
    // aquí (el Organization/WebSite global de <x-jsonld /> sigue igual).
    $customSchema = $page?->schemaJsonLd();
@endphp

@if ($customSchema)
    @push('schema')
    <x-schema-raw :json="$customSchema" />
    @endpush
@endif

@section('content')

{{-- ───────── HERO ───────── --}}
<section class="relative isolate text-white min-h-[70vh] flex flex-col justify-end" aria-labelledby="about-hero-title">
    <div class="absolute inset-0 -z-10">
        <img src="{{ $mediaUrl($b['img_hero'] ?? null) ?? asset('assets/banners/Rectangle 19215.jpg') }}" alt=""
             class="w-full h-full object-cover object-center" loading="eager" fetchpriority="high"
             width="1440" height="900">
        <div class="absolute inset-0 bg-gradient-to-b from-teal-900/30 via-teal-900/40 to-teal-900/70"></div>
    </div>
    <div class="container mx-auto px-5 lg:px-10">
        <nav aria-label="Breadcrumb" class="py-5 text-xs text-white/75">
            <ol class="flex items-center gap-1.5">
                <li><a href="{{ route('home', ['locale' => $locale]) }}" class="hover:text-orange-400 transition-colors">{{ __('nav.home') }}</a></li>
                <li aria-hidden="true" class="text-white/50">/</li>
                <li aria-current="page">{{ __('nav.about') }}</li>
            </ol>
        </nav>
    </div>
    <div class="container mx-auto px-5 lg:px-10 pb-20 md:pb-28 lg:pb-32">
        <p class="text-[11px] uppercase tracking-[0.25em] font-semibold text-white/80">{{ $b['hero_eyebrow_'.$locale] ?? __('ui.about_eyebrow') }}</p>
        <h1 id="about-hero-title" class="mt-3 font-display font-normal text-4xl md:text-5xl lg:text-6xl leading-[1.1] max-w-3xl">
            {{ $b['hero_title_'.$locale] ?? __('ui.about_hero_title') }}
        </h1>
        <p class="mt-5 max-w-2xl text-sm md:text-base text-white/85 leading-relaxed">
            {{ $b['hero_lead_'.$locale] ?? __('ui.about_hero_lead') }}
        </p>
        <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="btn--primary mt-7">
            {{ $b['hero_cta_label_'.$locale] ?? __('ui.see_more') }}
        </a>
    </div>
</section>

{{-- ───────── ¿POR QUÉ RESERVAR + GALERÍA? ───────── --}}
<section class="bg-white py-16 lg:py-20" aria-labelledby="about-why-title">
    <div class="container mx-auto px-5 lg:px-10 grid gap-12 lg:grid-cols-2 items-center">
        <div>
            <p class="text-[11px] uppercase tracking-[0.2em] text-teal-800/60 font-semibold inline-flex items-center gap-2 mb-3">
                <x-icon-compass class="w-8 h-8 text-teal-700" />
                {{ __('nav.about') }}
            </p>
            <h2 id="about-why-title" class="font-display text-3xl md:text-4xl lg:text-5xl text-teal-800 leading-tight">
                {{ __('ui.why_book_with_us') }}
            </h2>
            <p class="mt-5 text-teal-800/75 leading-relaxed text-sm md:text-base">
                {{ $b['why_intro_'.$locale] ?? __('ui.about_why_intro') }}
            </p>
            <p class="mt-4 text-teal-800/75 leading-relaxed text-sm md:text-base">
                {{ $b['why_intro2_'.$locale] ?? __('ui.about_why_intro2') }}
            </p>
            <a href="{{ \App\Support\LocalizedPages::url('contact', $locale) }}" class="btn--primary mt-7">
                {{ $b['why_cta_label_'.$locale] ?? __('ui.contact_us') }}
            </a>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <img src="{{ $mediaUrl($b['img_grid1'] ?? null) ?? asset('assets/banners/Rectangle 19216.jpg') }}" alt="{{ __('ui.alt_lima_nocturna') }}" class="w-full h-44 md:h-52 object-cover rounded-2xl shadow-sm" loading="lazy" width="360" height="208">
            <img src="{{ $mediaUrl($b['img_grid2'] ?? null) ?? asset('assets/banners/Rectangle 19217.jpg') }}" alt="{{ __('ui.alt_machu_picchu') }}" class="w-full h-44 md:h-52 object-cover rounded-2xl shadow-sm translate-y-6 md:translate-y-8" loading="lazy" width="360" height="208">
            <img src="{{ $mediaUrl($b['img_grid3'] ?? null) ?? asset('assets/banners/Rectangle 19218.jpg') }}" alt="{{ __('ui.alt_cusco_colonial') }}" class="w-full h-44 md:h-52 object-cover rounded-2xl shadow-sm" loading="lazy" width="360" height="208">
            <img src="{{ $mediaUrl($b['img_grid4'] ?? null) ?? asset('assets/banners/Rectangle 19219.jpg') }}" alt="{{ __('ui.alt_huacachina') }}" class="w-full h-44 md:h-52 object-cover rounded-2xl shadow-sm translate-y-6 md:translate-y-8" loading="lazy" width="360" height="208">
        </div>
    </div>
</section>

{{-- ───────── STATS BAND ───────── --}}
<section class="bg-teal-700 text-white" aria-labelledby="about-stats-title">
    <h2 id="about-stats-title" class="sr-only">{{ __('ui.who_we_are') }}</h2>
    <div class="container mx-auto px-5 lg:px-10 py-12 grid grid-cols-2 md:grid-cols-4 gap-y-8 md:gap-y-0 text-center">
        @foreach ($statsItems as $idx => $s)
            <div class="px-5 lg:px-8 {{ $idx > 0 ? 'border-l border-white/15' : '' }} flex flex-col items-center">
                <h3 class="font-display text-2xl lg:text-3xl text-white">
                    {{ $s['title_'.$locale] ?? $s['title_es'] ?? '' }}
                </h3>
                <p class="mt-3 text-sm text-white/75 leading-relaxed max-w-[14rem]">
                    {{ $s['desc_'.$locale] ?? $s['desc_es'] ?? '' }}
                </p>
            </div>
        @endforeach
    </div>
</section>

{{-- ───────── BANNER LIMA VIEW TOURS ───────── --}}
<section class="relative isolate text-white min-h-[22rem] flex items-center" aria-labelledby="about-banner-title">
    <div class="absolute inset-0 -z-10">
        <img src="{{ $mediaUrl($b['img_banner_cta'] ?? null) ?? asset('assets/banners/Rectangle 19214.jpg') }}" alt=""
             class="w-full h-full object-cover object-center" loading="lazy"
             width="1440" height="600">
        <div class="absolute inset-0 bg-teal-900/60"></div>
    </div>
    <div class="container mx-auto px-5 lg:px-10 py-20 md:py-28 lg:grid lg:grid-cols-2 lg:items-center lg:gap-16">
        <div>
            <h2 id="about-banner-title" class="font-display text-4xl md:text-5xl lg:text-6xl leading-tight">
                {!! nl2br(e($b['banner_heading_'.$locale] ?? __('ui.about_banner_heading'))) !!}
            </h2>
        </div>
        <div>
            <p class="mt-6 lg:mt-0 text-sm md:text-base text-white/85 leading-relaxed max-w-xl">
                {{ $b['banner_text_'.$locale] ?? __('ui.about_banner_text') }}
            </p>
        </div>
    </div>
</section>

{{-- ───────── VIVE LA CULTURA LOCAL (TABS) ───────── --}}
<section class="bg-white py-16 lg:py-20" aria-labelledby="about-cultura-title">
    <div class="container mx-auto px-5 lg:px-10 grid gap-10 lg:gap-16 lg:grid-cols-2 items-start"
         x-data="{ tab: '{{ ($pillarsItems[0]['key'] ?? 'servicio') }}' }">
        <div class="lg:sticky lg:top-28">
            <x-icon-compass class="w-12 h-12 lg:w-[58px] lg:h-[60px] text-teal-700 shrink-0" />
            <h2 id="about-cultura-title" class="mt-4 font-display text-3xl md:text-4xl lg:text-5xl text-teal-800 leading-tight">
                {!! nl2br(e($b['cultura_heading_'.$locale] ?? __('ui.about_cultura_heading'))) !!}
            </h2>
            <p class="mt-4 text-teal-800/75 max-w-md leading-relaxed text-sm md:text-base">
                {{ $b['cultura_intro_'.$locale] ?? __('ui.about_cultura_intro') }}
            </p>
        </div>

        <div>
            <div role="tablist" aria-label="{{ __('ui.service_pillars') }}" class="flex flex-wrap gap-2 mb-6">
                @foreach ($pillarsItems as $pillar)
                    @php $pid = $pillar['key'] ?? '' @endphp
                    <button type="button"
                            role="tab"
                            id="tab-{{ $pid }}"
                            aria-controls="panel-{{ $pid }}"
                            :aria-selected="tab === '{{ $pid }}'"
                            @click="tab = '{{ $pid }}'"
                            class="px-6 py-2.5 rounded-pill text-sm font-semibold uppercase tracking-wider transition-all border focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-500"
                            :class="tab === '{{ $pid }}'
                                ? 'bg-orange-500 text-white border-orange-500 shadow-sm'
                                : 'bg-cream-100 text-teal-800 border-transparent hover:bg-cream-200'">
                        {{ $pillar['label_'.$locale] ?? $pillar['label_es'] ?? $pid }}
                    </button>
                @endforeach
            </div>

            @foreach ($pillarsItems as $pillar)
                @php $pid = $pillar['key'] ?? '' @endphp
                <div role="tabpanel"
                     id="panel-{{ $pid }}"
                     aria-labelledby="tab-{{ $pid }}"
                     x-show="tab === '{{ $pid }}'"
                     x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    <article class="bg-cream-100 rounded-2xl p-7 lg:p-9">
                        <h3 class="font-display text-2xl lg:text-3xl text-teal-800">
                            {{ $pillar['heading_'.$locale] ?? $pillar['heading_es'] ?? '' }}
                        </h3>
                        <p class="mt-3 text-teal-800/75 leading-relaxed text-sm md:text-base">
                            {{ $pillar['body_'.$locale] ?? $pillar['body_es'] ?? '' }}
                        </p>
                    </article>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ───────── TRIPADVISOR QUOTE ───────── --}}
<section class="bg-cream-100 py-12 lg:py-16">
    <div class="container mx-auto px-5 lg:px-10 max-w-4xl text-center">
        <p class="text-teal-800/70 inline-flex items-center gap-2 text-sm uppercase tracking-[0.18em] font-semibold">
            <span aria-hidden="true">&#127797;</span> Tripadvisor
        </p>
        @php
            // Use featured testimonial from tripadvisor source, fallback to hardcoded
            $tadQuote  = $featured?->quote ?? null;
            $tadAuthor = $featured?->name  ?? null;
        @endphp
        <blockquote class="mt-6 font-display text-2xl md:text-3xl text-teal-800 leading-snug">
            &laquo;{{ $tadQuote ?? __('ui.about_ta_quote') }}&raquo;
        </blockquote>
        <figcaption class="mt-6 inline-flex items-center gap-3 text-left">
            <span class="w-10 h-10 rounded-full bg-cream-200"></span>
            <span class="leading-tight">
                <span class="block font-semibold text-teal-800">{{ $tadAuthor ?? 'Valeriy Roberts' }}</span>
                <span class="block text-xs text-teal-800/60">{{ __('ui.verified_client') }}</span>
            </span>
        </figcaption>
    </div>
</section>

{{-- ───────── TESTIMONIOS ───────── --}}
<section class="bg-cream-100 pb-16 lg:pb-20">
    <div class="container mx-auto px-5 lg:px-10 grid gap-6 lg:grid-cols-[1fr_1fr_minmax(0,1.05fr)] items-stretch">
        <div class="grid gap-5 sm:grid-cols-2 lg:col-span-2">
            @if ($testimonials->isNotEmpty())
                @foreach ($testimonials as $testimonial)
                    <figure class="bg-white rounded-2xl overflow-hidden flex flex-col">
                        <div class="p-6">
                            <span class="text-orange-400 text-3xl leading-none" aria-hidden="true">&rdquo;</span>
                            <blockquote class="mt-2 text-sm text-teal-800/85 leading-relaxed">
                                {{ $testimonial->quote }}
                            </blockquote>
                            <p class="mt-3 text-orange-400 text-sm" aria-hidden="true">
                                @for ($i = 0; $i < (int) round($testimonial->rating); $i++)&#9733;@endfor
                                <span class="text-teal-800/60">{{ $testimonial->source }}</span>
                            </p>
                        </div>
                        <figcaption class="bg-teal-700 text-white px-5 py-3 flex items-center gap-3 mt-auto">
                            @if ($testimonial->avatar)
                                <img src="{{ asset('storage/' . $testimonial->avatar) }}"
                                     alt="{{ $testimonial->name }}"
                                     class="w-9 h-9 rounded-full object-cover">
                            @else
                                <span class="w-9 h-9 rounded-full bg-cream-200"></span>
                            @endif
                            <span class="leading-tight">
                                <span class="block font-semibold text-sm tracking-wide">{{ strtoupper($testimonial->name) }}</span>
                                <span class="block text-[10px] uppercase tracking-[0.15em] text-white/70">{{ $testimonial->country }}</span>
                            </span>
                        </figcaption>
                    </figure>
                @endforeach
            @else
                {{-- Fallback hardcoded cards when testimonials table is empty --}}
                @foreach ([['Sara Fernández','Spain'],['Rebeca Figueroa','Colombia'],['Liam Carter','USA'],['Ana Suárez','México']] as [$name,$country])
                    <figure class="bg-white rounded-2xl overflow-hidden flex flex-col">
                        <div class="p-6">
                            <span class="text-orange-400 text-3xl leading-none" aria-hidden="true">&rdquo;</span>
                            <blockquote class="mt-2 text-sm text-teal-800/85 leading-relaxed">
                                {{ __('ui.about_fallback_quote') }}
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
            @endif
        </div>

        <article class="relative rounded-2xl overflow-hidden text-white min-h-[28rem]">
            <img src="{{ $mediaUrl($b['img_testimonios'] ?? null) ?? asset('assets/banners/Rectangle 19211.jpg') }}" alt=""
                 class="absolute inset-0 w-full h-full object-cover" loading="lazy">
            <div class="absolute inset-0 bg-gradient-to-t from-teal-900/85 via-teal-900/40 to-teal-900/20"></div>
            <div class="relative h-full p-8 flex flex-col">
                <p class="text-[11px] uppercase tracking-[0.2em] font-semibold">
                    {{ $b['testimonios_eyebrow_'.$locale] ?? __('ui.happy_clients') }}
                </p>
                <h3 class="mt-3 font-display text-3xl lg:text-4xl leading-tight">
                    {{ $b['testimonios_heading_'.$locale] ?? __('ui.clients_opinion') }}
                </h3>
                <p class="mt-4 inline-flex items-center gap-2 text-sm">
                    <span class="font-semibold">{{ $avgRating }}</span>
                    <span class="text-orange-400">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
                </p>
                <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="btn--primary mt-auto self-start">{{ __('ui.see_tours') }}</a>
            </div>
        </article>
    </div>
</section>

@endsection
