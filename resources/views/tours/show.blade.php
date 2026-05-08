@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $tour = [
        'title' => 'Tour de día Completo al Oasis de Huacachina + Islas Ballestas en Paracas',
        'rating' => '4.6',
        'reviews' => 30,
        'before' => 125,
        'now' => 100,
        'duration' => 'Full Day',
        'lang' => 'Español / Inglés',
        'group' => 'Grupal',
        'images' => [
            'Rectangle 19210.jpg',
            'Rectangle 19211.jpg',
            'Rectangle 19212.jpg',
            'Rectangle 19214.jpg',
            'Rectangle 19215.jpg',
        ],
        'related' => ['Rectangle 19217.jpg','Rectangle 19218.jpg','Rectangle 19219.jpg','Rectangle 19216.jpg'],
    ];

    $itinerary = [
        ['05:00 AM', 'Recojo del hotel', 'Pasamos a recogerte por tu hotel en Lima en una unidad cómoda y climatizada. Salida puntual hacia Paracas.'],
        ['07:30 AM', 'Llegada a Paracas', 'Desayuno breve en zona designada y briefing del recorrido por las Islas Ballestas.'],
        ['08:30 AM', 'Islas Ballestas', 'Embarcamos rumbo a las islas para observar fauna marina: lobos marinos, pingüinos de Humboldt y aves guaneras.'],
        ['11:00 AM', 'Reserva Nacional de Paracas', 'Recorrido panorámico por la reserva: Catedral, Playa Roja y mirador del Cóndor.'],
        ['01:30 PM', 'Almuerzo en Ica', 'Almuerzo típico en restaurante con opciones para todos los gustos (no incluido).'],
        ['03:00 PM', 'Oasis de Huacachina', 'Tiempo libre y opcional de tubulares + sandboarding en las dunas (no incluido).'],
        ['06:30 PM', 'Retorno a Lima', 'Salida hacia Lima con dejada en hotel céntrico aproximadamente a las 10:30 PM.'],
    ];
@endphp

@section('title', $tour['title'] . ' — ' . __('seo.site_name'))
@section('description', 'Reserva online: ' . $tour['title'] . '. Salidas diarias, guías oficiales, transporte cómodo. Mejor precio garantizado.')

