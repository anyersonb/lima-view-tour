@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $gallery = $tour->gallery ?? [];
    $itinerary = $tour->{"itinerary_{$locale}"} ?? $tour->itinerary_es ?? [];
    $includes  = $tour->{"includes_{$locale}"}  ?? $tour->includes_es  ?? [];
    $excludes  = $tour->{"excludes_{$locale}"}  ?? $tour->excludes_es  ?? [];
    $recommendations = $tour->{"recommendations_{$locale}"} ?? $tour->recommendations_es ?? null;
    $notes = $tour->{"notes_{$locale}"} ?? $tour->notes_es ?? null;
    $contactPhone = \App\Models\Setting::get('contact_phone', '+51 935 542 384');

    $recommendationLines = $recommendations
        ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $recommendations))))
        : ['Llevar protector solar SPF50+', 'Lentes de sol y sombrero', 'Ropa cómoda y zapatillas', 'Cámara fotográfica', 'Agua embotellada', 'Documento de identidad'];

    $notesText = $notes ?: 'El recorrido marítimo a las Islas Ballestas puede sufrir cambios o cancelaciones por condiciones climáticas. En caso de cancelación se reembolsa el ítem correspondiente o se reagenda la salida sin costo.';
@endphp

@section('title', $tour->title . ' — ' . __('seo.site_name'))
@section('description', 'Reserva online: ' . $tour->title . '. Salidas diarias, guías oficiales, transporte cómodo. Mejor precio garantizado.')

