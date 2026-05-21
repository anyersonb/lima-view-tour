@extends('layouts.app')

@section('title', 'Nosotros — ' . __('seo.site_name'))
@section('description', 'Somos planificadores profesionales para tus vacaciones. Conoce a Lima View Tours: experiencias auténticas, guías expertos y turismo responsable en Perú.')

@php
    $locale = app()->getLocale();
    $brujula = '<svg viewBox="0 0 64 64" class="w-12 h-12 text-teal-700" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.2"><circle cx="32" cy="32" r="22"/><circle cx="32" cy="32" r="14"/><path d="M32 14 L34 30 L50 32 L34 34 L32 50 L30 34 L14 32 L30 30 Z" fill="currentColor" opacity=".15" stroke="none"/><path d="M32 16v3M32 45v3M16 32h3M45 32h3"/></svg>';
@endphp

@section('content')

{{-- ───────── HERO ───────── --}}
<section class="relative isolate text-white min-h-[70vh] flex flex-col justify-end" aria-labelledby="about-hero-title">
    <div class="absolute inset-0 -z-10">
        <img src="{{ asset('assets/banners/Rectangle 19215.jpg') }}" alt=""
             class="w-full h-full object-cover object-center" loading="eager" fetchpriority="high"
             width="1440" height="900">
        <div class="absolute inset-0 bg-gradient-to-b from-teal-900/30 via-teal-900/40 to-teal-900/70"></div>
    </div>
    <div class="container mx-auto px-5 lg:px-10">
        <nav aria-label="Breadcrumb" class="py-5 text-xs text-white/75">
            <ol class="flex items-center gap-1.5">
                <li><a href="{{ route('home', ['locale' => $locale]) }}" class="hover:text-orange-400 transition-colors">Inicio</a></li>
                <li aria-hidden="true" class="text-white/50">/</li>
                <li aria-current="page">Nosotros</li>
            </ol>
        </nav>
    </div>
    <div class="container mx-auto px-5 lg:px-10 pb-20 md:pb-28 lg:pb-32">
        <p class="text-[11px] uppercase tracking-[0.25em] font-semibold text-white/80">SOBRE NOSOTROS</p>
        <h1 id="about-hero-title" class="mt-3 font-display font-normal text-4xl md:text-5xl lg:text-6xl leading-[1.1] max-w-3xl">
            Somos planificadores profesionales<br class="hidden md:block"> para tus vacaciones
        </h1>
        <p class="mt-5 max-w-2xl text-sm md:text-base text-white/85 leading-relaxed">
            Más de una década organizando experiencias auténticas por los destinos más emblemáticos del Perú. Nuestro compromiso: viajar contigo y dejar huella positiva.
        </p>
        <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="btn--primary mt-7">Ver más</a>
    </div>
</section>

