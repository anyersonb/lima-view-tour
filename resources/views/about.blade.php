@extends('layouts.app')

@section('title', 'Nosotros — ' . __('seo.site_name'))
@section('description', 'Somos planificadores profesionales para tus vacaciones. Conoce a Lima View Tours: experiencias auténticas, guías expertos y turismo responsable en Perú.')

@php
    $locale = app()->getLocale();
    $brujula = '<svg viewBox="0 0 64 64" class="w-12 h-12 text-teal-700" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.2"><circle cx="32" cy="32" r="22"/><circle cx="32" cy="32" r="14"/><path d="M32 14 L34 30 L50 32 L34 34 L32 50 L30 34 L14 32 L30 30 Z" fill="currentColor" opacity=".15" stroke="none"/><path d="M32 16v3M32 45v3M16 32h3M45 32h3"/></svg>';
@endphp

@section('content')

{{-- ───────── HERO ───────── --}}
<section class="relative isolate text-white">
    <div class="absolute inset-0 -z-10">
        <img src="{{ asset('assets/banners/Rectangle 19215.jpg') }}" alt="" class="w-full h-full object-cover" loading="eager" fetchpriority="high">
        <div class="absolute inset-0 bg-gradient-to-b from-teal-900/40 via-teal-900/45 to-teal-900/65"></div>
    </div>
    <div class="container mx-auto px-5 lg:px-10">
        <nav aria-label="Breadcrumb" class="pt-6 text-xs text-white/80">
            <a href="{{ route('home', ['locale' => $locale]) }}" class="hover:text-orange-400">Inicio</a> &gt; <span>Nosotros</span>
        </nav>
    </div>
    <div class="container mx-auto px-5 lg:px-10 pt-12 pb-28 md:pb-36 text-center max-w-4xl">
        <p class="text-[11px] uppercase tracking-[0.25em] font-semibold opacity-90">SOBRE NOSOTROS</p>
        <h1 class="mt-4 font-display font-normal text-4xl md:text-5xl lg:text-6xl leading-[1.1]">
            Somos planificadores profesionales<br>para tus vacaciones
        </h1>
        <p class="mt-6 mx-auto max-w-2xl text-sm md:text-base text-white/85">
            Más de una década organizando experiencias auténticas por los destinos más emblemáticos del Perú. Nuestro compromiso: viajar contigo y dejar huella positiva.
        </p>
        <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="btn--primary mt-8">Ver tours</a>
    </div>
</section>

{{-- ───────── ¿POR QUÉ RESERVAR + GALERÍA? ───────── --}}
<section class="bg-white py-16 lg:py-20">
    <div class="container mx-auto px-5 lg:px-10 grid gap-12 lg:grid-cols-2 items-start">
        <div>
            <p class="text-[11px] uppercase tracking-[0.2em] text-teal-800/70 font-semibold inline-flex items-center gap-2">
                <span class="w-9 h-9 rounded-full border border-teal-800/20 grid place-items-center">
                    <svg class="w-5 h-5 text-teal-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><circle cx="12" cy="12" r="9"/></svg>
                </span>
                NOSOTROS
            </p>
            <h2 class="mt-3 font-display text-3xl md:text-4xl lg:text-5xl text-teal-800 leading-tight">
                ¿Por qué reservar<br>con nosotros?
            </h2>
            <p class="mt-5 text-teal-800/80 leading-relaxed">
                En Lima View Tours diseñamos experiencias para conectar a nuestros viajeros con la riqueza histórica, gastronómica y natural del Perú. Cada itinerario está pensado al detalle: transporte cómodo, guías locales certificados y aliados confiables.
            </p>
            <p class="mt-4 text-teal-800/80 leading-relaxed">
                Apostamos por un turismo responsable que respeta a las comunidades y al medio ambiente. Nuestro símbolo —la espiral— representa el viaje desde el centro hacia nuevas perspectivas.
            </p>
            <a href="{{ route('contact', ['locale' => $locale]) }}" class="btn--primary mt-7">Contáctanos</a>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <img src="{{ asset('assets/banners/Rectangle 19216.jpg') }}" alt="" class="w-full h-44 md:h-56 object-cover rounded-2xl shadow-sm" loading="lazy">
            <img src="{{ asset('assets/banners/Rectangle 19217.jpg') }}" alt="" class="w-full h-44 md:h-56 object-cover rounded-2xl shadow-sm translate-y-6" loading="lazy">
            <img src="{{ asset('assets/banners/Rectangle 19218.jpg') }}" alt="" class="w-full h-44 md:h-56 object-cover rounded-2xl shadow-sm" loading="lazy">
            <img src="{{ asset('assets/banners/Rectangle 19219.jpg') }}" alt="" class="w-full h-44 md:h-56 object-cover rounded-2xl shadow-sm translate-y-6" loading="lazy">
        </div>
    </div>
</section>

