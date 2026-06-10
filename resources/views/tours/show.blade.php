@extends('layouts.app')

@php
    $locale     = app()->getLocale();
    $gallery    = $tour->gallery ?? [];
    $itinerary  = $tour->{"itinerary_{$locale}"} ?? $tour->itinerary_es ?? [];
    $includes   = $tour->{"includes_{$locale}"}  ?? $tour->includes_es  ?? [];
    $excludes   = $tour->{"excludes_{$locale}"}  ?? $tour->excludes_es  ?? [];
    $recommendations = $tour->{"recommendations_{$locale}"} ?? $tour->recommendations_es ?? null;
    $notes      = $tour->{"notes_{$locale}"}     ?? $tour->notes_es     ?? null;
    $contactPhone = \App\Models\Setting::get('contact_phone', '+51 935 542 384');

    $recommendationLines = $recommendations
        ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $recommendations))))
        : ['Llevar protector solar SPF50+', 'Lentes de sol y sombrero', 'Ropa cómoda y zapatillas', 'Cámara fotográfica', 'Agua embotellada', 'Documento de identidad'];

    $notesText = $notes ?: 'El recorrido marítimo a las Islas Ballestas puede sufrir cambios o cancelaciones por condiciones climáticas. En caso de cancelación se reembolsa el ítem correspondiente o se reagenda la salida sin costo.';

    $galleryUrls = $tour->gallery_urls;
    $totalImgs   = count($galleryUrls);
    $tourRating  = $tour->rating ?? 4.8;
    $reviewsCount = $tour->reviews_count ?? 30;
    $isMostBooked = $tour->is_most_booked ?? $tour->is_featured ?? false;
    $bookingsWeek = $tour->bookings_this_week ?? 30;

    // ── Variante oferta ──
    $hasOffer = $tour->price_before && (float)$tour->price_before > (float)$tour->price;
    $savings  = $hasOffer ? (float)$tour->price_before - (float)$tour->price : 0;
    $discountPct = $hasOffer ? (int) round($savings / (float)$tour->price_before * 100) : 0;

    // Itinerario por defecto si no existe en DB
    if (empty($itinerary)) {
        $itinerary = [
            ['time' => '06:00', 'title' => 'Recojo en tu hotel',      'description' => 'Inicio de la aventura desde Lima.',                          'icon' => 'van'],
            ['time' => '09:30', 'title' => 'Islas Ballestas',         'description' => 'Navegación para ver lobos marinos, aves y el Candelabro.',    'icon' => 'boat'],
            ['time' => '13:00', 'title' => 'Almuerzo',                'description' => 'Tiempo libre para almorzar en Paracas.',                      'icon' => 'utensils'],
            ['time' => '15:00', 'title' => 'Oasis Huacachina',        'description' => 'Visita al oasis y tiempo para fotos.',                        'icon' => 'palm'],
            ['time' => '16:00', 'title' => 'Buggies y sandboarding',  'description' => 'Aventura en las dunas con buggies y sandboarding.',           'icon' => 'buggy'],
            ['time' => '18:00', 'title' => 'Retorno a Lima',          'description' => 'Fin de la experiencia en tu hotel.',                          'icon' => 'van'],
        ];
    } else {
        $itinerary = array_map(function ($step) {
            if (!is_array($step)) return ['time' => '', 'title' => (string)$step, 'description' => '', 'icon' => 'van'];

            $rawTime  = trim((string)($step['time']  ?? $step['hour'] ?? $step[0] ?? ''));
            $rawTitle = trim((string)($step['title'] ?? $step[1]      ?? ''));
            $rawDesc  = trim((string)($step['description'] ?? $step['desc'] ?? $step[2] ?? ''));

            $time  = $rawTime;
            $title = $rawTitle;

            if (empty($title)) {
                // ¿rawTime es una hora? (ej. "4:00 am", "06:00", "1:00 pm")
                if (preg_match('/^\d{1,2}:\d{2}/u', $rawTime)) {
                    // Extraer solo la parte de hora (HH:MM am/pm)
                    preg_match('/^(\d{1,2}:\d{2}\s*(?:am|pm)?)/iu', $rawTime, $hm);
                    $time = trim($hm[1] ?? $rawTime);
                    // ¿Hay texto adicional tras la hora? → usarlo como título
                    $afterHour = trim(preg_replace('/^\d{1,2}:\d{2}\s*(?:am|pm)?\s*[:\-–]?\s*/iu', '', $rawTime));
                    if (!empty($afterHour) && !preg_match('/^(am|pm)$/iu', $afterHour)) {
                        $title = $afterHour;
                    }
                    // Si título sigue vacío, derivar de descripción: cortar en el primer
                    // corte natural (coma, punto, paréntesis, guion) ≤45 chars; el resto
                    // queda como descripción. Evita títulos verbosos cortados a mitad de frase.
                    if (empty($title) && !empty($rawDesc)) {
                        if (preg_match('/^(.{4,45}?)\s*[,.\(\)\-–—:]/u', $rawDesc, $fm)) {
                            $title   = trim($fm[1]);
                            $rest    = trim(mb_substr($rawDesc, mb_strlen($fm[0], 'UTF-8'), null, 'UTF-8'), " ,.-–—:()");
                            $rawDesc = ($rest !== '' && mb_strlen($rest, 'UTF-8') > 3) ? $rest : '';
                        } elseif (preg_match('/^(.{15,40})\s/u', $rawDesc, $fm)) {
                            $title = trim($fm[1]);
                        } else {
                            $title   = $rawDesc;
                            $rawDesc = '';
                        }
                    }
                } else {
                    // rawTime no es hora → es el título directamente
                    $time  = '';
                    $title = $rawTime;
                }
            }

            // Guardia final: nunca renderizar "am", "pm" o string vacío/símbolo como título
            if (preg_match('/^(am|pm|[^a-záéíóúüñA-Za-z]+)$/iu', trim((string)$title))) {
                $title = '';
            }
            // Capitalizar primera letra del título (la data viene en minúscula)
            if (!empty($title)) {
                $title = mb_strtoupper(mb_substr($title, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($title, 1, null, 'UTF-8');
            }

            // Mapear icono por palabra clave (título + descripción)
            $iconSource   = mb_strtolower($title . ' ' . $rawDesc, 'UTF-8');
            $explicitIcon = $step['icon'] ?? '';
            if (!empty($explicitIcon) && $explicitIcon !== 'van') {
                $icon = $explicitIcon;
            } elseif (preg_match('/bote|barco|ballestas|isla|nautic|muelle|embarcac|maritim/u', $iconSource)) {
                $icon = 'boat';
            } elseif (preg_match('/almuerzo|comida|cena|restaurante|buffet|desayuno|plato|pisco|bode|vino|vitivin/u', $iconSource)) {
                $icon = 'utensils';
            } elseif (preg_match('/oasis|huacachina|palmera|lago|laguna|desierto.*agua/u', $iconSource)) {
                $icon = 'palm';
            } elseif (preg_match('/buggi|buggy|sandboard|duna|arena|desliz/u', $iconSource)) {
                $icon = 'buggy';
            } elseif (preg_match('/recojo|recoge|recog|retorno|retorn|regres|traslad|llegada.*hotel|hotel.*llegad/u', $iconSource)) {
                $icon = 'van';
            } else {
                $icon = $explicitIcon ?: 'van';
            }

            return [
                'time'        => $time,
                'title'       => $title,
                'description' => $rawDesc,
                'icon'        => $icon,
            ];
        }, $itinerary);
    }

    // Tours relacionados (excluye el actual, máx 3)
    $sidebarTours = \App\Models\Tour::published()
        ->where('id', '!=', $tour->id)
        ->ordered()
        ->take(3)
        ->get();

    if ($sidebarTours->isEmpty()) {
        $sidebarTours = collect([
            (object)['title' => 'Tour a Machu Picchu Full Day',        'slug' => '#', 'price' => 180, 'price_before' => null, 'rating' => 4.9, 'reviews_count' => 120, 'cover_url' => asset('assets/banners/hero-machu-picchu.png'), 'duration' => 'Full Day'],
            (object)['title' => 'Montaña de 7 Colores Full Day',       'slug' => '#', 'price' => 75,  'price_before' => null, 'rating' => 4.8, 'reviews_count' => 85,  'cover_url' => asset('assets/banners/banner-hero.jpg'),         'duration' => 'Full Day'],
            (object)['title' => 'City Tour Lima + Catacumbas',         'slug' => '#', 'price' => 45,  'price_before' => null, 'rating' => 4.7, 'reviews_count' => 60,  'cover_url' => asset('assets/banners/banner-hero.jpg'),         'duration' => 'Medio día'],
        ]);
    }
@endphp

@php
    // Title Case en español: capitaliza palabras significativas, deja artículos/preposiciones
    // cortas en minúscula (salvo la primera palabra). El título viene en MAYÚSCULAS desde DB.
    $titleDisplay = (function ($raw) {
        $stop = ['de','del','la','las','el','los','y','o','u','e','a','al','en','con','para','por','un','una'];
        $words = preg_split('/\s+/u', mb_strtolower(trim($raw), 'UTF-8'));
        $out = [];
        foreach ($words as $i => $w) {
            if ($w === '') continue;
            $out[] = ($i > 0 && in_array($w, $stop, true))
                ? $w
                : mb_convert_case($w, MB_CASE_TITLE, 'UTF-8');
        }
        return implode(' ', $out);
    })($tour->title);
@endphp
@section('title', $titleDisplay . ' — ' . __('seo.site_name'))
@section('description', 'Reserva online: ' . $tour->title . '. Salidas diarias, guías oficiales, transporte cómodo. Mejor precio garantizado.')

@push('schema')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'TouristTrip',
    'name'     => $tour->title,
    'description' => 'Reserva online: ' . $tour->title,
    'image'    => $galleryUrls,
    'aggregateRating' => $tourRating ? [
        '@type'       => 'AggregateRating',
        'ratingValue' => $tourRating,
        'reviewCount' => $reviewsCount ?: 1,
    ] : null,
    'offers' => [
        '@type'        => 'Offer',
        'price'        => (float) $tour->price,
        'priceCurrency'=> 'USD',
        'availability' => 'https://schema.org/InStock',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@push('head')
<style>
/* ── Acordeón nativo ── */
details summary::-webkit-details-marker { display: none; }
details summary::marker               { display: none; }
details[open] .acc-chevron            { transform: rotate(180deg); }
.acc-chevron                          { transition: transform .25s ease; }

/* ── Carrusel highlights ── */
.highlights-track {
    display: flex;
    gap: .75rem;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    padding-bottom: .25rem;
}
.highlights-track::-webkit-scrollbar { display: none; }
.highlights-track > * {
    flex: 0 0 calc(50% - .375rem);
    scroll-snap-align: start;
}
@media (min-width: 640px) {
    .highlights-track > * { flex: 0 0 calc(33.333% - .5rem); }
}
@media (min-width: 1024px) {
    .highlights-track > * { flex: 0 0 calc(25% - .5625rem); }
}

/* ── Safe area bottom ── */
.sticky-bar { padding-bottom: env(safe-area-inset-bottom, 0); }
</style>
@endpush

@section('content')

{{-- ─────────── BREADCRUMB ─────────── --}}
<section class="bg-white pt-5 pb-2">
    <div class="container mx-auto px-5">
        <nav aria-label="Breadcrumb" class="text-xs text-teal-800/60">
            <a href="{{ route('home', ['locale' => $locale]) }}" class="hover:text-orange-500 transition-colors">Inicio</a>
            <span class="mx-1" aria-hidden="true">/</span>
            <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="hover:text-orange-500 transition-colors">Tours</a>
            <span class="mx-1" aria-hidden="true">/</span>
            <span class="text-teal-800">{{ \Illuminate\Support\Str::limit($titleDisplay, 55) }}</span>
        </nav>
    </div>
</section>

{{-- ─────────── CONTENIDO PRINCIPAL — single column ─────────── --}}
<section class="bg-cream-100 pb-28"
         x-data="{
             gallery: {{ json_encode($galleryUrls) }},
             active: 0,
             lightbox: false,
             showAll: false,
             open(i) { this.active = (i !== undefined) ? i : this.active; this.lightbox = true; document.body.style.overflow = 'hidden'; },
             close() { this.lightbox = false; this.showAll = false; document.body.style.overflow = ''; },
             prev() { this.active = (this.active - 1 + this.gallery.length) % this.gallery.length; },
             next() { this.active = (this.active + 1) % this.gallery.length; }
         }"
         @keydown.escape.window="lightbox && close()"
         @keydown.arrow-left.window="lightbox && prev()"
         @keydown.arrow-right.window="lightbox && next()">

    <div class="max-w-3xl xl:max-w-4xl mx-auto px-4 sm:px-5 pt-4 space-y-5 lg:space-y-6">

        {{-- ══════════════════════════════════════
             1. HERO IMAGEN + GALERÍA + BADGES
        ══════════════════════════════════════ --}}
        <div class="relative">
            <div class="relative rounded-3xl overflow-hidden h-[360px] sm:h-[420px] lg:h-[520px] bg-teal-800/10">
                <img :src="gallery[active] || {{ json_encode($galleryUrls[0] ?? $tour->cover_url) }}"
                     alt="{{ $tour->title }}"
                     class="w-full h-full object-cover"
                     loading="eager"
                     width="800" height="520">

                {{-- Badges flotantes arriba-izquierda --}}
                <div class="absolute top-4 left-4 flex flex-col gap-2 z-10">
                    @if ($isMostBooked)
                        <span class="inline-flex items-center gap-1.5 bg-white/95 text-teal-800 text-xs font-semibold px-3 py-1.5 rounded-full shadow-sm">
                            <svg class="w-4 h-4 text-orange-400 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                            Más reservado
                        </span>
                    @endif
                    <span class="inline-flex items-center gap-1.5 bg-teal-800 text-white text-xs font-semibold px-3 py-1.5 rounded-full shadow-sm">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Cancelación gratuita
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-orange-500 text-white text-xs font-semibold px-3 py-1.5 rounded-full shadow-sm">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M12.963 2.286a.75.75 0 00-1.071-.136 9.742 9.742 0 00-3.539 6.177A7.547 7.547 0 016.648 6.61a.75.75 0 00-1.152.082A9 9 0 1015.68 4.534a7.46 7.46 0 01-2.717-2.248zM15.75 14.25a3.75 3.75 0 11-7.313-1.172c.628.465 1.35.81 2.133 1a5.99 5.99 0 011.925-3.545 3.75 3.75 0 013.255 3.717z" clip-rule="evenodd"/>
                        </svg>
                        Últimos cupos
                    </span>
                </div>

                {{-- Pill contador galería abajo-derecha --}}
                <button type="button"
                        @click="open(active)"
                        class="absolute bottom-4 right-4 z-10 inline-flex items-center gap-1.5 bg-black/55 hover:bg-black/70 text-white text-xs font-semibold px-3 py-1.5 rounded-full transition-colors"
                        aria-label="Ver galería de fotos">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>
                    </svg>
                    1 / {{ $totalImgs }}
                </button>
            </div>
        </div>

        {{-- ── LIGHTBOX ── --}}
        <div x-show="lightbox" x-cloak
             x-transition.opacity.duration.200ms
             class="fixed inset-0 z-[60] bg-teal-900/95 flex flex-col"
             @click.self="close()">
            <div class="flex items-center justify-between px-5 py-4 text-white shrink-0">
                <span class="text-sm font-semibold"><span x-text="active + 1"></span> / {{ $totalImgs }}</span>
                <button type="button" @click="close()" class="p-2 rounded-full hover:bg-white/10 transition" aria-label="Cerrar galería">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="relative flex-1 grid place-items-center px-4 md:px-12 overflow-hidden">
                @if ($totalImgs > 1)
                    <button type="button" @click="prev()" class="absolute left-2 md:left-6 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 transition grid place-items-center text-white z-10" aria-label="Foto anterior">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" @click="next()" class="absolute right-2 md:right-6 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 transition grid place-items-center text-white z-10" aria-label="Foto siguiente">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                @endif
                <img :src="gallery[active]" alt="{{ $tour->title }}"
                     class="w-auto h-auto max-w-full max-h-full object-contain rounded-xl shadow-2xl">
            </div>
            <div class="px-5 pb-5 pt-3 shrink-0">
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

        {{-- ══════════════════════════════════════
             2. TÍTULO + RATING
        ══════════════════════════════════════ --}}
        <div>
            <h1 class="font-display text-3xl lg:text-4xl text-teal-800 leading-tight">
                {{ $titleDisplay }}
            </h1>
            <div class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1.5">
                <span class="inline-flex gap-0.5" aria-hidden="true">
                    @for ($s = 0; $s < 5; $s++)
                        <svg class="w-5 h-5 text-orange-400 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    @endfor
                </span>
                <span class="font-bold text-teal-800 text-sm">{{ $tourRating }}</span>
                <span class="text-teal-800/60 text-sm">({{ $reviewsCount }} opiniones verificadas)</span>
                <span class="inline-flex items-center gap-1 bg-state-success/10 text-state-success text-[11px] font-semibold px-2 py-0.5 rounded-full" aria-label="Reseñas verificadas">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    Verificado
                </span>
            </div>
        </div>

        {{-- ══════════════════════════════════════
             3. CARD PRECIO + CTA
             Variante normal vs. oferta
        ══════════════════════════════════════ --}}
        @if ($hasOffer)
            {{-- VARIANTE OFERTA --}}
            <div class="bg-white rounded-2xl ring-1 ring-teal-800/10 shadow-sm overflow-hidden">
                {{-- Banner naranja top --}}
                <div class="bg-orange-500 px-4 py-2 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4 text-white shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M12.963 2.286a.75.75 0 00-1.071-.136 9.742 9.742 0 00-3.539 6.177A7.547 7.547 0 016.648 6.61a.75.75 0 00-1.152.082A9 9 0 1015.68 4.534a7.46 7.46 0 01-2.717-2.248zM15.75 14.25a3.75 3.75 0 11-7.313-1.172c.628.465 1.35.81 2.133 1a5.99 5.99 0 011.925-3.545 3.75 3.75 0 013.255 3.717z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-white text-xs font-bold uppercase tracking-widest">OFERTA ESPECIAL</span>
                </div>
                {{-- Body card oferta --}}
                <div class="p-4">
                    {{-- Fila superior: precio antes + precio actual --}}
                    <p class="text-sm text-teal-800/50 line-through font-price leading-none mb-0.5">Antes US${{ number_format((float)$tour->price_before, 0) }}</p>
                    <p class="font-price text-4xl font-bold text-teal-800 leading-none">US${{ number_format((float)$tour->price, 0) }}</p>
                    <p class="text-[11px] text-teal-800/55 mt-0.5 mb-3">por persona</p>
                    {{-- Fila inferior: pill Ahorras + CTA (siempre en row, incluso en 390px) --}}
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 bg-cream-100 ring-1 ring-teal-800/10 rounded-full px-3 py-1.5 shrink-0">
                            <svg class="w-3.5 h-3.5 text-teal-700 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-xs font-bold text-teal-800">Ahorras US${{ number_format($savings, 0) }}</span>
                        </span>
                        <button type="button"
                                onclick="document.getElementById('sticky-reservar')?.click()"
                                class="inline-flex items-center justify-center gap-2 bg-teal-800 hover:bg-teal-700 text-white font-semibold text-sm rounded-full py-2.5 px-5 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-orange-500">
                            Reservar ahora
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════
                 3b. CHIPS TRUST (solo variante oferta)
            ══════════════════════════════════════ --}}
            <div class="grid grid-cols-2 sm:flex sm:flex-row gap-3">
                <div class="flex items-center gap-3 bg-white ring-1 ring-teal-800/10 rounded-2xl px-4 py-3">
                    <svg class="w-5 h-5 text-teal-700 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                    </svg>
                    <div>
                        <p class="text-xs font-bold text-teal-800">Cancelación gratuita</p>
                        <p class="text-[11px] text-teal-800/55">Hasta 24h antes</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 bg-white ring-1 ring-teal-800/10 rounded-2xl px-4 py-3">
                    <svg class="w-5 h-5 text-teal-700 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                    </svg>
                    <div>
                        <p class="text-xs font-bold text-teal-800">Pago seguro</p>
                        <p class="text-[11px] text-teal-800/55">y protegido</p>
                    </div>
                </div>
            </div>

        @else
            {{-- VARIANTE NORMAL --}}
            <div class="bg-white rounded-2xl ring-1 ring-teal-800/10 shadow-sm p-4">
                <div class="flex items-center gap-4">
                    <div class="shrink-0 flex flex-col">
                        <p class="text-[10px] uppercase tracking-widest text-teal-800/60 font-semibold leading-none mb-1">Desde</p>
                        <p class="font-price text-4xl font-bold text-teal-800 leading-none">US${{ number_format((float)$tour->price, 0) }}</p>
                        <p class="text-[11px] text-teal-800/55 mt-0.5">por persona</p>
                    </div>
                    <div class="flex items-start gap-1.5 flex-1 min-w-0">
                        <svg class="w-5 h-5 text-teal-700 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                        </svg>
                        <div>
                            <p class="text-xs font-bold text-teal-800">Cancelación gratuita</p>
                            <p class="text-[11px] text-teal-800/55">Hasta 24h antes</p>
                        </div>
                    </div>
                </div>
                <button type="button"
                        onclick="document.getElementById('sticky-reservar')?.click()"
                        class="mt-4 w-full inline-flex items-center justify-center gap-2 bg-teal-800 hover:bg-teal-700 text-white font-semibold text-sm rounded-full py-3.5 px-6 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-orange-500">
                    Reservar ahora
                    <span class="w-6 h-6 rounded-full bg-orange-500 grid place-items-center shrink-0" aria-hidden="true">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </span>
                </button>
            </div>
        @endif

        {{-- ══════════════════════════════════════
             4. BANNER SOCIAL PROOF
        ══════════════════════════════════════ --}}
        <div class="inline-flex items-center gap-2 bg-orange-50 border border-orange-200 text-teal-800 text-sm font-medium px-4 py-2.5 rounded-full w-full justify-center">
            <svg class="w-4 h-4 text-orange-500 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M12.963 2.286a.75.75 0 00-1.071-.136 9.742 9.742 0 00-3.539 6.177A7.547 7.547 0 016.648 6.61a.75.75 0 00-1.152.082A9 9 0 1015.68 4.534a7.46 7.46 0 01-2.717-2.248zM15.75 14.25a3.75 3.75 0 11-7.313-1.172c.628.465 1.35.81 2.133 1a5.99 5.99 0 011.925-3.545 3.75 3.75 0 013.255 3.717z" clip-rule="evenodd"/>
            </svg>
            <span><strong>{{ $bookingsWeek }}</strong> viajeros reservaron este tour esta semana</span>
        </div>

        {{-- ══════════════════════════════════════
             5. ROW 4 FEATURES CIRCULARES
        ══════════════════════════════════════ --}}
        <div class="grid grid-cols-4 gap-2 sm:gap-4">
            @foreach ([
                ['icon' => 'van',    'label' => 'Recojo incluido',      'caption' => 'Desde tu hotel'],
                ['icon' => 'globe',  'label' => 'Guía bilingüe',        'caption' => 'Español / Inglés'],
                ['icon' => 'shield', 'label' => 'Cancelación gratuita', 'caption' => 'Hasta 24h antes'],
                ['icon' => 'group',  'label' => 'Grupos pequeños',      'caption' => 'Experiencia personalizada'],
            ] as $feature)
                <div class="flex flex-col items-center text-center gap-1.5">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-white ring-1 ring-teal-800/15 grid place-items-center text-teal-700 shadow-sm">
                        @if ($feature['icon'] === 'van')
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                        @elseif ($feature['icon'] === 'globe')
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                        @elseif ($feature['icon'] === 'shield')
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                        @else
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                        @endif
                    </div>
                    <p class="text-[11px] sm:text-xs font-bold text-teal-800 leading-tight">{{ $feature['label'] }}</p>
                    <p class="text-[9px] sm:text-[11px] text-teal-800/55 leading-tight">{{ $feature['caption'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- ══════════════════════════════════════
             6. ¿QUÉ VIVIRÁS? — CARRUSEL HIGHLIGHTS
        ══════════════════════════════════════ --}}
        <div x-data="{
                activeSlide: 0,
                totalSlides: 4,
                updateDot(el) {
                    const firstChild = el.children[0];
                    if (!firstChild) return;
                    const itemW = firstChild.offsetWidth + 12; // 12 = gap (.75rem)
                    this.activeSlide = Math.round(el.scrollLeft / itemW);
                }
             }">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-display text-xl lg:text-2xl text-teal-800">¿Qué vivirás en este tour?</h2>
                <button type="button" class="text-sm font-semibold text-orange-500 hover:text-orange-600 transition-colors flex items-center gap-1">
                    Ver más <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                </button>
            </div>
            <div class="highlights-track"
                 x-ref="track"
                 @scroll.passive="updateDot($el)">
                @php
                $highlights = [
                    ['img' => 'tours/OASIS-DE-HUACACHINA-CON-BUGGIE-11.jpg', 'title' => 'Islas Ballestas',      'desc' => 'Vida marina increíble',      'icon' => 'wave'],
                    ['img' => 'tours/OASIS-DE-HUACACHINA-CON-BUGGIE-4.jpg',  'title' => 'Sandboarding',         'desc' => 'Aventura en las dunas',      'icon' => 'mountain'],
                    ['img' => 'tours/OASIS-DE-HUACACHINA-CON-BUGGIE-12.jpg', 'title' => 'Oasis Huacachina',     'desc' => 'Paisajes únicos',            'icon' => 'palm'],
                    ['img' => 'tours/OASIS-DE-HUACACHINA-CON-BUGGIE-10-scaled-1.jpg', 'title' => 'Degustación de vinos', 'desc' => 'Viñedos y piscos locales', 'icon' => 'wine'],
                ];
                foreach ($highlights as $k => $h) {
                    $path = public_path('storage/' . $h['img']);
                    if (!file_exists($path) && count($galleryUrls) > $k) {
                        $highlights[$k]['img'] = null;
                    }
                }
                @endphp
                @foreach ($highlights as $k => $hl)
                    <div class="bg-white rounded-2xl overflow-hidden shadow-sm ring-1 ring-teal-800/5">
                        <div class="h-32 lg:h-40 overflow-hidden relative bg-teal-800/10">
                            <img src="{{ $hl['img'] ? asset('storage/' . $hl['img']) : ($galleryUrls[$k] ?? $tour->cover_url) }}"
                                 alt="{{ $hl['title'] }}"
                                 class="w-full h-full object-cover"
                                 loading="lazy"
                                 width="240" height="160">
                        </div>
                        <div class="p-3 pt-4">
                            <div class="w-8 h-8 rounded-full bg-white ring-1 ring-teal-800/15 grid place-items-center text-teal-700 mb-2 shadow-sm">
                                @if ($hl['icon'] === 'wave')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 15s1-1 3-1 3 2 6 2 3-1 3-1v3s-1 1-3 1-3-2-6-2-3 1-3 1v-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 9s1-1 3-1 3 2 6 2 3-1 3-1"/></svg>
                                @elseif ($hl['icon'] === 'mountain')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M18.75 10.5L12 3.75 5.25 10.5"/></svg>
                                @elseif ($hl['icon'] === 'palm')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c0 0-4 3-4 8h8c0-5-4-8-4-8zm0 8v10m-3 0h6"/></svg>
                                @else
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"/></svg>
                                @endif
                            </div>
                            <p class="text-xs font-bold text-teal-800 leading-tight">{{ $hl['title'] }}</p>
                            <p class="text-[11px] text-teal-800/60 mt-0.5 leading-tight line-clamp-2">{{ $hl['desc'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            {{-- Dots --}}
            <div class="flex justify-center gap-2 mt-3" aria-hidden="true">
                @for ($d = 0; $d < 4; $d++)
                    <button type="button"
                            @click="activeSlide = {{ $d }}; (function(el, idx){ const itemW = (el.children[0]?.offsetWidth ?? 0) + 12; el.scrollTo({ left: idx * itemW, behavior: 'smooth' }); })($refs.track, {{ $d }})"
                            class="h-2 rounded-full transition-all duration-200"
                            :class="activeSlide === {{ $d }} ? 'bg-orange-500 w-4' : 'bg-teal-800/25 w-2'"
                            aria-label="Slide {{ $d + 1 }}">
                    </button>
                @endfor
            </div>
        </div>

        {{-- ══════════════════════════════════════
             7. ITINERARIO DEL TOUR
        ══════════════════════════════════════ --}}
        <div class="pb-2">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-display text-xl lg:text-2xl text-teal-800">Itinerario del tour</h2>
                <button type="button" class="text-sm font-semibold text-orange-500 hover:text-orange-600 transition-colors flex items-center gap-1">
                    Ver completo <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                </button>
            </div>
            <ol class="space-y-0" aria-label="Itinerario del tour">
                @foreach ($itinerary as $idx => $step)
                    <li class="relative flex gap-4 {{ !$loop->last ? 'pb-6' : '' }}">
                        @if (!$loop->last)
                            <div class="absolute left-[11px] top-6 bottom-0 w-0.5 bg-teal-800/18" aria-hidden="true"></div>
                        @endif
                        <div class="shrink-0 w-6 h-6 rounded-full bg-orange-500 ring-4 ring-orange-500/15 mt-0.5 grid place-items-center" aria-hidden="true">
                            <span class="w-2 h-2 rounded-full bg-white"></span>
                        </div>
                        <div class="flex-1 min-w-0 -mt-0.5">
                            <div class="flex items-center gap-2 mb-0.5">
                                @if (!empty($step['time']))
                                    <span class="font-price text-sm font-bold text-teal-800 tabular-nums">{{ $step['time'] }}</span>
                                @endif
                                <span class="text-teal-700/60" aria-hidden="true">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                        @if (($step['icon'] ?? '') === 'boat')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                                        @elseif (($step['icon'] ?? '') === 'utensils')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8.25v-1.5m0 1.5c-1.355 0-2.697.056-4.024.166C6.845 8.51 6 9.473 6 10.608v2.513m6-4.87c1.355 0 2.697.056 4.025.166C17.155 8.51 18 9.473 18 10.608v2.513m-3-4.87v-1.5m-3 10.125v2.25m0-2.25a.375.375 0 100-.75.375.375 0 000 .75z"/>
                                        @elseif (($step['icon'] ?? '') === 'palm')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c0 0-4 3-4 8h8c0-5-4-8-4-8zm0 8v10"/>
                                        @elseif (($step['icon'] ?? '') === 'buggy')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                                        @else
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                                        @endif
                                    </svg>
                                </span>
                            </div>
                            @if (!empty($step['title']) && $step['title'] !== $step['time'])
                                <p class="text-sm font-bold text-teal-800 clamp-2">{{ $step['title'] }}</p>
                            @endif
                            @if (!empty($step['description']))
                                <p class="text-sm text-teal-800/65 leading-relaxed mt-0.5 clamp-3">{{ $step['description'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        {{-- ══════════════════════════════════════
             8. INFORMACIÓN IMPORTANTE — ACORDEÓN
             Normal: 4 ítems | Oferta: 5 ítems
        ══════════════════════════════════════ --}}
        <div class="bg-white rounded-2xl ring-1 ring-teal-800/10 shadow-sm overflow-hidden">
            <h2 class="font-display text-base text-teal-800 px-5 pt-5 pb-3">Información importante</h2>
            <div class="divide-y divide-teal-800/8">

                {{-- Descripción --}}
                <details class="group">
                    <summary class="flex items-center gap-3 px-5 py-4 cursor-pointer select-none">
                        <span class="w-10 h-10 rounded-xl bg-teal-800 grid place-items-center shrink-0" aria-hidden="true">
                            <svg class="w-5 h-5 text-orange-300" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-teal-800">Descripción</p>
                            <p class="text-xs text-teal-800/55 truncate">Conoce Huacachina y las Islas Ballestas en un día inolvidable.</p>
                        </div>
                        <svg class="w-5 h-5 text-orange-500 shrink-0 acc-chevron" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="px-5 pb-5 pt-1 text-sm text-teal-800/80 leading-relaxed">
                        {!! nl2br(e($tour->description ?? $notesText)) !!}
                    </div>
                </details>

                @if ($hasOffer)
                {{-- Itinerario completo (solo variante oferta) --}}
                <details class="group">
                    <summary class="flex items-center gap-3 px-5 py-4 cursor-pointer select-none">
                        <span class="w-10 h-10 rounded-xl bg-teal-800 grid place-items-center shrink-0" aria-hidden="true">
                            <svg class="w-5 h-5 text-orange-300" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"/></svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-teal-800">Itinerario completo</p>
                            <p class="text-xs text-teal-800/55 truncate">Revisa el plan detallado del tour.</p>
                        </div>
                        <svg class="w-5 h-5 text-orange-500 shrink-0 acc-chevron" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="px-5 pb-5 pt-1 text-sm text-teal-800/80 leading-relaxed">
                        <ol class="space-y-2">
                            @foreach ($itinerary as $step)
                                <li class="flex gap-2">
                                    @if (!empty($step['time']))
                                        <span class="font-price font-bold text-teal-800 shrink-0 tabular-nums min-w-[48px]">{{ $step['time'] }}</span>
                                    @endif
                                    <span>{{ $step['title'] }}{{ !empty($step['description']) ? ' — ' . $step['description'] : '' }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </details>
                @endif

                {{-- Servicios incluidos --}}
                <details class="group">
                    <summary class="flex items-center gap-3 px-5 py-4 cursor-pointer select-none">
                        <span class="w-10 h-10 rounded-xl bg-teal-800 grid place-items-center shrink-0" aria-hidden="true">
                            <svg class="w-5 h-5 text-orange-300" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"/></svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-teal-800">Servicios incluidos</p>
                            <p class="text-xs text-teal-800/55 truncate">Todo lo que está incluido en tu experiencia.</p>
                        </div>
                        <svg class="w-5 h-5 text-orange-500 shrink-0 acc-chevron" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="px-5 pb-5 pt-1 text-sm text-teal-800/80 space-y-4">
                        @if (count($includes) > 0)
                            <div>
                                <p class="text-xs font-semibold text-state-success uppercase tracking-wide mb-2">Incluye</p>
                                <ul class="space-y-1.5">
                                    @foreach ($includes as $item)
                                        <li class="flex gap-2 items-start">
                                            <svg class="w-4 h-4 text-state-success shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                            {{ is_array($item) ? ($item['label'] ?? $item[0] ?? '') : $item }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if (count($excludes) > 0)
                            <div>
                                <p class="text-xs font-semibold text-state-error uppercase tracking-wide mb-2">No incluye</p>
                                <ul class="space-y-1.5">
                                    @foreach ($excludes as $item)
                                        <li class="flex gap-2 items-start">
                                            <svg class="w-4 h-4 text-state-error shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            {{ is_array($item) ? ($item['label'] ?? $item[0] ?? '') : $item }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if (count($includes) === 0 && count($excludes) === 0)
                            <p class="text-teal-800/55 text-xs">Consulta los servicios incluidos con nuestro equipo.</p>
                        @endif
                    </div>
                </details>

                {{-- Recomendaciones --}}
                <details class="group">
                    <summary class="flex items-center gap-3 px-5 py-4 cursor-pointer select-none">
                        <span class="w-10 h-10 rounded-xl bg-teal-800 grid place-items-center shrink-0" aria-hidden="true">
                            <svg class="w-5 h-5 text-orange-300" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18"/></svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-teal-800">Recomendaciones</p>
                            <p class="text-xs text-teal-800/55 truncate">Consejos para que disfrutes al máximo tu experiencia.</p>
                        </div>
                        <svg class="w-5 h-5 text-orange-500 shrink-0 acc-chevron" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="px-5 pb-5 pt-1 text-sm text-teal-800/80">
                        <ul class="space-y-1.5">
                            @foreach ($recommendationLines as $rec)
                                <li class="flex gap-2 items-start">
                                    <span class="text-orange-500 shrink-0 mt-0.5" aria-hidden="true">&#9679;</span>
                                    {{ $rec }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </details>

                {{-- Notas importantes --}}
                <details class="group">
                    <summary class="flex items-center gap-3 px-5 py-4 cursor-pointer select-none">
                        <span class="w-10 h-10 rounded-xl bg-teal-800 grid place-items-center shrink-0" aria-hidden="true">
                            <svg class="w-5 h-5 text-orange-300" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-teal-800">Notas importantes</p>
                            <p class="text-xs text-teal-800/55 truncate">Lo que debes saber antes de reservar.</p>
                        </div>
                        <svg class="w-5 h-5 text-orange-500 shrink-0 acc-chevron" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="px-5 pb-5 pt-1 text-sm text-teal-800/80 leading-relaxed">
                        {!! nl2br(e($notesText)) !!}
                    </div>
                </details>

            </div>
        </div>

        {{-- ══════════════════════════════════════
             9. OPINIONES DE NUESTROS VIAJEROS
        ══════════════════════════════════════ --}}
        <div class="bg-white rounded-2xl ring-1 ring-teal-800/10 shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-display text-base lg:text-lg text-teal-800">Opiniones de nuestros viajeros</h2>
                <a href="#" class="text-xs font-semibold text-orange-500 hover:text-orange-600 transition-colors">Ver todas</a>
            </div>
            {{-- Plataformas Google + Tripadvisor --}}
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div class="bg-cream-100 rounded-xl p-3">
                    <div class="flex items-center gap-1.5 mb-1">
                        <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                        <span class="text-xs font-bold text-teal-800">Google</span>
                    </div>
                    <p class="font-price text-xl font-bold text-teal-800">4.7<span class="text-teal-800/40 text-xs">/5</span></p>
                    <div class="flex gap-0.5 my-0.5" aria-hidden="true">
                        @for ($s=0;$s<5;$s++)<svg class="w-3 h-3 text-orange-400 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>@endfor
                    </div>
                    <p class="text-[11px] text-teal-800/55 mb-1.5">22 reseñas</p>
                    <a href="#" class="text-[11px] font-semibold text-teal-800 hover:text-orange-500 transition-colors flex items-center gap-0.5">
                        Ver en Google <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </a>
                </div>
                <div class="bg-cream-100 rounded-xl p-3">
                    <div class="flex items-center gap-1.5 mb-1">
                        {{-- Tripadvisor búho SVG --}}
                        <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="11" fill="#00AA6C"/>
                            <circle cx="9" cy="10" r="2.5" fill="white"/>
                            <circle cx="15" cy="10" r="2.5" fill="white"/>
                            <circle cx="9" cy="10" r="1.2" fill="#00AA6C"/>
                            <circle cx="15" cy="10" r="1.2" fill="#00AA6C"/>
                            <path d="M7 14.5 C8.5 16.5 15.5 16.5 17 14.5" stroke="white" stroke-width="1.3" stroke-linecap="round" fill="none"/>
                        </svg>
                        <span class="text-xs font-bold text-teal-800">Tripadvisor</span>
                    </div>
                    <p class="font-price text-xl font-bold text-teal-800">4.6<span class="text-teal-800/40 text-xs">/5</span></p>
                    <div class="flex gap-0.5 my-0.5" aria-hidden="true">
                        @for ($s=0;$s<4;$s++)<svg class="w-3 h-3 text-orange-400 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>@endfor
                        <svg class="w-3 h-3 text-orange-200 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    </div>
                    <p class="text-[11px] text-teal-800/55 mb-1.5">8 reseñas</p>
                    <a href="#" class="text-[11px] font-semibold text-teal-800 hover:text-orange-500 transition-colors flex items-center gap-0.5">
                        Ver en Tripadvisor <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </a>
                </div>
            </div>

            {{-- Reviews individuales --}}
            <div class="space-y-3">
                @php
                $hardcodedReviews = [
                    ['name' => 'María Fernanda', 'date' => '12 may 2024', 'text' => '¡Increíble experiencia! Todo muy bien organizado y el guía súper amable. 100% recomendado.', 'initials' => 'MF', 'color' => 'bg-teal-700'],
                    ['name' => 'Jorge Luis',     'date' => '5 may 2024',  'text' => 'El mejor tour que hice en Perú. Huacachina es mágica.',                                       'initials' => 'JL', 'color' => 'bg-orange-500'],
                ];
                @endphp
                @foreach ($hardcodedReviews as $review)
                    <div class="p-4 bg-cream-100 rounded-xl">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-full {{ $review['color'] }} text-white grid place-items-center text-xs font-bold shrink-0" aria-hidden="true">{{ $review['initials'] }}</div>
                                <div>
                                    <p class="text-xs font-bold text-teal-800 flex items-center gap-1">
                                        {{ $review['name'] }}
                                        <span class="inline-flex items-center gap-0.5 bg-state-success/10 text-state-success text-[9px] font-semibold px-1.5 py-0.5 rounded-full" aria-label="Verificado">
                                            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        </span>
                                    </p>
                                    <div class="flex gap-0.5 mt-0.5" aria-hidden="true">
                                        @for ($s=0;$s<5;$s++)<svg class="w-3 h-3 text-orange-400 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>@endfor
                                    </div>
                                </div>
                            </div>
                            <span class="text-[10px] text-teal-800/45 shrink-0">{{ $review['date'] }}</span>
                        </div>
                        <p class="text-xs text-teal-800/75 leading-relaxed">{{ $review['text'] }}</p>
                    </div>
                @endforeach
            </div>
            <a href="#" class="mt-4 w-full inline-flex items-center justify-center gap-2 border border-teal-800/20 text-teal-800 hover:bg-cream-100 text-xs font-semibold rounded-full py-2.5 px-5 transition-colors">
                Ver todas las opiniones
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
            </a>
        </div>

        {{-- ══════════════════════════════════════
             10. OTROS VIAJEROS TAMBIÉN RESERVARON
        ══════════════════════════════════════ --}}
        <div class="bg-white rounded-2xl ring-1 ring-teal-800/10 shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-display text-base lg:text-lg text-teal-800">Otros viajeros también reservaron</h2>
                <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="text-xs font-semibold text-orange-500 hover:text-orange-600 transition-colors">Ver todos</a>
            </div>
            <div class="space-y-3">
                @foreach ($sidebarTours as $idx => $rel)
                    @php
                        $relSlug    = is_object($rel) ? ($rel->slug ?? '#') : '#';
                        $relTitle   = is_object($rel) ? ($rel->title_es ?? $rel->title ?? 'Tour') : ($rel->title ?? 'Tour');
                        $relPrice   = is_object($rel) ? ($rel->price ?? 0) : ($rel->price ?? 0);
                        $relBefore  = is_object($rel) ? ($rel->price_before ?? null) : ($rel->price_before ?? null);
                        $relRating  = is_object($rel) ? ($rel->rating ?? 4.8) : ($rel->rating ?? 4.8);
                        $relReviews = is_object($rel) ? ($rel->reviews_count ?? 0) : ($rel->reviews_count ?? 0);
                        $relCover   = is_object($rel) && method_exists($rel, 'getCoverUrlAttribute') ? $rel->cover_url : ($rel->cover_url ?? asset('assets/banners/banner-hero.jpg'));
                        $relDuration= is_object($rel) ? ($rel->duration ?? 'Full Day') : ($rel->duration ?? 'Full Day');
                        $relHasOffer = $relBefore && (float)$relBefore > (float)$relPrice;
                        $relPct = $relHasOffer ? (int) round(((float)$relBefore - (float)$relPrice) / (float)$relBefore * 100) : 0;
                        $badges = [
                            ['text' => 'Más vendido',  'class' => 'bg-teal-800 text-white'],
                            ['text' => 'Recomendado', 'class' => 'bg-orange-500 text-white'],
                            ['text' => 'Popular',     'class' => 'bg-teal-200 text-teal-800'],
                        ];
                        $badge = $badges[$idx % 3];
                        $relHref = $relSlug !== '#' ? route('tours.show', ['locale' => $locale, 'slug' => $relSlug]) : '#';
                    @endphp
                    <a href="{{ $relHref }}" class="flex gap-3 group" aria-label="{{ $relTitle }}">
                        <div class="relative w-24 h-20 rounded-xl overflow-hidden shrink-0 bg-teal-800/10">
                            <img src="{{ $relCover }}"
                                 alt="{{ $relTitle }}"
                                 class="w-full h-full object-cover transition-transform group-hover:scale-105"
                                 loading="lazy" width="96" height="80">
                            <span class="absolute top-1.5 left-1.5 text-[9px] font-bold px-1.5 py-0.5 rounded-full {{ $badge['class'] }} flex items-center gap-0.5">
                                @if ($idx === 0)
                                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                @endif
                                {{ $badge['text'] }}
                            </span>
                            <button type="button"
                                    onclick="event.preventDefault()"
                                    class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-white/80 grid place-items-center"
                                    aria-label="Guardar {{ $relTitle }}">
                                <svg class="w-3.5 h-3.5 text-teal-800" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
                            </button>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold text-teal-800 leading-tight line-clamp-2 group-hover:text-teal-700 transition-colors">{{ $relTitle }}</p>
                            <p class="text-[11px] text-teal-800/55 mt-0.5">{{ $relDuration }}</p>
                            <div class="flex items-center gap-1 mt-0.5">
                                <svg class="w-3 h-3 text-orange-400 fill-current shrink-0" viewBox="0 0 20 20" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                <span class="text-[11px] font-semibold text-teal-800">{{ number_format((float)$relRating, 1) }}</span>
                                <span class="text-[10px] text-teal-800/45">({{ $relReviews }})</span>
                            </div>
                            {{-- Precio con/sin oferta usando nuevo sistema --}}
                            @if ($relHasOffer)
                                <p class="text-[10px] text-teal-800/50 line-through font-price mt-0.5">US${{ number_format((float)$relBefore, 0) }}</p>
                                <div class="flex items-baseline gap-1">
                                    <p class="font-price text-sm font-bold text-teal-800">US${{ number_format((float)$relPrice, 0) }}</p>
                                    <span class="text-[9px] font-bold text-orange-500 bg-orange-50 px-1 rounded">-{{ $relPct }}%</span>
                                </div>
                            @else
                                <p class="font-price text-sm font-bold text-teal-800 mt-1">US${{ number_format((float)$relPrice, 0) }}</p>
                            @endif
                            <p class="text-[10px] text-teal-800/45">por persona</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- ══════════════════════════════════════
             11. RESERVA CON CONFIANZA
        ══════════════════════════════════════ --}}
        <div class="bg-white rounded-2xl ring-1 ring-teal-800/10 shadow-sm p-5">
            <p class="text-sm font-bold text-teal-800 mb-4">Reserva con confianza</p>
            <div class="grid grid-cols-4 gap-2">
                @foreach ([
                    ['icon' => 'shield', 'label' => 'Cancelación gratuita', 'caption' => 'Hasta 24h antes'],
                    ['icon' => 'lock',   'label' => 'Pago seguro y protegido', 'caption' => ''],
                    ['icon' => 'phone',  'label' => 'Atención al cliente 24/7', 'caption' => ''],
                    ['icon' => 'medal',  'label' => 'Más de 10 años de experiencia', 'caption' => ''],
                ] as $trust)
                    <div class="flex flex-col items-center text-center gap-1.5">
                        <div class="w-10 h-10 rounded-full bg-orange-50 ring-1 ring-orange-200 grid place-items-center text-orange-500 shrink-0">
                            @if ($trust['icon'] === 'shield')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                            @elseif ($trust['icon'] === 'lock')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                            @elseif ($trust['icon'] === 'phone')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 3.75v4.5m0-4.5h-4.5m4.5 0l-6 6m3 12c-8.284 0-15-6.716-15-15V4.5A2.25 2.25 0 014.5 2.25h1.372c.516 0 .966.351 1.091.852l1.106 4.423c.11.44-.054.902-.417 1.173l-1.293.97a1.062 1.062 0 00-.38 1.21 12.035 12.035 0 007.143 7.143c.441.162.928-.004 1.21-.38l.97-1.293a1.125 1.125 0 011.173-.417l4.423 1.106c.5.125.852.575.852 1.091V19.5a2.25 2.25 0 01-2.25 2.25h-2.25z"/></svg>
                            @else
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0"/></svg>
                            @endif
                        </div>
                        <p class="text-[10px] font-semibold text-teal-800 leading-tight">{{ $trust['label'] }}</p>
                        @if ($trust['caption'])
                            <p class="text-[9px] text-teal-800/50 leading-tight">{{ $trust['caption'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

    </div>{{-- /max-w-3xl --}}
</section>

{{-- ─────────── 12. STICKY FOOTER CTA ─────────── --}}
<div class="fixed bottom-0 inset-x-0 z-40 bg-white shadow-2xl ring-1 ring-teal-800/10 sticky-bar">
    <div class="container mx-auto px-4 sm:px-5">
        <div class="flex items-center justify-between py-3 gap-4">
            {{-- Precio --}}
            <div class="shrink-0">
                @if ($hasOffer)
                    <p class="text-[9px] text-teal-800/50 line-through font-price leading-none">US${{ number_format((float)$tour->price_before, 0) }}</p>
                @else
                    <p class="text-[10px] uppercase tracking-wider text-teal-800/55 font-semibold leading-none">Desde</p>
                @endif
                <p class="font-price text-2xl font-bold text-teal-800 leading-tight">US${{ number_format((float)$tour->price, 0) }}</p>
                <p class="text-[10px] text-teal-800/45 leading-none">por persona</p>
            </div>
            {{-- Cancelación (sm+) --}}
            <div class="hidden sm:flex items-center gap-2 text-teal-800/70 flex-1 justify-center">
                <svg class="w-5 h-5 text-teal-700 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                <div>
                    <p class="text-xs font-semibold text-teal-800">Cancelación gratuita</p>
                    <p class="text-[11px] text-teal-800/50">Hasta 24h antes</p>
                </div>
            </div>
            {{-- CTA --}}
            <button id="sticky-reservar"
                    type="button"
                    onclick="window.location.href='{{ route('cart.store', ['locale' => $locale]) }}'"
                    class="shrink-0 inline-flex items-center gap-2 bg-teal-800 hover:bg-teal-700 text-white font-semibold text-sm rounded-full py-3 px-5 lg:px-7 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-orange-500">
                Reservar ahora
                <span class="w-7 h-7 rounded-full bg-orange-500 grid place-items-center shrink-0" aria-hidden="true">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                </span>
            </button>
        </div>
    </div>
</div>

@endsection