{{-- ───────── ¿POR QUÉ RESERVAR + GALERÍA? ───────── --}}
<section class="bg-white py-16 lg:py-20" aria-labelledby="about-why-title">
    <div class="container mx-auto px-5 lg:px-10 grid gap-12 lg:grid-cols-2 items-center">
        <div>
            <p class="text-[11px] uppercase tracking-[0.2em] text-teal-800/60 font-semibold inline-flex items-center gap-2 mb-3">
                <x-icon-compass class="w-8 h-8 text-teal-700" />
                NOSOTROS
            </p>
            <h2 id="about-why-title" class="font-display text-3xl md:text-4xl lg:text-5xl text-teal-800 leading-tight">
                ¿Por qué reservar<br>con nosotros?
            </h2>
            <p class="mt-5 text-teal-800/75 leading-relaxed text-sm md:text-base">
                En Lima View Tours diseñamos experiencias para conectar a nuestros viajeros con la riqueza histórica, gastronómica y natural del Perú. Cada itinerario está pensado al detalle: transporte cómodo, guías locales certificados y aliados confiables.
            </p>
            <p class="mt-4 text-teal-800/75 leading-relaxed text-sm md:text-base">
                Apostamos por un turismo responsable que respeta a las comunidades y al medio ambiente. Nuestro símbolo —la espiral— representa el viaje desde el centro hacia nuevas perspectivas.
            </p>
            <a href="{{ route('contact', ['locale' => $locale]) }}" class="btn--primary mt-7">Contáctanos</a>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <img src="{{ asset('assets/banners/Rectangle 19216.jpg') }}" alt="Lima nocturna" class="w-full h-44 md:h-52 object-cover rounded-2xl shadow-sm" loading="lazy" width="360" height="208">
            <img src="{{ asset('assets/banners/Rectangle 19217.jpg') }}" alt="Machu Picchu" class="w-full h-44 md:h-52 object-cover rounded-2xl shadow-sm translate-y-6 md:translate-y-8" loading="lazy" width="360" height="208">
            <img src="{{ asset('assets/banners/Rectangle 19218.jpg') }}" alt="Cusco colonial" class="w-full h-44 md:h-52 object-cover rounded-2xl shadow-sm" loading="lazy" width="360" height="208">
            <img src="{{ asset('assets/banners/Rectangle 19219.jpg') }}" alt="Huacachina oasis" class="w-full h-44 md:h-52 object-cover rounded-2xl shadow-sm translate-y-6 md:translate-y-8" loading="lazy" width="360" height="208">
        </div>
    </div>
</section>

{{-- ───────── STATS BAND ───────── --}}
<section class="bg-teal-700 text-white" aria-labelledby="about-stats-title">
    <h2 id="about-stats-title" class="sr-only">Quiénes somos</h2>
    <div class="container mx-auto px-5 lg:px-10 py-12 grid grid-cols-2 md:grid-cols-4 gap-y-8 md:gap-y-0 text-center">
        @foreach ([
            ['Misión', 'Brindar experiencias memorables que conecten al viajero con la cultura peruana.'],
            ['Visión', 'Ser la agencia referente de turismo responsable y experiencial en Perú.'],
            ['Valores', 'Pasión, respeto, integridad y compromiso con cada viajero y comunidad.'],
            ['Equipo', 'Guías locales certificados, planificadores y operadores con vocación de servicio.'],
        ] as $idx => [$title, $desc])
            <div class="px-5 lg:px-8 {{ $idx > 0 ? 'border-l border-white/15' : '' }} flex flex-col items-center">
                <h3 class="font-display text-2xl lg:text-3xl text-white">{{ $title }}</h3>
                <p class="mt-3 text-sm text-white/75 leading-relaxed max-w-[14rem]">{{ $desc }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- ───────── BANNER LIMA VIEW TOURS ───────── --}}
<section class="relative isolate text-white min-h-[22rem] flex items-center" aria-labelledby="about-banner-title">
    <div class="absolute inset-0 -z-10">
        <img src="{{ asset('assets/banners/Rectangle 19214.jpg') }}" alt=""
             class="w-full h-full object-cover object-center" loading="lazy"
             width="1440" height="600">
        <div class="absolute inset-0 bg-teal-900/60"></div>
    </div>
    <div class="container mx-auto px-5 lg:px-10 py-20 md:py-28 lg:grid lg:grid-cols-2 lg:items-center lg:gap-16">
        <div>
            <h2 id="about-banner-title" class="font-display text-4xl md:text-5xl lg:text-6xl leading-tight">
                Somos<br>Lima View<br>Tours
            </h2>
        </div>
        <div>
            <p class="mt-6 lg:mt-0 text-sm md:text-base text-white/85 leading-relaxed max-w-xl">
                Transformamos cada viaje en una experiencia que conecta tu presente con el legado vibrante del Perú. Somos más que una agencia: somos tus compañeros de aventura.
            </p>
        </div>
    </div>
</section>