{{-- ───────── STATS BAND ───────── --}}
<section class="bg-teal-700 text-white" aria-labelledby="about-stats-title">
    <h2 id="about-stats-title" class="sr-only">Nuestras cifras</h2>
    <div class="container mx-auto px-5 lg:px-10 py-12 grid grid-cols-2 md:grid-cols-4 gap-y-8 text-center divide-y md:divide-y-0 md:divide-x divide-white/10">
        @foreach ([
            ['Misión', 'Brindar experiencias memorables que conecten al viajero con la cultura peruana.'],
            ['Visión', 'Ser la agencia referente de turismo responsable y experiencial en Perú.'],
            ['Valores', 'Pasión, respeto, integridad y compromiso con cada viajero y comunidad.'],
            ['Equipo', 'Guías locales certificados, planificadores y operadores con vocación de servicio.'],
        ] as [$title, $desc])
            <div class="px-6">
                <h3 class="font-display text-2xl">{{ $title }}</h3>
                <p class="mt-2 text-sm text-white/80 leading-relaxed max-w-[16rem] mx-auto">{{ $desc }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- ───────── BANNER LIMA VIEW TOURS ───────── --}}
<section class="relative isolate text-white">
    <div class="absolute inset-0 -z-10">
        <img src="{{ asset('assets/banners/Rectangle 19214.jpg') }}" alt="" class="w-full h-full object-cover" loading="lazy">
        <div class="absolute inset-0 bg-teal-900/55"></div>
    </div>
    <div class="container mx-auto px-5 lg:px-10 py-24 md:py-32 text-center">
        <h2 class="font-display text-4xl md:text-5xl lg:text-6xl leading-tight">Somos<br>Lima View Tours</h2>
        <p class="mt-5 mx-auto max-w-2xl text-sm md:text-base text-white/85">
            Transformamos cada viaje en una experiencia que conecta tu presente con el legado vibrante del Perú.
        </p>
    </div>
</section>

{{-- ───────── VIVE LA CULTURA LOCAL (TABS) ───────── --}}
<section class="bg-white py-16 lg:py-20">
    <div class="container mx-auto px-5 lg:px-10 grid gap-10 lg:grid-cols-2 items-start"
         x-data="{ tab: 'servicio' }">
        <div class="lg:sticky lg:top-24">
            <x-icon-compass class="w-12 h-12 lg:w-[58px] lg:h-[60px] text-teal-700 shrink-0" />
            <h2 class="mt-4 font-display text-3xl md:text-4xl lg:text-5xl text-teal-800 leading-tight">
                Vive la cultura<br>local
            </h2>
            <p class="mt-4 text-teal-800/80 max-w-md leading-relaxed">
                Cada experiencia se construye sobre tres pilares fundamentales: servicio cálido, calidad sin compromisos y operación propia que asegura la mejor experiencia.
            </p>
        </div>

        <div>
            <div role="tablist" class="flex gap-2 mb-6">
                @foreach ([
                    ['servicio','Servicio'],
                    ['calidad','Calidad'],
                    ['propio','Propio'],
                ] as [$id,$label])
                    <button type="button"
                            role="tab"
                            id="tab-{{ $id }}"
                            aria-controls="panel-{{ $id }}"
                            :aria-selected="tab === '{{ $id }}' ? 'true' : 'false'"
                            @click="tab = '{{ $id }}'"
                            class="px-6 py-3 rounded-pill text-sm font-semibold uppercase tracking-wide transition border focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-700"
                            :class="tab === '{{ $id }}' ? 'bg-orange-700 text-white border-orange-700' : 'bg-cream-100 text-teal-800 border-teal-800/10 hover:border-orange-700'">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div role="tabpanel" id="panel-servicio" aria-labelledby="tab-servicio" x-show="tab === 'servicio'" x-cloak x-transition>
                <article class="bg-cream-100 rounded-2xl p-7 lg:p-8">
                    <h3 class="font-display text-2xl text-teal-800">Servicio que se nota</h3>
                    <p class="mt-3 text-teal-800/80 leading-relaxed text-sm">
                        Atención personalizada antes, durante y después del viaje. Nuestro equipo está disponible 24/7 para resolver cualquier inquietud y asegurar que cada detalle salga como lo soñaste.
                    </p>
                </article>
            </div>
            <div role="tabpanel" id="panel-calidad" aria-labelledby="tab-calidad" x-show="tab === 'calidad'" x-cloak x-transition>
                <article class="bg-cream-100 rounded-2xl p-7 lg:p-8">
                    <h3 class="font-display text-2xl text-teal-800">Calidad sin atajos</h3>
                    <p class="mt-3 text-teal-800/80 leading-relaxed text-sm">
                        Trabajamos con aliados certificados, vehículos modernos, hospedajes verificados y guías oficiales. Sin sorpresas: lo que ves en el itinerario es lo que vives.
                    </p>
                </article>
            </div>
            <div role="tabpanel" id="panel-propio" aria-labelledby="tab-propio" x-show="tab === 'propio'" x-cloak x-transition>
                <article class="bg-cream-100 rounded-2xl p-7 lg:p-8">
                    <h3 class="font-display text-2xl text-teal-800">Operación propia</h3>
                    <p class="mt-3 text-teal-800/80 leading-relaxed text-sm">
                        Diseñamos y operamos nuestros propios tours. Eso nos permite controlar la calidad de extremo a extremo y garantizar la mejor relación experiencia-precio del mercado.
                    </p>
                </article>
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