@push('schema')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'TouristTrip',
    'name' => $tour['title'],
    'description' => 'Reserva online: ' . $tour['title'],
    'image' => array_map(fn($i) => asset('assets/banners/' . $i), $tour['images']),
    'aggregateRating' => [
        '@type' => 'AggregateRating',
        'ratingValue' => $tour['rating'],
        'reviewCount' => $tour['reviews'],
    ],
    'offers' => [
        '@type' => 'Offer',
        'price' => $tour['now'],
        'priceCurrency' => 'USD',
        'availability' => 'https://schema.org/InStock',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')

{{-- ───────── BREADCRUMB + TITLE ───────── --}}
<section class="bg-white pt-6 pb-4">
    <div class="container mx-auto px-5 lg:px-10">
        <nav aria-label="Breadcrumb" class="text-xs text-teal-800/70">
            <a href="{{ route('home', ['locale' => $locale]) }}" class="hover:text-orange-500">Inicio</a> &gt;
            <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="hover:text-orange-500">Tours</a> &gt;
            <span class="text-teal-800">{{ \Illuminate\Support\Str::limit($tour['title'], 60) }}</span>
        </nav>
    </div>
</section>

{{-- ───────── GALLERY + BOOKING SIDEBAR ───────── --}}
<section class="bg-white pb-10">
    <div class="container mx-auto px-5 lg:px-10 grid gap-8 lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]"
         x-data="{ active: 0 }">
        {{-- Galería --}}
        <div>
            <div class="grid grid-cols-3 grid-rows-2 gap-3 h-[26rem] md:h-[30rem]">
                <div class="col-span-2 row-span-2 rounded-2xl overflow-hidden">
                    <img :src="'{{ asset('assets/banners/') }}/' + ['{{ $tour['images'][0] }}','{{ $tour['images'][1] }}','{{ $tour['images'][2] }}','{{ $tour['images'][3] }}','{{ $tour['images'][4] }}'][active]"
                         alt="{{ $tour['title'] }}" class="w-full h-full object-cover" loading="eager">
                </div>
                @foreach (array_slice($tour['images'], 1, 4) as $i => $img)
                    <button type="button" @click="active = {{ $i + 1 }}" class="rounded-2xl overflow-hidden relative group">
                        <img src="{{ asset('assets/banners/' . $img) }}" alt="" class="w-full h-full object-cover transition-transform group-hover:scale-105" loading="lazy">
                        @if ($i === 3)
                            <span class="absolute inset-0 bg-teal-900/60 grid place-items-center text-white font-semibold text-sm">Ver fotos +</span>
                        @endif
                    </button>
                @endforeach
            </div>

            <h1 class="mt-6 font-display text-3xl md:text-4xl lg:text-5xl text-teal-800 leading-tight">
                {{ $tour['title'] }}
            </h1>
            <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-teal-800/75">
                <span class="inline-flex items-center gap-1">
                    <span class="text-orange-400" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
                    <span class="font-semibold text-teal-800">{{ $tour['rating'] }}</span>
                    <span>({{ $tour['reviews'] }} reseñas)</span>
                </span>
                <span class="inline-flex items-center gap-1.5">&#9202; {{ $tour['duration'] }}</span>
                <span class="inline-flex items-center gap-1.5">&#128100; {{ $tour['lang'] }}</span>
                <span class="inline-flex items-center gap-1.5">&#128101; {{ $tour['group'] }}</span>
            </div>
        </div>

        {{-- Sidebar de reserva --}}
        <aside class="lg:sticky lg:top-24 self-start">
            <div class="bg-white rounded-2xl ring-1 ring-teal-800/10 shadow-md overflow-hidden">
                <div class="bg-orange-500 text-white px-6 py-3 flex items-center justify-between">
                    <span class="text-xs uppercase tracking-[0.2em] font-semibold">Reservar online</span>
                    <span class="text-[10px] uppercase tracking-wider opacity-90">Cancelación gratuita</span>
                </div>
                @php
                    $tourId    = is_array($tour) ? null : $tour->id;
                    $tourPrice = is_array($tour) ? $tour['now'] : (float) $tour->price;
                    $tourBefore = is_array($tour) ? $tour['before'] : (float) ($tour->price_before ?? $tour->price);
                @endphp
                <form action="{{ $tourId ? route('cart.store', ['locale' => $locale]) : '#' }}"
                      method="POST" class="p-6 space-y-4">
                    @csrf
                    @if ($tourId)
                        <input type="hidden" name="tour_id" value="{{ $tourId }}">
                    @endif

                    <div class="flex items-baseline gap-2">
                        <p class="text-xs text-teal-800/60 line-through">${{ number_format($tourBefore, 0) }}</p>
                        <p class="font-price text-4xl text-teal-800">${{ number_format($tourPrice, 0) }}</p>
                        <span class="text-xs text-teal-800/60 uppercase tracking-wide">USD / persona</span>
                    </div>

                    <label class="block">
                        <span class="text-xs uppercase tracking-wide text-teal-800/70">Fecha del tour</span>
                        <input type="date" name="travel_date" required
                               min="{{ date('Y-m-d') }}"
                               class="mt-1 w-full rounded-pill border border-teal-800/15 px-5 py-3 text-sm focus:border-orange-400 focus:ring-orange-400">
                    </label>

                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="text-xs uppercase tracking-wide text-teal-800/70">Adultos</span>
                            <select name="adults" class="mt-1 w-full rounded-pill border border-teal-800/15 px-5 py-3 text-sm focus:border-orange-400 focus:ring-orange-400">
                                @for ($i=1;$i<=20;$i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-xs uppercase tracking-wide text-teal-800/70">Niños</span>
                            <select name="children" class="mt-1 w-full rounded-pill border border-teal-800/15 px-5 py-3 text-sm focus:border-orange-400 focus:ring-orange-400">
                                @for ($i=0;$i<=20;$i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
                            </select>
                        </label>
                    </div>

                    <div class="border-t border-teal-800/10 pt-4 flex items-center justify-between text-sm">
                        <span class="text-teal-800/70">Total estimado</span>
                        <span class="font-price text-2xl text-teal-800">${{ number_format($tourPrice, 0) }}</span>
                    </div>

                    <button type="submit" class="btn--primary btn--block">Reservar ahora</button>
                    <p class="text-[11px] text-teal-800/55 text-center">Pago seguro &middot; Sin cargos ocultos</p>
                </form>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 text-xs text-teal-800/75">
                @foreach ([
                    ['&#128666;','Transporte'],
                    ['&#127869;','Almuerzo opc.'],
                    ['&#127757;','Guía oficial'],
                    ['&#128137;','Seguro de viaje'],
                ] as [$icon, $label])
                    <div class="flex items-center gap-2 bg-cream-100 rounded-pill px-4 py-2">
                        <span aria-hidden="true">{!! $icon !!}</span>
                        <span>{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </aside>
    </div>
</section>

{{-- ───────── DESCRIPCIÓN + ITINERARIO + INCLUYE ───────── --}}
<section class="bg-white pb-16 lg:pb-20" x-data="{ open: 0 }" aria-label="Detalle del tour">
    <div class="container mx-auto px-5 lg:px-10 grid gap-12 lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
        <div>
            <h2 class="bg-teal-700 text-white rounded-t-2xl px-6 py-3 text-sm font-semibold uppercase tracking-wider">Descripción</h2>
            <div class="bg-cream-100 rounded-b-2xl p-6 lg:p-8 text-sm text-teal-800/85 leading-relaxed space-y-4">
                <p>Disfruta de un día completo combinando la magia del oasis de Huacachina con la riqueza natural de las Islas Ballestas en Paracas. Una experiencia diseñada para viajeros que buscan diversidad de paisajes y emociones en una sola jornada.</p>
                <p>Te recogemos directamente en tu hotel en Lima, viajamos en transporte cómodo y climatizado, y contamos con guías oficiales que te acompañarán en cada parada del recorrido.</p>
            </div>

            <h2 class="mt-10 bg-teal-700 text-white rounded-t-2xl px-6 py-3 text-sm font-semibold uppercase tracking-wider">Itinerario</h2>
            <div class="bg-cream-100 rounded-b-2xl divide-y divide-teal-800/10">
                @foreach ($itinerary as $i => [$hour, $title, $desc])
                    <article>
                        <button type="button" @click="open = (open === {{ $i }} ? -1 : {{ $i }})"
                                class="w-full flex items-center gap-5 px-6 py-4 text-left"
                                :aria-expanded="open === {{ $i }} ? 'true' : 'false'">
                            <span class="font-price text-2xl text-orange-500 w-24 shrink-0" aria-hidden="true">{{ $hour }}</span>
                            <h3 class="font-semibold text-teal-800 flex-1 text-base">{{ $title }}</h3>
                            <svg class="w-4 h-4 text-teal-800/60 transition-transform" :class="open === {{ $i }} ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open === {{ $i }}" x-cloak x-transition class="px-6 pb-5 -mt-1 pl-[7.75rem] text-sm text-teal-800/80 leading-relaxed">
                            {{ $desc }}
                        </div>
                    </article>
                @endforeach
            </div>

            <h2 class="mt-10 bg-teal-700 text-white rounded-t-2xl px-6 py-3 text-sm font-semibold uppercase tracking-wider">Recomendaciones</h2>
            <div class="bg-cream-100 rounded-b-2xl p-6 lg:p-8 text-sm text-teal-800/85 leading-relaxed">
                <ul class="grid sm:grid-cols-2 gap-y-2 gap-x-8 list-disc list-inside">
                    <li>Llevar protector solar SPF50+</li>
                    <li>Lentes de sol y sombrero</li>
                    <li>Ropa cómoda y zapatillas</li>
                    <li>Cámara fotográfica</li>
                    <li>Agua embotellada</li>
                    <li>Documento de identidad</li>
                </ul>
            </div>

            <div class="mt-10 grid md:grid-cols-2 gap-6">
                <div>
                    <h2 class="bg-teal-700 text-white rounded-t-2xl px-6 py-3 text-sm font-semibold uppercase tracking-wider">Incluye</h2>
                    <ul class="bg-cream-100 rounded-b-2xl p-6 text-sm text-teal-800/85 space-y-2">
                        <li class="flex gap-2"><span class="text-state-success" aria-hidden="true">&#10003;</span> Recojo y retorno al hotel en Lima</li>
                        <li class="flex gap-2"><span class="text-state-success" aria-hidden="true">&#10003;</span> Transporte turístico climatizado</li>
                        <li class="flex gap-2"><span class="text-state-success" aria-hidden="true">&#10003;</span> Guía oficial bilingüe</li>
                        <li class="flex gap-2"><span class="text-state-success" aria-hidden="true">&#10003;</span> Embarque a Islas Ballestas</li>
                        <li class="flex gap-2"><span class="text-state-success" aria-hidden="true">&#10003;</span> Ingreso a Reserva de Paracas</li>
                    </ul>
                </div>
                <div>
                    <h2 class="bg-teal-700 text-white rounded-t-2xl px-6 py-3 text-sm font-semibold uppercase tracking-wider">No incluye</h2>
                    <ul class="bg-cream-100 rounded-b-2xl p-6 text-sm text-teal-800/85 space-y-2">
                        <li class="flex gap-2"><span class="text-state-error" aria-hidden="true">&#10007;</span> Almuerzo en Ica</li>
                        <li class="flex gap-2"><span class="text-state-error" aria-hidden="true">&#10007;</span> Tubulares en Huacachina</li>
                        <li class="flex gap-2"><span class="text-state-error" aria-hidden="true">&#10007;</span> Bebidas adicionales</li>
                        <li class="flex gap-2"><span class="text-state-error" aria-hidden="true">&#10007;</span> Propinas (opcionales)</li>
                    </ul>
                </div>
            </div>

            <h2 class="mt-10 bg-teal-700 text-white rounded-t-2xl px-6 py-3 text-sm font-semibold uppercase tracking-wider">Notas importantes</h2>
            <div class="bg-cream-100 rounded-b-2xl p-6 lg:p-8 text-sm text-teal-800/85 leading-relaxed">
                <p>El recorrido marítimo a las Islas Ballestas puede sufrir cambios o cancelaciones por condiciones climáticas. En caso de cancelación se reembolsa el ítem correspondiente o se reagenda la salida sin costo.</p>
            </div>
        </div>

        {{-- Información lateral --}}
        <aside class="space-y-6">
            <div class="bg-cream-100 rounded-2xl p-6">
                <h3 class="font-display text-lg text-teal-800">Información del tour</h3>
                <dl class="mt-4 grid grid-cols-2 gap-y-3 text-sm">
                    <dt class="text-teal-800/60">Duración</dt><dd class="text-teal-800 font-semibold">{{ $tour['duration'] }}</dd>
                    <dt class="text-teal-800/60">Idiomas</dt><dd class="text-teal-800 font-semibold">{{ $tour['lang'] }}</dd>
                    <dt class="text-teal-800/60">Grupo</dt><dd class="text-teal-800 font-semibold">{{ $tour['group'] }}</dd>
                    <dt class="text-teal-800/60">Salida</dt><dd class="text-teal-800 font-semibold">Diaria 5:00 AM</dd>
                    <dt class="text-teal-800/60">Regreso</dt><dd class="text-teal-800 font-semibold">10:30 PM</dd>
                </dl>
            </div>

            <div class="bg-teal-700 text-white rounded-2xl p-6">
                <h3 class="font-display text-lg">¿Necesitas ayuda?</h3>
                <p class="mt-2 text-sm text-white/85">Nuestro equipo te asesora 24/7 antes y durante tu reserva.</p>
                <a href="tel:+51190010088" class="mt-4 inline-flex items-center gap-2 rounded-pill bg-orange-500 hover:bg-orange-600 px-5 py-2.5 text-sm font-semibold transition">
                    Llamar al 190010088
                </a>
                <a href="https://wa.me/51935542384" class="mt-3 inline-flex items-center gap-2 rounded-pill border border-white/40 hover:bg-white/10 px-5 py-2.5 text-sm font-semibold transition">
                    Chatear por WhatsApp
                </a>
            </div>
        </aside>
    </div>
</section>

{{-- ───────── TESTIMONIOS ───────── --}}
<section class="bg-cream-100 py-14 md:py-16 lg:py-20" aria-labelledby="show-reviews-title">
    <div class="container mx-auto px-5 lg:px-10">
        <div class="text-center max-w-2xl mx-auto mb-8 md:mb-10">
            <p class="text-[11px] uppercase tracking-[0.2em] text-teal-800/70 font-semibold">RESEÑAS</p>
            <h2 id="show-reviews-title" class="mt-3 font-display text-2xl sm:text-3xl md:text-4xl text-teal-800 leading-tight">Nuestros clientes opinan de nuestros tours</h2>
        </div>

        <div class="owl-carousel owl-theme owl-testimonials" data-owl-testimonials>
            @foreach ([['Sara Fernández','Spain'],['Rebeca Figueroa','Colombia'],['Liam Carter','USA'],['Ana Suárez','México']] as [$name,$country])
                <figure class="item bg-white rounded-2xl overflow-hidden flex flex-col">
                    <div class="p-6">
                        <span class="text-orange-400 text-3xl leading-none" aria-hidden="true">&rdquo;</span>
                        <blockquote class="mt-2 text-sm text-teal-800/85 leading-relaxed">
                            Una experiencia que recordaremos toda la vida. Atención impecable y guías muy preparados.
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
    </div>
</section>

{{-- ───────── TOURS RELACIONADOS ───────── --}}
<section class="bg-white py-16 lg:py-20">
    <div class="container mx-auto px-5 lg:px-10">
        <div class="flex items-end justify-between mb-10">
            <div>
                <p class="text-[11px] uppercase tracking-[0.2em] text-teal-800/70 font-semibold">SIGUE EXPLORANDO</p>
                <h2 class="mt-2 font-display text-3xl md:text-4xl text-teal-800 leading-tight">Nuestros tours más comprados</h2>
            </div>
            <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="hidden md:inline-flex items-center gap-2 text-sm font-semibold text-orange-500 hover:text-orange-600">
                Ver catálogo &rsaquo;
            </a>
        </div>

        <div class="owl-carousel owl-theme owl-related" data-owl-related>
            @foreach ($tour['related'] as $i => $img)
                <article class="item group bg-white rounded-2xl overflow-hidden ring-1 ring-teal-800/5">
                    <a href="{{ route('tours.show', ['locale' => $locale, 'slug' => 'machu-picchu']) }}" class="block">
                        <img src="{{ asset('assets/banners/' . $img) }}" alt="" class="w-full h-48 object-cover transition-transform group-hover:scale-105" loading="lazy">
                    </a>
                    <div class="p-5">
                        <p class="text-[11px] uppercase tracking-wide text-orange-500 font-semibold">{{ ['LIMA','ICA','CUSCO','PARACAS'][$i] }}</p>
                        <h3 class="mt-2 font-display text-base text-teal-800 leading-snug">Tour completo con experiencias únicas</h3>
                        <div class="mt-4 flex items-end justify-between">
                            <p class="font-price text-xl text-teal-800">$120</p>
                            <a href="{{ route('tours.show', ['locale' => $locale, 'slug' => 'machu-picchu']) }}" class="text-xs font-semibold text-orange-500 hover:text-orange-600 uppercase tracking-wider">Ver tour &rsaquo;</a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>

@endsection