{{-- ───────── VIVE LA CULTURA LOCAL (TABS) ───────── --}}
<section class="bg-white py-16 lg:py-20" aria-labelledby="about-cultura-title">
    <div class="container mx-auto px-5 lg:px-10 grid gap-10 lg:gap-16 lg:grid-cols-2 items-start"
         x-data="{ tab: 'servicio' }">
        <div class="lg:sticky lg:top-28">
            <x-icon-compass class="w-12 h-12 lg:w-[58px] lg:h-[60px] text-teal-700 shrink-0" />
            <h2 id="about-cultura-title" class="mt-4 font-display text-3xl md:text-4xl lg:text-5xl text-teal-800 leading-tight">
                Vive la cultura<br>local
            </h2>
            <p class="mt-4 text-teal-800/75 max-w-md leading-relaxed text-sm md:text-base">
                Cada experiencia se construye sobre tres pilares fundamentales: servicio cálido, calidad sin compromisos y operación propia que asegura la mejor experiencia.
            </p>
        </div>

        <div>
            <div role="tablist" aria-label="Pilares de servicio" class="flex flex-wrap gap-2 mb-6">
                @foreach ([
                    ['servicio','Servicio'],
                    ['calidad','Calidad'],
                    ['propio','Propio'],
                ] as [$id,$label])
                    <button type="button"
                            role="tab"
                            id="tab-{{ $id }}"
                            aria-controls="panel-{{ $id }}"
                            :aria-selected="tab === '{{ $id }}'"
                            @click="tab = '{{ $id }}'"
                            class="px-6 py-2.5 rounded-pill text-sm font-semibold uppercase tracking-wider transition-all border focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-500"
                            :class="tab === '{{ $id }}'
                                ? 'bg-orange-500 text-white border-orange-500 shadow-sm'
                                : 'bg-cream-100 text-teal-800 border-transparent hover:bg-cream-200'">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            @foreach ([
                ['servicio','Servicio que se nota','Atención personalizada antes, durante y después del viaje. Nuestro equipo está disponible 24/7 para resolver cualquier inquietud y asegurar que cada detalle salga como lo soñaste.'],
                ['calidad','Calidad sin atajos','Trabajamos con aliados certificados, vehículos modernos, hospedajes verificados y guías oficiales. Sin sorpresas: lo que ves en el itinerario es lo que vives.'],
                ['propio','Operación propia','Diseñamos y operamos nuestros propios tours. Eso nos permite controlar la calidad de extremo a extremo y garantizar la mejor relación experiencia-precio del mercado.'],
            ] as [$id,$heading,$body])
                <div role="tabpanel"
                     id="panel-{{ $id }}"
                     aria-labelledby="tab-{{ $id }}"
                     x-show="tab === '{{ $id }}'"
                     x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    <article class="bg-cream-100 rounded-2xl p-7 lg:p-9">
                        <h3 class="font-display text-2xl lg:text-3xl text-teal-800">{{ $heading }}</h3>
                        <p class="mt-3 text-teal-800/75 leading-relaxed text-sm md:text-base">{{ $body }}</p>
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
        <blockquote class="mt-6 font-display text-2xl md:text-3xl text-teal-800 leading-snug">
            &laquo;Una experiencia que supera lo prometido. Logística impecable, guías apasionados y un trato cálido que hizo del viaje algo memorable.&raquo;
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

{{-- ───────── TESTIMONIOS ───────── --}}
<section class="bg-cream-100 pb-16 lg:pb-20">
    <div class="container mx-auto px-5 lg:px-10 grid gap-6 lg:grid-cols-[1fr_1fr_minmax(0,1.05fr)] items-stretch">
        <div class="grid gap-5 sm:grid-cols-2 lg:col-span-2">
            @foreach ([['Sara Fernández','Spain'],['Rebeca Figueroa','Colombia'],['Liam Carter','USA'],['Ana Suárez','México']] as [$name,$country])
                <figure class="bg-white rounded-2xl overflow-hidden flex flex-col">
                    <div class="p-6">
                        <span class="text-orange-400 text-3xl leading-none" aria-hidden="true">&rdquo;</span>
                        <blockquote class="mt-2 text-sm text-teal-800/85 leading-relaxed">
                            Excelente atención y un itinerario muy bien diseñado. Volvería sin dudarlo y lo recomiendo a quien quiera conocer Perú a profundidad.
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

@endsection