@push('schema')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'TouristTrip',
    'name' => $tour->title,
    'description' => 'Reserva online: ' . $tour->title,
    'image' => $gallery
        ? array_map(fn($i) => Str::startsWith($i, ['http','/']) ? $i : asset('storage/' . $i), $gallery)
        : [$tour->cover_url],
    'aggregateRating' => $tour->rating ? [
        '@type' => 'AggregateRating',
        'ratingValue' => $tour->rating,
        'reviewCount' => $tour->testimonials()->count() ?: 1,
    ] : null,
    'offers' => [
        '@type' => 'Offer',
        'price' => (float) $tour->price,
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
            <span class="text-teal-800">{{ \Illuminate\Support\Str::limit($tour->title, 60) }}</span>
        </nav>
    </div>
</section>

{{-- ───────── GALLERY + BOOKING SIDEBAR ───────── --}}
<section class="bg-white pb-10">
    @php
        $galleryUrls = $tour->gallery_urls;
        $totalImgs = count($galleryUrls);
    @endphp
    <div class="container mx-auto px-5 lg:px-10 grid gap-8 lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]"
         x-data="{
             gallery: {{ json_encode($galleryUrls) }},
             active: 0,
             lightbox: false,
             showAll: false,
             open(i) { this.active = i ?? this.active; this.lightbox = true; document.body.style.overflow = 'hidden'; },
             close() { this.lightbox = false; this.showAll = false; document.body.style.overflow = ''; },
             prev() { this.active = (this.active - 1 + this.gallery.length) % this.gallery.length; },
             next() { this.active = (this.active + 1) % this.gallery.length; }
         }"
         @keydown.escape.window="lightbox && close()"
         @keydown.arrow-left.window="lightbox && prev()"
         @keydown.arrow-right.window="lightbox && next()">
        {{-- Galería --}}
        <div>
            <div class="grid grid-cols-4 grid-rows-2 gap-3 h-[26rem] md:h-[30rem]">
                <button type="button" @click="open(active)"
                        class="col-span-2 row-span-2 block w-full h-full rounded-2xl overflow-hidden group cursor-zoom-in">
                    <img :src="gallery[active] || {{ json_encode($galleryUrls[0] ?? $tour->cover_url) }}"
                         alt="{{ $tour->title }}" class="w-full h-full object-cover transition-transform group-hover:scale-[1.02]" loading="eager">
                </button>
                @if ($totalImgs > 1)
                    @foreach (array_slice($galleryUrls, 1, 4) as $i => $imgUrl)
                        <button type="button"
                                @click="{{ $i === 3 ? 'open(' . ($i + 1) . ')' : 'active = ' . ($i + 1) }}"
                                class="block w-full h-full rounded-2xl overflow-hidden relative group cursor-pointer">
                            <img src="{{ $imgUrl }}" alt="" class="w-full h-full object-cover transition-transform group-hover:scale-105" loading="lazy">
                            @if ($i === 3 && $totalImgs > 5)
                                <span class="absolute inset-0 bg-teal-900/65 grid place-items-center text-white font-semibold text-sm gap-2 flex-col">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                                    </svg>
                                    Ver fotos ({{ $totalImgs }})
                                </span>
                            @elseif ($i === 3)
                                <span class="absolute inset-0 bg-teal-900/35 grid place-items-center text-white font-semibold text-sm opacity-0 group-hover:opacity-100 transition">
                                    Ver fotos +
                                </span>
                            @endif
                        </button>
                    @endforeach
                @else
                    {{-- Sin galería: rellena los 4 thumbnails con la cover --}}
                    @for ($i = 0; $i < 4; $i++)
                        <div class="rounded-2xl overflow-hidden">
                            <img src="{{ $tour->cover_url }}" alt="" class="w-full h-full object-cover" loading="lazy">
                        </div>
                    @endfor
                @endif
            </div>

            {{-- ── LIGHTBOX ── --}}
            <div x-show="lightbox" x-cloak
                 x-transition.opacity.duration.200ms
                 class="fixed inset-0 z-[60] bg-teal-900/95 flex flex-col"
                 @click.self="close()">
                {{-- Topbar --}}
                <div class="flex items-center justify-between px-5 py-4 text-white">
                    <span class="text-sm">
                        <span x-text="active + 1"></span> / {{ $totalImgs }}
                    </span>
                    <button type="button" @click="close()" class="p-2 rounded-full hover:bg-white/10 transition" aria-label="Cerrar">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Stage --}}
                <div class="relative flex-1 grid place-items-center px-4 md:px-12">
                    @if ($totalImgs > 1)
                        <button type="button" @click="prev()" class="absolute left-2 md:left-6 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 transition grid place-items-center text-white" aria-label="Anterior">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <button type="button" @click="next()" class="absolute right-2 md:right-6 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 transition grid place-items-center text-white" aria-label="Siguiente">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    @endif
                    <img :src="gallery[active]" alt="{{ $tour->title }}"
                         class="w-auto h-auto max-w-[min(100%,1400px)] max-h-full object-contain rounded-xl shadow-2xl">
                </div>

                {{-- Strip de thumbnails --}}
                <div class="px-5 pb-5 pt-3">
                    <div class="flex flex-wrap justify-center gap-2 max-w-5xl mx-auto">
                        <template x-for="(img, i) in (showAll ? gallery : gallery.slice(0, 8))" :key="i">
                            <button type="button" @click="active = i"
                                    class="w-16 h-16 md:w-20 md:h-20 rounded-lg overflow-hidden ring-2 transition flex-shrink-0"
                                    :class="active === i ? 'ring-orange-400 opacity-100' : 'ring-transparent opacity-60 hover:opacity-100'">
                                <img :src="img" alt="" class="w-full h-full object-cover" loading="lazy">
                            </button>
                        </template>
                        @if ($totalImgs > 8)
                            <button type="button" x-show="!showAll" @click="showAll = true"
                                    class="px-4 py-3 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs font-semibold uppercase tracking-wide transition">
                                Ver más ({{ $totalImgs - 8 }})
                            </button>
                            <button type="button" x-show="showAll" @click="showAll = false"
                                    class="px-4 py-3 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs font-semibold uppercase tracking-wide transition">
                                Ver menos
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <h1 class="mt-6 font-display text-3xl md:text-4xl lg:text-5xl text-teal-800 leading-tight">
                {{ $tour->title }}
            </h1>
            <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-teal-800/75">
                @if ($tour->rating)
                    <span class="inline-flex items-center gap-1">
                        <span class="text-orange-400" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
                        <span class="font-semibold text-teal-800">{{ $tour->rating }}</span>
                        <span>({{ $tour->testimonials()->count() }} reseñas)</span>
                    </span>
                @endif
                @if ($tour->duration_es ?? null)
                    <span class="inline-flex items-center gap-1.5">&#9202; {{ $tour->{"duration_{$locale}"} ?? $tour->duration_es }}</span>
                @endif
                @if ($tour->languages ?? null)
                    <span class="inline-flex items-center gap-1.5">&#128100; {{ $tour->languages }}</span>
                @endif
                @if ($tour->group_type ?? null)
                    <span class="inline-flex items-center gap-1.5">&#128101; {{ $tour->group_type }}</span>
                @endif
            </div>
        </div>

        {{-- Sidebar de reserva --}}
        <aside class="lg:sticky lg:top-24 self-start">
            <div class="bg-white rounded-2xl ring-1 ring-teal-800/10 shadow-md overflow-hidden">
                <div class="bg-orange-500 text-white px-6 py-3 flex items-center justify-between">
                    <span class="text-xs uppercase tracking-[0.2em] font-semibold">Reservar online</span>
                    <span class="text-[10px] uppercase tracking-wider opacity-90">Cancelación gratuita</span>
                </div>
                <form action="{{ route('cart.store', ['locale' => $locale]) }}"
                      method="POST" class="p-6 space-y-4">
                    @csrf
                    <input type="hidden" name="tour_id" value="{{ $tour->id }}">

                    <div class="flex items-baseline gap-2">
                        @if ($tour->price_before && $tour->price_before > $tour->price)
                            <p class="text-xs text-teal-800/60 line-through">${{ number_format((float) $tour->price_before, 0) }}</p>
                        @endif
                        <p class="font-price text-4xl text-teal-800">${{ number_format((float) $tour->price, 0) }}</p>
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
                        <span class="font-price text-2xl text-teal-800">${{ number_format((float) $tour->price, 0) }}</span>
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
<section class="bg-white pb-16 lg:pb-20" aria-label="Detalle del tour">
    <div class="container mx-auto px-5 lg:px-10 grid gap-12 lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
        <div class="tour-accordion space-y-3">
            {{-- Descripción --}}
            <details class="acc-item group bg-white border border-teal-800/15 rounded-2xl shadow-sm overflow-hidden" open>
                <summary class="flex items-center gap-3 px-6 py-4 cursor-pointer list-none">
                    <span class="w-9 h-9 rounded-full bg-cream-100 grid place-items-center text-teal-700 shrink-0" aria-hidden="true">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    </span>
                    <h2 class="font-semibold text-teal-800 flex-1 text-base">Descripción</h2>
                    <span class="acc-toggle w-8 h-8 rounded-full border border-teal-800/20 grid place-items-center text-teal-700 transition-transform group-open:rotate-45" aria-hidden="true">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    </span>
                </summary>
                <div class="px-6 pb-6 text-sm text-teal-800/85 leading-relaxed space-y-3">
                    {!! nl2br(e($tour->description)) !!}
                </div>
            </details>

            @if (count($itinerary) > 0)
                {{-- Itinerario --}}
                <details class="acc-item group bg-white border border-teal-800/15 rounded-2xl shadow-sm overflow-hidden">
                    <summary class="flex items-center gap-3 px-6 py-4 cursor-pointer list-none">
                        <span class="w-9 h-9 rounded-full bg-cream-100 grid place-items-center text-teal-700 shrink-0" aria-hidden="true">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                        </span>
                        <h2 class="font-semibold text-teal-800 flex-1 text-base">Itinerario</h2>
                        <span class="acc-toggle w-8 h-8 rounded-full border border-teal-800/20 grid place-items-center text-teal-700 transition-transform group-open:rotate-45" aria-hidden="true">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        </span>
                    </summary>
                    <div class="px-6 pb-6">
                        <ol class="relative border-l-2 border-orange-300/60 ml-2 space-y-4 pl-5">
                            @foreach ($itinerary as $i => $step)
                                @php
                                    $hour  = is_array($step) ? ($step['hour']  ?? ($step[0] ?? '')) : '';
                                    $title = is_array($step) ? ($step['title'] ?? ($step[1] ?? '')) : (string) $step;
                                    $desc  = is_array($step) ? ($step['desc']  ?? ($step[2] ?? '')) : '';
                                @endphp
                                <li class="relative">
                                    <span class="absolute -left-[1.65rem] top-1 w-3 h-3 rounded-full bg-orange-500 ring-4 ring-orange-500/15" aria-hidden="true"></span>
                                    @if ($hour)
                                        <span class="font-price text-lg text-orange-600 mr-2">{{ $hour }}</span>
                                    @endif
                                    <span class="font-semibold text-teal-800">{{ $title }}</span>
                                    @if ($desc)
                                        <p class="mt-1 text-sm text-teal-800/75 leading-relaxed">{{ $desc }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </details>
            @endif

            {{-- Recomendaciones --}}
            <details class="acc-item group bg-white border border-teal-800/15 rounded-2xl shadow-sm overflow-hidden">
                <summary class="flex items-center gap-3 px-6 py-4 cursor-pointer list-none">
                    <span class="w-9 h-9 rounded-full bg-cream-100 grid place-items-center text-teal-700 shrink-0" aria-hidden="true">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.32.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.32-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                    </span>
                    <h2 class="font-semibold text-teal-800 flex-1 text-base">Recomendaciones</h2>
                    <span class="acc-toggle w-8 h-8 rounded-full border border-teal-800/20 grid place-items-center text-teal-700 transition-transform group-open:rotate-45" aria-hidden="true">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    </span>
                </summary>
                <div class="px-6 pb-6 text-sm text-teal-800/85 leading-relaxed">
                    <ul class="grid sm:grid-cols-2 gap-y-2 gap-x-8 list-disc list-inside marker:text-orange-500">
                        @foreach ($recommendationLines as $rec)
                            <li>{{ $rec }}</li>
                        @endforeach
                    </ul>
                </div>
            </details>

            @if (count($includes) > 0)
                {{-- Servicios incluidos --}}
                <details class="acc-item group bg-white border border-teal-800/15 rounded-2xl shadow-sm overflow-hidden">
                    <summary class="flex items-center gap-3 px-6 py-4 cursor-pointer list-none">
                        <span class="w-9 h-9 rounded-full bg-state-success/10 grid place-items-center text-state-success shrink-0" aria-hidden="true">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        </span>
                        <h2 class="font-semibold text-teal-800 flex-1 text-base">Servicios incluidos</h2>
                        <span class="acc-toggle w-8 h-8 rounded-full border border-teal-800/20 grid place-items-center text-teal-700 transition-transform group-open:rotate-45" aria-hidden="true">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        </span>
                    </summary>
                    <ul class="px-6 pb-6 text-sm text-teal-800/85 space-y-2">
                        @foreach ($includes as $item)
                            <li class="flex gap-2">
                                <span class="text-state-success" aria-hidden="true">&#10003;</span>
                                {{ is_array($item) ? ($item['label'] ?? $item[0] ?? '') : $item }}
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif

            @if (count($excludes) > 0)
                {{-- Servicios no incluidos --}}
                <details class="acc-item group bg-white border border-teal-800/15 rounded-2xl shadow-sm overflow-hidden">
                    <summary class="flex items-center gap-3 px-6 py-4 cursor-pointer list-none">
                        <span class="w-9 h-9 rounded-full bg-state-error/10 grid place-items-center text-state-error shrink-0" aria-hidden="true">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </span>
                        <h2 class="font-semibold text-teal-800 flex-1 text-base">Servicios no incluidos</h2>
                        <span class="acc-toggle w-8 h-8 rounded-full border border-teal-800/20 grid place-items-center text-teal-700 transition-transform group-open:rotate-45" aria-hidden="true">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        </span>
                    </summary>
                    <ul class="px-6 pb-6 text-sm text-teal-800/85 space-y-2">
                        @foreach ($excludes as $item)
                            <li class="flex gap-2">
                                <span class="text-state-error" aria-hidden="true">&#10007;</span>
                                {{ is_array($item) ? ($item['label'] ?? $item[0] ?? '') : $item }}
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif

            {{-- Notas importantes --}}
            <details class="acc-item group bg-white border border-teal-800/15 rounded-2xl shadow-sm overflow-hidden">
                <summary class="flex items-center gap-3 px-6 py-4 cursor-pointer list-none">
                    <span class="w-9 h-9 rounded-full bg-orange-400/15 grid place-items-center text-orange-600 shrink-0" aria-hidden="true">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                    </span>
                    <h2 class="font-semibold text-teal-800 flex-1 text-base">Notas importantes</h2>
                    <span class="acc-toggle w-8 h-8 rounded-full border border-teal-800/20 grid place-items-center text-teal-700 transition-transform group-open:rotate-45" aria-hidden="true">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    </span>
                </summary>
                <div class="px-6 pb-6 text-sm text-teal-800/85 leading-relaxed">
                    {!! nl2br(e($notesText)) !!}
                </div>
            </details>
        </div>

        {{-- Información lateral --}}
        <aside class="space-y-6">
            <div class="bg-cream-100 rounded-2xl p-6">
                <h3 class="font-display text-lg text-teal-800">Información del tour</h3>
                <dl class="mt-4 grid grid-cols-2 gap-y-3 text-sm">
                    @if ($tour->duration_es ?? null)
                        <dt class="text-teal-800/60">Duración</dt>
                        <dd class="text-teal-800 font-semibold">{{ $tour->{"duration_{$locale}"} ?? $tour->duration_es }}</dd>
                    @endif
                    @if ($tour->languages ?? null)
                        <dt class="text-teal-800/60">Idiomas</dt>
                        <dd class="text-teal-800 font-semibold">{{ $tour->languages }}</dd>
                    @endif
                    @if ($tour->group_type ?? null)
                        <dt class="text-teal-800/60">Grupo</dt>
                        <dd class="text-teal-800 font-semibold">{{ $tour->group_type }}</dd>
                    @endif
                    @if ($tour->departure_time ?? null)
                        <dt class="text-teal-800/60">Salida</dt>
                        <dd class="text-teal-800 font-semibold">{{ $tour->departure_time }}</dd>
                    @endif
                    @if ($tour->return_time ?? null)
                        <dt class="text-teal-800/60">Regreso</dt>
                        <dd class="text-teal-800 font-semibold">{{ $tour->return_time }}</dd>
                    @endif
                    @if ($tour->region)
                        <dt class="text-teal-800/60">Región</dt>
                        <dd class="text-teal-800 font-semibold">{{ $tour->region->name ?? $tour->region->name_es }}</dd>
                    @endif
                    @if ($tour->category)
                        <dt class="text-teal-800/60">Categoría</dt>
                        <dd class="text-teal-800 font-semibold">{{ $tour->category->name ?? $tour->category->name_es }}</dd>
                    @endif
                </dl>
            </div>

            <div class="bg-teal-700 text-white rounded-2xl p-6">
                <h3 class="font-display text-lg">¿Necesitas ayuda?</h3>
                <p class="mt-2 text-sm text-white/85">Nuestro equipo te asesora 24/7 antes y durante tu reserva.</p>
                <a href="tel:{{ str_replace([' ', '+'], '', $contactPhone) }}" class="mt-4 inline-flex items-center gap-2 rounded-pill bg-orange-500 hover:bg-orange-600 px-5 py-2.5 text-sm font-semibold transition">
                    Llamar al {{ $contactPhone }}
                </a>
                <a href="https://wa.me/{{ str_replace([' ', '+'], '', $contactPhone) }}" class="mt-3 inline-flex items-center gap-2 rounded-pill border border-white/40 hover:bg-white/10 px-5 py-2.5 text-sm font-semibold transition">
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

        <div class="owl-carousel owl-theme owl-testimonials" data-owl-testimonials x-ignore>
            @forelse ($testimonials as $testimonial)
                <figure class="item bg-white rounded-2xl overflow-hidden flex flex-col">
                    <div class="p-6">
                        <span class="text-orange-400 text-3xl leading-none" aria-hidden="true">&rdquo;</span>
                        <blockquote class="mt-2 text-sm text-teal-800/85 leading-relaxed">
                            {{ $testimonial->body ?? $testimonial->comment }}
                        </blockquote>
                        <p class="mt-3 text-orange-400 text-sm" aria-hidden="true">
                            &#9733;&#9733;&#9733;&#9733;&#9733;
                            <span class="text-teal-800/60">{{ $testimonial->source ?? 'Google' }}</span>
                        </p>
                    </div>
                    <figcaption class="bg-teal-700 text-white px-5 py-3 flex items-center gap-3 mt-auto">
                        <span class="w-9 h-9 rounded-full bg-cream-200"></span>
                        <span class="leading-tight">
                            <span class="block font-semibold text-sm tracking-wide">{{ strtoupper($testimonial->author ?? $testimonial->name) }}</span>
                            <span class="block text-[10px] uppercase tracking-[0.15em] text-white/70">{{ $testimonial->country ?? '' }}</span>
                        </span>
                    </figcaption>
                </figure>
            @empty
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
            @endforelse
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
            <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="hidden md:inline-flex items-center gap-2 text-sm font-semibold text-orange-700 hover:text-orange-600">
                Ver catálogo &rsaquo;
            </a>
        </div>

        <div class="owl-carousel owl-theme owl-related" data-owl-related x-ignore>
            @forelse ($related as $rel)
                <article class="item group bg-white rounded-2xl overflow-hidden ring-1 ring-teal-800/5">
                    <a href="{{ route('tours.show', ['locale' => $locale, 'slug' => $rel->slug]) }}" class="block">
                        <img src="{{ $rel->cover_url }}" alt="{{ $rel->title }}" class="w-full h-48 object-cover transition-transform group-hover:scale-105" loading="lazy" width="400" height="192">
                    </a>
                    <div class="p-5">
                        <p class="text-[11px] uppercase tracking-wide text-orange-700 font-semibold">{{ $rel->region->name ?? '' }}</p>
                        <h3 class="mt-2 font-display text-base text-teal-800 leading-snug">{{ $rel->title }}</h3>
                        <div class="mt-4 flex items-end justify-between">
                            <p class="font-price text-xl text-teal-800">${{ number_format((float) $rel->price, 0) }}</p>
                            <a href="{{ route('tours.show', ['locale' => $locale, 'slug' => $rel->slug]) }}" class="text-xs font-semibold text-orange-700 hover:text-orange-600 uppercase tracking-wider">Ver tour &rsaquo;</a>
                        </div>
                    </div>
                </article>
            @empty
                <p class="text-sm text-teal-800/60 text-center py-8">No hay tours relacionados disponibles.</p>
            @endforelse
        </div>
    </div>
</section>

@endsection
