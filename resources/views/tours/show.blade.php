@extends('layouts.app')

@php
    $locale     = app()->getLocale();
    // Per-tour promises controlled from the CMS (default true = legacy behaviour).
    $pickupIncluded   = (bool) ($tour->pickup_included ?? true);
    $freeCancellation = (bool) ($tour->free_cancellation ?? true);
    $gallery    = $tour->gallery ?? [];
    $itinerary  = $tour->localized('itinerary') ?? [];
    $includes   = $tour->localized('includes') ?? [];
    $excludes   = $tour->localized('excludes') ?? [];
    $recommendations = $tour->localized('recommendations');
    $notes      = $tour->localized('notes');
    $contactPhone = \App\Models\Setting::get('contact_phone') ?: '+51 925 886 725';

    // ── Zonas de recogida (pickup) — configuración GLOBAL, igual para todos los tours ──
    $pickupEnabled  = (bool) \App\Models\Setting::get('pickup_enabled', false);
    $rawPickupZones = \App\Models\Setting::get('pickup_zones');
    $pickupZonesRaw = is_string($rawPickupZones) ? (json_decode($rawPickupZones, true) ?? []) : (is_array($rawPickupZones) ? $rawPickupZones : []);
    $pickupZones = collect($pickupZonesRaw)
        ->filter(fn ($z) => is_array($z) && isset($z['lat'], $z['lng']) && is_numeric($z['lat']) && is_numeric($z['lng']))
        ->map(fn ($z) => [
            'label'     => (string) ($z['label'] ?? ''),
            'lat'       => (float) $z['lat'],
            'lng'       => (float) $z['lng'],
            'radius_km' => (float) ($z['radius_km'] ?? 2),
            'type'      => ($z['type'] ?? 'all') === 'hotels' ? 'hotels' : 'all',
        ])
        ->values();
    $pickupMapsKey = \App\Models\Setting::get('google_maps_api_key') ?: config('services.google.maps_api_key');
    $showPickup    = $pickupIncluded && $pickupEnabled && $pickupZones->isNotEmpty() && filled($pickupMapsKey);

    // FAQs del tour: idioma actual con fallback a español; solo pares completos.
    $tourFaqs = array_values(array_filter(
        (array) ($tour->localized('faqs') ?: []),
        fn ($f) => !empty($f['question']) && !empty($f['answer'])
    ));

    $recommendationLines = $recommendations
        ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $recommendations))))
        : [__('ui.rec_sunscreen'), __('ui.rec_sunglasses_hat'), __('ui.rec_comfortable_clothes'), __('ui.rec_camera'), __('ui.rec_water'), __('ui.rec_id_document')];

    $notesText = $notes ?: __('ui.default_notes_text');

    $galleryUrls = $tour->gallery_urls;
    $totalImgs   = count($galleryUrls);
    // Imagen principal (LCP): variantes WebP si existen (ImageVariants), si no el original. Misma URL/sizes en la copia
    // móvil y la de escritorio para que el navegador descargue UNA sola.
    $heroPath    = (is_array($gallery) && ! empty($gallery[0]) && is_string($gallery[0])) ? $gallery[0] : ($tour->cover_image ?? null);
    $heroSrcset  = \App\Support\ImageVariants::srcset($heroPath);
    $heroSrc     = $heroSrcset ? (\App\Support\ImageVariants::responsive($heroPath, 960) ?: ($galleryUrls[0] ?? $tour->cover_url)) : ($galleryUrls[0] ?? $tour->cover_url);
    $heroSizes   = '(min-width: 768px) 900px, 100vw';
    $comparison  = $tour->comparisonData($locale); // bloque "convencional VS premium" (null si desactivado)

    // Idioma del tour para el badge "Guía bilingüe": si el campo trae un valor
    // bilingüe por defecto (en cualquier idioma/variante) usa el string localizado
    // (__('ui.spanish_english')); si es un valor realmente personalizado, se respeta.
    $rawTourLang   = trim((string) ($tour->language ?? ''));
    // Known languages are re-labelled in the current locale (never mixes es/pt/en
    // in one label); a value with unknown languages is respected as written.
    $tourLanguage  = ($rawTourLang === '')
        ? __('ui.spanish_english')
        : (\App\Support\TourListing::languageLabel($rawTourLang) ?? $rawTourLang);
    // Única fuente de verdad: reseñas publicadas reales (Tour::reviewStats()).
    // Sin fallback fijo — si no hay reseñas, average/total llegan null/0 y el
    // badge no se pinta (ver guards @if($tourRating !== null) más abajo).
    $reviewStats  = $tour->reviewStats();
    // number_format fija un decimal (5.0, no 5) — mismo formato que
    // <x-review-summary> en la sección "Opiniones de nuestros viajeros".
    $tourRating   = $reviewStats['average'] !== null ? number_format($reviewStats['average'], 1) : null;
    $reviewsCount = $reviewStats['total'];
    $isMostBooked = ($tour->show_best_seller ?? true) && ($tour->is_most_booked ?? $tour->is_featured ?? false);
    // Prueba social REAL (reservas confirmadas/pagadas, 30 días, cache 1 h). null = no se muestra.
    $socialProof = \App\Support\SocialProof::displayable($tour);

    // ── Variante oferta ──
    $hasOffer = $tour->price_before && (float)$tour->price_before > (float)$tour->price;
    $savings  = $hasOffer ? (float)$tour->price_before - (float)$tour->price : 0;
    $discountPct = $hasOffer ? (int) round($savings / (float)$tour->price_before * 100) : 0;

    // Si no hay itinerario en DB, se deja vacío (la sección se oculta)
    if (empty($itinerary)) {
        $itinerary = [];
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

            // Normalizar hora a formato 24h sin am/pm (ej. "1:00 pm" → "13:00", "4:00 am" → "04:00")
            // Cada hora del texto se normaliza por separado: un rango "4:00 AM - 11:30 AM" debe
            // quedar "04:00 - 11:30", no colapsar a la hora de inicio (dos pasos a las 04:00).
            if (!empty($time) && preg_match('/^\d{1,2}:\d{2}/u', $time)) {
                $time = preg_replace_callback('/(\d{1,2}):(\d{2})\s*(am|pm)?/iu', function ($tm) {
                    $h = (int) $tm[1];
                    $mer = mb_strtolower($tm[3] ?? '');
                    if ($mer === 'pm' && $h < 12) { $h += 12; }
                    if ($mer === 'am' && $h === 12) { $h = 0; }
                    return sprintf('%02d:%02d', $h, $tm[2]);
                }, $time);
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

    // ═══════ Ficha rediseñada (24-nov-2026): variables de la plantilla única ═══════
    // Tours relacionados REALES (el controlador ya prioriza misma región y completa con el resto). Sin fallback inventado.
    $relatedTours = collect($related ?? [])->where('id', '!=', $tour->id)->take(3)->values();

    $decimalComma = in_array($locale, ['es', 'pt'], true);
    $fmtRating    = fn ($n) => number_format((float) $n, 1, $decimalComma ? ',' : '.', '');
    $money        = fn ($n) => 'US$' . number_format((float) $n, ((float) $n == floor((float) $n)) ? 0 : 2, '.', ',');
    $ratingText   = $reviewStats['average'] !== null ? $fmtRating($reviewStats['average']) : null;
    $starsFilled  = $reviewStats['average'] !== null ? max(0, min(5, (int) round($reviewStats['average']))) : 0;
    $starsGlyph   = fn (int $n) => str_repeat('★', $n) . str_repeat('☆', 5 - $n);
    $reviewsLabel = $reviewsCount === 1 ? __('ficha.review_one') : __('ficha.review_other', ['count' => $reviewsCount]);

    // Listas del tour tal cual vienen del admin (string o ['label' => …]).
    $cleanList = fn ($list) => array_values(array_filter(array_map(
        fn ($i) => trim((string) (is_array($i) ? ($i['label'] ?? $i[0] ?? '') : $i)),
        is_array($list) ? $list : []
    ), fn ($t) => $t !== ''));
    $includesList = $cleanList($includes);
    $excludesList = $cleanList($excludes);
    $hasIncludes  = $includesList !== [] || $excludesList !== [];
    $recsReal     = $recommendations ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $recommendations)))) : [];
    $notesReal    = trim((string) $notes);

    // Resumen "¿Por qué reservar?": subtítulo del tour; si no hay, el arranque de la descripción. La descripción completa
    // queda en el HTML dentro de un <details> (no se pierde texto indexable respecto a la ficha anterior).
    $subtitleText = trim((string) $tour->localized('subtitle'));
    $descPlain    = trim(strip_tags((string) $tour->description));
    $summaryLead  = $subtitleText !== '' ? $subtitleText : \Illuminate\Support\Str::limit($descPlain, 260);
    $summaryFull  = ($descPlain !== '' && $descPlain !== $summaryLead) ? $descPlain : '';

    // Datos rápidos (chips): duración, recojo, idioma y los chips destacados del admin (card_chips con acento).
    $facts = [];
    $durationLabel = \App\Support\TourListing::durationLabel($tour->duration);
    if ($durationLabel) { $facts[] = ['icon' => '◷', 'text' => $durationLabel, 'href' => null]; }
    if ($pickupIncluded) { $facts[] = ['icon' => '', 'text' => __('ficha.fact_pickup'), 'href' => $hasIncludes ? '#incluye' : null]; }
    if (trim($tourLanguage) !== '') { $facts[] = ['icon' => '', 'text' => $tourLanguage, 'href' => null]; }
    $seenFacts = array_map(fn ($f) => mb_strtolower($f['text']), $facts);
    foreach ($tour->cardChips() as $chip) {
        if (! empty($chip['accent']) && ! in_array(mb_strtolower($chip['text']), $seenFacts, true)) {
            $facts[] = ['icon' => '', 'text' => $chip['text'], 'href' => null];
            $seenFacts[] = mb_strtolower($chip['text']);
        }
    }

    // Galería: principal + 2 miniaturas (como la maqueta); el visor abre todas.
    $photoAlt = function (int $i) use ($tour) {
        return $i === 0 ? $tour->title : ($tour->title . ' – ' . __('ficha.photo_n', ['n' => $i + 1]));
    };
    $galleryAlts = [];
    foreach ($galleryUrls as $gi => $gu) { $galleryAlts[] = $photoAlt($gi); }
    $minis = [];
    foreach ([1, 2] as $mi) {
        if (isset($galleryUrls[$mi])) {
            $raw  = (is_array($gallery) && isset($gallery[$mi]) && is_string($gallery[$mi])) ? $gallery[$mi] : null;
            $card = $raw ? \App\Support\ImageVariants::card($raw, '(min-width: 841px) 22vw, 30vw') : ['src' => null, 'srcset' => null, 'sizes' => null];
            $minis[$mi] = ['src' => $card['src'] ?: $galleryUrls[$mi], 'srcset' => $card['srcset'], 'sizes' => $card['sizes']];
        }
    }
    $galleryClass = count($minis) === 0 ? 'fi-gallery--solo' : (count($minis) === 1 ? 'fi-gallery--duo' : '');

    // Aviso de fecha/horario coherente con 1 o varios horarios y con mismo día. Todo viene del servidor (hora de Lima).
    $av          = $bookingAvailability ?? [];
    $slotsAll    = array_values($av['slots'] ?? []);
    $slotLabels  = $av['labels'] ?? [];
    $slotPart    = function (string $t) {
        $h = (int) substr($t, 0, 2);
        return $h < 12 ? __('ficha.slot_morning') : ($h < 18 ? __('ficha.slot_afternoon') : __('ficha.slot_evening'));
    };
    $noticeParts = [];
    if (count($slotsAll) <= 1) {
        $only = $slotsAll[0] ?? $bookingDepartureTime;
        $noticeParts[] = __('ficha.notice_single_slot', ['time' => \App\Support\BookingCalendar::timeLabel($only)]);
    }
    $noticeParts[] = __('ficha.notice_cutoff', ['cutoff' => \App\Support\BookingCalendar::nextDayCutoffLabel()]);
    if (! empty($av['same_day'])) {
        $noticeParts[] = __('ficha.notice_same_day', ['hours' => $av['min_hours'] ?? 4]);
    }
    $bookingNotice = implode(' ', $noticeParts);
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
{{-- Meta título/descripción: panel Filament (SEO por idioma) con fallback al
     título/descripción autogenerados como antes. Nunca queda vacío. --}}
@section('title', $tour->metaTitle ?: ($titleDisplay . ' — ' . __('seo.site_name')))
@section('description', $tour->metaDescription ?: (__('seo.tour_description_prefix') . $titleDisplay . __('seo.tour_description_suffix')))
{{-- OJO: el valor NO puede ser null (ver misma nota en blog/show.blade.php).
     Cadena vacía = el layout cae a la imagen og por defecto global. --}}
@section('og_image', $tour->seo_image_url ?: '')

@php
    // hreflang/canonical reales: el slug puede diferir por idioma (slug_en/slug_pt).
    // Sin esto, layouts/app.blade.php asumiría el mismo slug en los 3 idiomas y
    // apuntaría a URLs inexistentes en cuanto se cargue una traducción.
    $localizedAlternates = [
        'es' => url('/es/tours/detalle/' . $tour->slugFor('es')),
        'en' => url('/en/tours/detalle/' . $tour->slugFor('en')),
        'pt' => url('/pt/tours/detalle/' . $tour->slugFor('pt')),
    ];

    // PT with no description/itinerary of its own is a copy of the Spanish body under PT
    // labels: keep it out of the index and the hreflang cluster until it gets translated
    // (switches itself off). EN is intentionally NOT covered, see Tour::isLocalePageIndexable().
    $localizedAlternates = array_filter(
        $localizedAlternates,
        fn ($href, $alt) => $tour->isLocalePageIndexable($alt),
        ARRAY_FILTER_USE_BOTH
    );
    $forceNoindex = ! $tour->isLocalePageIndexable($locale);
@endphp

@push('schema')
@php
    // Regla de convivencia (panel Filament → Tours → SEO — [idioma] → Datos
    // estructurados): si el editor cargó JSON-LD manual para este idioma (o
    // el de español como fallback), se imprime tal cual. El JSON ya se
    // valida como parseable al guardar en el form, así que no hace falta
    // re-validar aquí.
    //
    // Lote B (2026-08-31): se apagó el Product+TouristTrip automático que
    // antes se pintaba cuando este campo estaba vacío, y el FAQPage
    // automático que se generaba siempre a partir de $tourFaqs (repeater de
    // FAQs del tour). $tourFaqs sigue alimentando el acordeón visible más
    // abajo — solo se apagó su copia en JSON-LD. Con el campo vacío, la
    // ficha del tour ya no emite ningún JSON-LD.
    $customSchema = \App\Support\TourJsonLd::for($tour, $locale);
@endphp
@if ($customSchema)
<x-schema-raw :json="$customSchema" />
@endif
@endpush

@push('head')
{{-- LCP: la imagen principal de la ficha se precarga con prioridad alta (mismos srcset/sizes que el <img>). --}}
<link rel="preload" as="image" href="{{ $heroSrc }}" @if ($heroSrcset) imagesrcset="{{ $heroSrcset }}" imagesizes="{{ $heroSizes }}" @endif fetchpriority="high">
@endpush

@push('head')
{{-- Ficha rediseñada: CSS plano propio (no depende del build de Vite). Subir ?v= en cada cambio. --}}
<link rel="stylesheet" href="{{ asset('css/ficha.css') }}?v=20261124a">
<style>
/* ── Flatpickr brand overrides ── */
.flatpickr-day.selected,
.flatpickr-day.selected:hover {
    background: #15474B !important;
    border-color: #15474B !important;
}
.flatpickr-day:hover,
.flatpickr-day.prevMonthDay:hover,
.flatpickr-day.nextMonthDay:hover {
    background: #15474b22 !important;
}
.lvt-fp-foot { padding: 8px 12px 10px; border-top: 1px solid #e6e6e6; text-align: left; }
.lvt-fp-foot p { margin: 0; font-size: 11.5px; line-height: 1.35; }
.lvt-fp-foot__notice { color: #465e68; }
.lvt-fp-foot__warn { color: #9a3412; font-weight: 600; margin-bottom: 6px !important; }
.lvt-fp-foot__warn[hidden] { display: none; }
.flatpickr-calendar { z-index: 1000; }
.flatpickr-day.flatpickr-disabled,
.flatpickr-day.flatpickr-disabled:hover {
    color: #b0b0b0 !important;
    text-decoration: line-through !important;
    cursor: not-allowed !important;
}
</style>
@endpush
@push('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
@endpush

@push('scripts')
{{-- Blocked-dates data injected from controller --}}
<script>
    window.LVT_BLOCKED_DATES    = @json($blockedDates ?? []);
    window.LVT_BLOCKED_WEEKDAYS = @json($blockedWeekdays ?? []);
    // Primer día reservable, calculado en el reloj de LIMA por el servidor.
    // Antes flatpickr hacía `new Date()` + 1 en el navegador: un visitante en
    // Madrid y uno en Lima veían primeros días distintos para el mismo tour, y
    // ninguno de los dos coincidía con lo que validaba el servidor (que corre
    // en UTC). Ahora los tres miran el mismo calendario.
    window.LVT_MIN_DATE = @json($bookingMinDate);
    // Horarios por fecha, mismo día y margen: calculados en servidor (hora de Lima).
    window.LVT_BOOKING = @json($bookingAvailability ?? []);
</script>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('booking', {
            adults: 1,
            children: 0,
            // Vacía a propósito (2026-08-27). Venía pre-rellenada con hoy+7, y
            // como el campo se ve "ya elegido", mucha gente reservaba sin abrir
            // el calendario: se llevaban una fecha que nadie escogió y que
            // además NO se podía cambiar en el carrito. Un cliente que quería
            // reservar para mañana terminaba con el 4 de septiembre.
            date: '',
            // Horario elegido ("HH:MM"); vacío si el tour tiene uno solo o ninguno propio.
            slot: '',
            cfg: window.LVT_BOOKING || {},
            // Horarios de la fecha elegida: HOY solo los que cumplen el margen (cfg.today_slots).
            slotList() {
                var c = this.cfg;
                if (!c.slots || !c.slots.length) return [];
                return (this.date && this.date === c.today) ? (c.today_slots || []) : c.slots;
            },
            // Todos los horarios del tour (para pintar los botones) y cuáles se pueden elegir la fecha actual.
            slotsAll() { return (this.cfg.slots || []); },
            slotEnabled(s) { return this.slotList().indexOf(s) !== -1; },
            label(s) { return (this.cfg.labels || {})[s] || s; },
            ensureSlot() {
                var l = this.slotList();
                if (l.indexOf(this.slot) === -1) this.slot = (l.length === 1) ? l[0] : '';
            },
            sameDayNow() { return !!this.cfg.same_day && !!this.date && this.date === this.cfg.today; },
        });
    });
</script>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js" crossorigin="anonymous" referrerpolicy="no-referrer" defer></script>
@php
    $fpLocale = match(app()->getLocale()) { 'pt' => 'pt', 'en' => 'en', default => 'es' };
@endphp
@if($fpLocale !== 'en')
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/l10n/{{ $fpLocale }}.min.js" crossorigin="anonymous" referrerpolicy="no-referrer" defer></script>
@endif
<script>
(function () {
    function initFlatpickr() {
        if (typeof flatpickr === 'undefined') {
            // Retry until the deferred script has loaded
            setTimeout(initFlatpickr, 80);
            return;
        }

        var MSG = {
            notice:  @json($bookingNotice),
            early:   @json(__('ui.date_unavailable_early')),
            blocked: @json(__('ui.date_unavailable_blocked')),
            first:   @json(__('ui.date_first_available'))
        };

        var blockedDates    = window.LVT_BLOCKED_DATES    || [];
        var blockedWeekdays = window.LVT_BLOCKED_WEEKDAYS || [];

        // 'YYYY-MM-DD' del servidor (hora de Lima). Se pasa como string y no
        // como Date para que el navegador no lo reinterprete en su propio huso
        // y termine adelantando o atrasando un día el primer hueco libre.
        var minDate = window.LVT_MIN_DATE || null;

        @if($fpLocale !== 'en')
        var locale = flatpickr.l10ns['{{ $fpLocale }}'] || flatpickr.l10ns.default;
        @else
        var locale = flatpickr.l10ns.default;
        @endif

        // Escribe la fecha elegida en TODOS los campos ocultos travel_date de
        // forma directa (sin depender del ciclo reactivo de Alpine, que puede
        // no haber refrescado el :value del <input hidden> en el momento del
        // submit). Esto blinda la fecha que realmente viaja al servidor.
        function syncTravelDate(dateStr) {
            if (!dateStr) return;
            if (typeof Alpine !== 'undefined' && Alpine.store('booking')) {
                Alpine.store('booking').date = dateStr;
            }
            document.querySelectorAll('input[name="travel_date"]').forEach(function (h) {
                h.value = dateStr;
            });
        }

        var config = {
            minDate:    minDate,
            dateFormat: 'Y-m-d',
            locale:     locale,
            // Forzar el calendario propio de flatpickr también en móvil. El
            // input nativo de iOS/Android (mobileInput) desincronizaba la fecha
            // seleccionada con el campo enviado; el calendario propio usa la
            // misma ruta verificada del escritorio.
            disableMobile: true,
            disable: [
                // Specific blocked dates
                ...blockedDates,
                // Recurring weekdays
                function (date) {
                    return blockedWeekdays.includes(date.getDay());
                }
            ],
            onChange: function (selectedDates, dateStr) {
                syncTravelDate(dateStr);
            },
            onReady: function (selectedDates, dateStr, fp) {
                buildCalendarFooter(fp);
            }
        };

        function isBlocked(date) {
            var y = date.getFullYear(), m = String(date.getMonth() + 1).padStart(2, '0'), d = String(date.getDate()).padStart(2, '0');
            return blockedDates.includes(y + '-' + m + '-' + d) || blockedWeekdays.includes(date.getDay());
        }

        function parseYmd(str) {
            var p = String(str).split('-');
            return new Date(+p[0], +p[1] - 1, +p[2]);
        }

        function firstAvailable() {
            var d = minDate ? parseYmd(minDate) : new Date();
            for (var i = 0; i < 400 && isBlocked(d); i++) d.setDate(d.getDate() + 1);
            return d;
        }

        // Pie dentro del calendario: el aviso de salida/anticipación queda
        // visible con el calendario abierto (antes lo tapaba) y aquí mismo se
        // explica por qué un día tachado no se puede elegir.
        function buildCalendarFooter(fp) {
            var foot = document.createElement('div');
            foot.className = 'lvt-fp-foot';
            var notice = document.createElement('p');
            notice.className = 'lvt-fp-foot__notice';
            notice.textContent = MSG.notice;
            var warn = document.createElement('p');
            warn.className = 'lvt-fp-foot__warn';
            warn.setAttribute('role', 'status');
            warn.setAttribute('aria-live', 'polite');
            warn.hidden = true;
            foot.appendChild(warn);
            foot.appendChild(notice);
            fp.calendarContainer.appendChild(foot);

            fp.calendarContainer.addEventListener('click', function (e) {
                var day = e.target.closest ? e.target.closest('.flatpickr-day.flatpickr-disabled') : null;
                if (!day || !day.dateObj) {
                    warn.hidden = true;
                    return;
                }
                var first = flatpickr.formatDate(firstAvailable(), 'l j F', locale);
                var early = minDate && day.dateObj < parseYmd(minDate);
                warn.textContent = (early ? MSG.early : MSG.blocked) + ' ' + MSG.first.replace(':date', first);
                warn.hidden = false;
            }, true);
        }

        // Initialize on both booking date inputs (mobile + desktop)
        document.querySelectorAll('[data-booking-date]').forEach(function (el) {
            var initialDate = (typeof Alpine !== 'undefined')
                ? Alpine.store('booking').date
                : el.value;

            // Sin fecha elegida NO se pone defaultDate. Con `|| tomorrow` el
            // campo volvía a quedar relleno solo, y con hoy+7 el calendario
            // abría directamente en el mes siguiente: quien quería reservar
            // para mañana tenía que retroceder un mes para encontrarlo.
            if (initialDate) {
                config.defaultDate = initialDate;
            } else {
                delete config.defaultDate;
            }

            flatpickr(el, config);
        });
    }
    // Envío del formulario de reserva (botón "Añadir al carrito"):
    //  1) sin fecha  -> no se envía; se lleva al calendario con un aviso.
    //  2) sin horario (el tour tiene varios) -> no se envía; aviso junto a los horarios.
    //  3) todo listo -> POST por fetch (JSON) y se abre el cajón del carrito sin salir de la ficha.
    //     Si el fetch falla por red/servidor, cae al envío normal del formulario (va a /carrito).
    function lvtMsg(box, flag, text, afterEl) {
        if (!box) return;
        var old = box.querySelector('[' + flag + ']');
        if (old) { old.textContent = text; return; }
        var msg = document.createElement('p');
        msg.setAttribute(flag, '');
        msg.setAttribute('role', 'alert');
        msg.className = 'fi-error';
        msg.textContent = text;
        if (afterEl && afterEl.parentNode === box) {
            afterEl.insertAdjacentElement('afterend', msg);
        } else {
            box.appendChild(msg);
        }
    }

    function lvtCsrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function guardEmptyDate() {
        var form = document.getElementById('form-reservar');
        if (!form) return;
        var I18N = window.LVT_FICHA || {};
        var btn = document.getElementById('fi-add');
        var errBox = document.getElementById('fi-book-error');

        function showBookError(text) {
            if (!errBox) return;
            errBox.textContent = text;
            errBox.hidden = !text;
        }

        form.addEventListener('submit', function (e) {
            var store = (typeof Alpine !== 'undefined' && Alpine.store) ? Alpine.store('booking') : null;
            var chosen = store ? store.date : '';

            // 1) fecha
            if (!chosen) {
                e.preventDefault();
                var visible = [...document.querySelectorAll('[data-booking-date]')]
                    .find(function (el) { return el.offsetParent !== null; });
                if (!visible) return;
                visible.scrollIntoView({ block: 'center', behavior: 'smooth' });
                var box = visible.closest('[data-date-field]') || visible.parentElement;
                lvtMsg(box, 'data-date-required', @json(__('cart.validation.date_required')), box.querySelector('label'));
                if (visible._flatpickr) { visible._flatpickr.open(); }
                return;
            }

            // 2) horario
            var needSlot = store && store.slotsAll().length > 1 && !store.slot;
            if (needSlot) {
                e.preventDefault();
                var picker = document.querySelector('[data-slot-picker]');
                if (picker) {
                    picker.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    lvtMsg(picker, 'data-slot-required', I18N.slot_required || '', picker.querySelector('[role="group"]'));
                    var first = picker.querySelector('button:not(:disabled)');
                    if (first) { first.focus({ preventScroll: true }); }
                }
                return;
            }

            // 3) enviar por fetch (sin recargar); el servidor valida igual que siempre.
            if (!window.fetch || !window.FormData) return; // envío normal
            e.preventDefault();
            showBookError('');
            var label = btn ? btn.querySelector('.fi-cta__label') : null;
            var labelText = label ? label.textContent : '';
            if (btn) { btn.disabled = true; if (label) { label.textContent = I18N.adding || labelText; } }

            fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': lvtCsrf() },
                body: new FormData(form),
                credentials: 'same-origin'
            }).then(function (res) {
                return res.json().catch(function () { return null; }).then(function (data) { return { res: res, data: data }; });
            }).then(function (r) {
                if (btn) { btn.disabled = false; if (label) { label.textContent = labelText; } }
                if (r.res.ok && r.data && r.data.success) {
                    if (typeof window.lvtTrack === 'function' && window.__lvtTourItem) {
                        var qty = store ? (parseInt(store.adults, 10) || 1) + (parseInt(store.children, 10) || 0) : 1;
                        window.lvtTrack('add_to_cart', {
                            currency: 'USD',
                            value: window.__lvtTourItem.price * qty,
                            items: [Object.assign({}, window.__lvtTourItem, { quantity: qty })]
                        }, { fb: 'AddToCart' });
                    }
                    window.dispatchEvent(new CustomEvent('lvt-cart-open', { detail: r.data.cart }));
                    var counter = document.querySelector('.site-header__badge');
                    if (counter && r.data.cart) { counter.textContent = r.data.cart.count; }
                    return;
                }
                if (r.res.status === 422) {
                    var msgs = [];
                    if (r.data && r.data.errors) { Object.keys(r.data.errors).forEach(function (k) { msgs = msgs.concat(r.data.errors[k]); }); }
                    else if (r.data && r.data.message) { msgs.push(r.data.message); }
                    showBookError(msgs.join(' ') || I18N.add_error);
                    return;
                }
                form.submit(); // fallo inesperado: envío clásico
            }).catch(function () {
                if (btn) { btn.disabled = false; if (label) { label.textContent = labelText; } }
                form.submit();
            });
        });

        // Al elegir fecha u horario se limpian los avisos.
        document.addEventListener('change', function (e) {
            if (e.target.hasAttribute && e.target.hasAttribute('data-booking-date')) {
                document.querySelectorAll('[data-date-required]').forEach(function (n) { n.remove(); });
                showBookError('');
            }
        });
        document.addEventListener('click', function (e) {
            if (e.target.closest && e.target.closest('[data-slot-picker] button')) {
                document.querySelectorAll('[data-slot-required]').forEach(function (n) { n.remove(); });
            }
        });
    }

    // El servidor rechazó la fecha (redirect back): llevar la vista al aviso del
    // calendario visible. Salto instantáneo.
    function revealServerDateError() {
        var err = [...document.querySelectorAll('[data-date-server-error]')]
            .find(function (n) { return n.offsetParent !== null; });
        if (!err) return;
        err.style.scrollMarginTop = '80px';
        err.scrollIntoView({ block: 'center', behavior: 'instant' });
    }

    // Wait for DOM + Alpine to be ready
    document.addEventListener('DOMContentLoaded', function () {
        guardEmptyDate();
        revealServerDateError();

        if (typeof Alpine !== 'undefined') {
            initFlatpickr();
        } else {
            document.addEventListener('alpine:init', initFlatpickr);
        }
    });
}());
</script>
@endpush

@if ($showPickup)
@push('scripts')
{{-- Zonas de recogida — datos inyectados desde el servidor, sin exponer más que lat/lng/radio/tipo --}}
<script>
    window.LVT_PICKUP_ZONES = @json($pickupZones);
</script>
<script>
(function () {
    // Dibuja los círculos de cobertura en el mapa de la ficha (#fi-pickup-map).
    function renderPickupMaps() {
        var zones = window.LVT_PICKUP_ZONES || [];
        var ids = ['fi-pickup-map'];

        ids.forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;

            var map = new google.maps.Map(el, { center: { lat: -12.09, lng: -77.03 }, zoom: 12 });
            var bounds = new google.maps.LatLngBounds();
            var any = false;

            zones.forEach(function (zone) {
                var center = { lat: zone.lat, lng: zone.lng };
                any = true;
                bounds.extend(center);

                new google.maps.Marker({ position: center, map: map, title: zone.label || '' });
                new google.maps.Circle({
                    map: map,
                    center: center,
                    radius: (zone.radius_km || 2) * 1000,
                    strokeColor: '#d96b33',
                    strokeOpacity: 0.8,
                    strokeWeight: 1.5,
                    fillColor: '#d96b33',
                    fillOpacity: 0.12,
                });
            });

            if (any) map.fitBounds(bounds);
        });
    }

    window.__lvtPickupMapsReady = renderPickupMaps;

    if (window.google && window.google.maps) {
        renderPickupMaps();
        return;
    }

    if (!document.getElementById('lvt-gmaps-sdk')) {
        var s = document.createElement('script');
        s.id = 'lvt-gmaps-sdk';
        s.src = 'https://maps.googleapis.com/maps/api/js?key={{ $pickupMapsKey }}&loading=async&callback=__lvtPickupMapsReady';
        s.async = true;
        s.defer = true;
        document.head.appendChild(s);
    } else {
        document.getElementById('lvt-gmaps-sdk').addEventListener('load', renderPickupMaps);
    }
}());
</script>
@endpush
@endif

@push('scripts')
{{-- Textos para JS, cajón del carrito y eventos de analítica (view_item; add_to_cart se dispara al añadir por fetch). --}}
<script>
    window.LVT_FICHA = {
        one: @json(__('ficha.traveler_one')),
        other: @json(__('ficha.traveler_other')),
        slot_required: @json(__('ficha.slot_required')),
        add_error: @json(__('ficha.add_error')),
        adding: @json(__('ficha.adding'))
    };
    window.__lvtTourItem = {
        item_id: @json((string) $tour->id),
        item_name: @json($titleDisplay),
        price: {{ (float) $tour->price }},
        quantity: 1
    };

    document.addEventListener('alpine:init', function () {
        Alpine.data('lvtCartDrawer', function () {
            var URLS = {
                summary: @json(route('cart.summary', ['locale' => $locale])),
                update: @json(route('cart.update', ['locale' => $locale, 'rowId' => '__ROW__'])),
                clear: @json(route('cart.clear', ['locale' => $locale])),
                cart: @json(route('cart.index', ['locale' => $locale]))
            };
            var TXT = {
                travelers: @json(__('ficha.cart_travelers')),
                no_date: @json(__('ficha.cart_no_date')),
                change_slot: @json(__('ficha.cart_slot_change')),
                error: @json(__('ficha.cart_error'))
            };
            function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.getAttribute('content') : ''; }

            return {
                open: false,
                err: '',
                opener: null,
                cart: { items: [], total: 0, count: 0, cart_url: URLS.cart },
                i18n: TXT,
                init: function () {
                    var self = this;
                    // El botón del carrito del encabezado abre este cajón (sigue siendo un enlace a /carrito sin JS).
                    var hb = document.querySelector('.site-header__cart-btn');
                    if (hb) {
                        hb.addEventListener('click', function (e) {
                            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
                            e.preventDefault();
                            self.opener = hb;
                            self.load();
                        });
                    }
                    // Trampa de foco mientras está abierto.
                    document.addEventListener('keydown', function (e) {
                        if (!self.open || e.key !== 'Tab') return;
                        var f = self.$refs.panel.querySelectorAll('button:not(:disabled), a[href], select');
                        if (!f.length) return;
                        var first = f[0], last = f[f.length - 1];
                        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
                        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
                    });
                },
                money: function (n) { return 'US$' + (Number(n) || 0).toFixed(2); },
                show: function (cart) {
                    if (cart) this.cart = cart;
                    if (!this.opener) this.opener = document.activeElement;
                    this.err = '';
                    this.open = true; document.documentElement.classList.add('fi-modal');
                    document.body.style.overflow = 'hidden';
                    var self = this;
                    this.$nextTick(function () { if (self.$refs.closeBtn) self.$refs.closeBtn.focus(); });
                },
                close: function () {
                    this.open = false; document.documentElement.classList.remove('fi-modal');
                    document.body.style.overflow = '';
                    if (this.opener && this.opener.focus) { this.opener.focus(); }
                    this.opener = null;
                },
                load: function () {
                    var self = this;
                    fetch(URLS.summary, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (d) { self.show(d.cart); })
                        .catch(function () { window.location.href = URLS.cart; });
                },
                send: function (method, url, body) {
                    var self = this;
                    self.err = '';
                    var headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' };
                    if (body) headers['Content-Type'] = 'application/json';
                    return fetch(url, { method: method, headers: headers, body: body ? JSON.stringify(body) : undefined, credentials: 'same-origin' })
                        .then(function (r) { return r.json().catch(function () { return null; }).then(function (d) { return { ok: r.ok, data: d }; }); })
                        .then(function (r) {
                            if (!r.ok || !r.data || r.data.success === false) {
                                var m = r.data && r.data.errors ? [].concat.apply([], Object.keys(r.data.errors).map(function (k) { return r.data.errors[k]; })).join(' ') : (r.data && r.data.message);
                                self.err = m || TXT.error;
                                return null;
                            }
                            return r.data;
                        })
                        .catch(function () { self.err = TXT.error; return null; });
                },
                changeSlot: function (row, value) {
                    var self = this;
                    this.send('PATCH', URLS.update.replace('__ROW__', encodeURIComponent(row.row_id)), {
                        adults: row.adults, children: row.children, departure_slot: value
                    }).then(function (d) {
                        if (d && d.cart) { self.cart = d.cart; }
                        else { self.cart = Object.assign({}, self.cart, { items: self.cart.items.slice() }); } // revierte el <select> al valor real
                    });
                },
                clearCart: function () {
                    var self = this;
                    this.send('DELETE', URLS.clear).then(function (d) {
                        if (d) {
                            self.cart = { items: [], total: 0, count: 0, cart_url: URLS.cart };
                            var counter = document.querySelector('.site-header__badge');
                            if (counter) counter.textContent = '0';
                        }
                    });
                }
            };
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.lvtTrack === 'function') {
            window.lvtTrack('view_item', {
                currency: 'USD',
                value: window.__lvtTourItem.price,
                items: [window.__lvtTourItem]
            }, { fb: 'ViewContent' });
        }
    });
</script>
@endpush

@section('content')
<script>
    window.__lvtGallery = {!! json_encode($galleryUrls, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!};
    window.__lvtAlts    = {!! json_encode($galleryAlts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!};
    window.__lvtPrice   = {{ (float) $tour->price }};
</script>

<div class="fi-page"
     x-data="{
         gallery: window.__lvtGallery || [],
         alts: window.__lvtAlts || [],
         active: 0,
         lb: false,
         opener: null,
         openLb(i, el) { if (i !== undefined) this.active = i; this.opener = el || null; this.lb = true; document.body.style.overflow = 'hidden'; document.documentElement.classList.add('fi-modal'); this.$nextTick(() => this.$refs.lbClose && this.$refs.lbClose.focus()); },
         closeLb() { this.lb = false; document.body.style.overflow = ''; document.documentElement.classList.remove('fi-modal'); if (this.opener) { this.opener.focus(); } },
         prev() { this.active = (this.active - 1 + this.gallery.length) % this.gallery.length; },
         next() { this.active = (this.active + 1) % this.gallery.length; }
     }"
     @keydown.escape.window="lb && closeLb()"
     @keydown.arrow-left.window="lb && prev()"
     @keydown.arrow-right.window="lb && next()">
<div class="fi">

    {{-- Migas --}}
    <nav class="fi-crumb" aria-label="Breadcrumb">
        <a href="{{ route('home', ['locale' => $locale]) }}">{{ __('ui.home') }}</a>
        <span aria-hidden="true">›</span>
        <a href="{{ route('tours.index', ['locale' => $locale]) }}">Tours</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page">{{ \Illuminate\Support\Str::limit($titleDisplay, 55) }}</span>
    </nav>

    {{-- Título + rating --}}
    <div class="fi-top">
        <h1 class="fi-title">{{ $titleDisplay }}</h1>
        @if ($tourRating !== null)
        <a class="fi-rating" href="#resenas">
            <span class="fi-stars" role="img" aria-label="{{ __('ficha.rating_stars', ['rating' => $ratingText]) }}">{{ $starsGlyph($starsFilled) }}</span>
            <b>{{ $ratingText }}</b>
            <span class="fi-rating__count">{{ $reviewsLabel }}</span>
        </a>
        @endif
    </div>

    <div class="fi-hero">
        <div class="fi-visual">

            {{-- Galería --}}
            <div class="fi-gallery {{ $galleryClass }}" role="group" aria-label="{{ __('ficha.photos_aria') }}">
                <button type="button" class="fi-photo" @click="openLb(active, $el)" aria-label="{{ __('ficha.open_photo') }}">
                    {{-- Imagen principal = candidata LCP (preload en <head>, mismas srcset/sizes). --}}
                    <img src="{{ $heroSrc }}"
                         @if ($heroSrcset) srcset="{{ $heroSrcset }}" @endif
                         sizes="{{ $heroSizes }}"
                         :src="active === 0 ? @js($heroSrc) : gallery[active]"
                         :srcset="active === 0 ? @js((string) $heroSrcset) : ''"
                         :alt="alts[active] || @js($tour->title)"
                         alt="{{ $tour->title }}"
                         width="900" height="600"
                         loading="eager" fetchpriority="high" decoding="async"
                         onerror="this.onerror=null;this.removeAttribute('srcset');this.src='{{ asset('assets/banners/banner-hero.jpg') }}'">
                    @if ($totalImgs > 1)
                    <span class="fi-photo__count" aria-hidden="true"><span x-text="active + 1">1</span> / {{ $totalImgs }}</span>
                    @endif
                    <span class="fi-photo__zoom">{{ __('ficha.view_photos') }} ⤢</span>
                </button>
                @foreach ($minis as $mi => $m)
                <button type="button" class="fi-mini" :class="{ 'is-active': active === {{ $mi }} }" @click="active = {{ $mi }}" aria-label="{{ __('ficha.view_photo_n', ['n' => $mi + 1]) }}">
                    <img src="{{ $m['src'] }}" @if ($m['srcset']) srcset="{{ $m['srcset'] }}" sizes="{{ $m['sizes'] }}" @endif alt="{{ $photoAlt($mi) }}" width="320" height="300" loading="lazy" decoding="async">
                </button>
                @endforeach
            </div>

            {{-- ¿Por qué reservar este tour? --}}
            @if ($summaryLead !== '' || count($facts) > 0)
            <section class="fi-summary" aria-labelledby="fi-why">
                <h2 id="fi-why">{{ __('ficha.why_title') }}</h2>
                @if ($summaryLead !== '')<p class="fi-summary__lead">{{ $summaryLead }}</p>@endif
                @if ($summaryFull !== '')
                <details class="fi-summary__more">
                    <summary>{{ __('ficha.read_full') }}</summary>
                    <div>{!! nl2br(e($summaryFull)) !!}</div>
                </details>
                @endif
                @if (count($facts) > 0)
                <ul class="fi-facts">
                    @foreach ($facts as $f)
                        <li>
                        @if ($f['href'])
                            <a class="fi-fact" href="{{ $f['href'] }}">@if ($f['icon'])<span aria-hidden="true">{{ $f['icon'] }}</span>@endif{{ $f['text'] }}</a>
                        @else
                            <span class="fi-fact">@if ($f['icon'])<span aria-hidden="true">{{ $f['icon'] }}</span>@endif{{ $f['text'] }}</span>
                        @endif
                        </li>
                    @endforeach
                </ul>
                @endif
            </section>
            @endif
        </div>

        {{-- Elige tu salida --}}
        <aside class="fi-book" id="seccion-reserva" aria-label="{{ __('ficha.book_title') }}"
               x-data="{
                   price: window.__lvtPrice || 0,
                   maxPax: 15,
                   get pax() { return this.$store.booking.adults + this.$store.booking.children; },
                   get total() { return (this.pax * this.price).toFixed(2); },
                   paxLabel() { var f = window.LVT_FICHA || {}; return ((this.pax === 1 ? f.one : f.other) || '').replace(':count', this.pax); }
               }">
            <div class="fi-book__label">{{ __('ficha.price_label') }}</div>
            <div class="fi-price">
                <strong>{{ $money($tour->price) }}</strong>
                @if ($hasOffer)
                    <s><span class="fi-sr">{{ __('ui.price_before') }}: </span>{{ $money($tour->price_before) }}</s>
                    <span class="fi-price__sale">−{{ $discountPct }}%</span>
                @endif
            </div>
            <p class="fi-flexpay">{{ __('ui.tour_flex_pay_line') }}</p>

            <div class="fi-book__title" role="heading" aria-level="2">{{ __('ficha.book_title') }}</div>

            <div class="fi-field" data-date-field>
                <label class="fi-field__label" for="fi-date">{{ __('ficha.date_label') }}</label>
                <input type="date" id="fi-date" class="fi-date lvt-date-input"
                       data-booking-date
                       placeholder="{{ __('ui.select_tour_date') }}"
                       x-model="$store.booking.date"
                       min="{{ $bookingMinDate }}"
                       required
                       aria-describedby="fi-date-notice">
                <p class="fi-notice" id="fi-date-notice">{{ $bookingNotice }}</p>
                @error('travel_date')
                    <p class="fi-error" role="alert" data-date-server-error>{{ $message }}</p>
                @enderror
            </div>

            @include('tours.partials.slot-picker', ['idSuffix' => 'd'])

            <div class="fi-field">
                <span class="fi-field__label" id="fi-pax-label">{{ __('ficha.travelers_label') }}</span>
                <div class="fi-qty" role="group" aria-labelledby="fi-pax-label">
                    <button type="button" @click="$store.booking.adults = Math.max(1, $store.booking.adults - 1)" :disabled="$store.booking.adults <= 1" aria-label="{{ __('ficha.traveler_less') }}">−</button>
                    <output aria-live="polite" x-text="paxLabel()">{{ __('ficha.traveler_one', ['count' => 1]) }}</output>
                    <button type="button" @click="$store.booking.adults = Math.min(maxPax, $store.booking.adults + 1)" :disabled="$store.booking.adults >= maxPax" aria-label="{{ __('ficha.traveler_more') }}">+</button>
                </div>
            </div>

            <div class="fi-total">
                <span>{{ __('ficha.total_label') }}</span>
                <strong>US$<span x-text="total">{{ number_format((float) $tour->price, 2, '.', '') }}</span></strong>
            </div>

            <p class="fi-error" role="alert" id="fi-book-error" hidden></p>
            <button type="submit" form="form-reservar" class="fi-cta" id="fi-add" data-ga-cta="reservar" data-ga-location="ficha">
                <span class="fi-cta__label">{{ __('ficha.add_to_cart') }}</span> <span aria-hidden="true">→</span>
            </button>
            <p class="fi-foot">{{ __('ficha.foot_pay') }}@if ($pickupIncluded) · {{ __('ficha.foot_pickup') }}@endif</p>
            @if ($socialProof !== null)
            <p class="fi-proof" data-ga-view="social_proof_view" data-ga-count="{{ $socialProof }}"><span aria-hidden="true">✔</span> {{ $socialProof }} {{ __('ui.travelers_booked_week') }}</p>
            @endif
        </aside>
    </div>

    {{-- Itinerario | Qué incluye --}}
    @if (count($itinerary) > 0 || $hasIncludes || $notesReal !== '' || count($recsReal) > 0 || $showPickup)
    <div class="fi-grid">
        @if (count($itinerary) > 0)
        @php $stepCount = count($itinerary); @endphp
        <section class="fi-sec" id="itinerario" aria-labelledby="fi-itin" x-data="{ open: false }">
            <h2 id="fi-itin">{{ __('ficha.itinerary_title') }}</h2>
            <p class="fi-sec__sub">{{ __('ficha.itinerary_sub') }}</p>
            <ol class="fi-timeline" id="fi-timeline" :class="{ 'is-open': open }">
                @foreach ($itinerary as $i => $step)
                    <li class="fi-stop @if ($i >= 5) fi-stop--extra @endif @if ($i === 4 && $stepCount > 5) fi-stop--lastvisible @endif">
                        <span class="fi-stop__num" aria-hidden="true">{{ $i + 1 }}</span>
                        <div>
                            @if (! empty($step['title']) || ! empty($step['time']))
                            <h3>@if (! empty($step['time']))<span class="fi-stop__time">{{ $step['time'] }}</span>@endif{{ $step['title'] ?? '' }}</h3>
                            @endif
                            @if (! empty($step['description']))<p>{{ $step['description'] }}</p>@endif
                        </div>
                    </li>
                @endforeach
            </ol>
            @if ($stepCount > 5)
            <button type="button" class="fi-toggle" aria-controls="fi-timeline" :aria-expanded="open ? 'true' : 'false'" @click="open = !open">
                <span x-show="!open">{{ __('ficha.itinerary_more') }} ↓</span>
                <span x-show="open" x-cloak>{{ __('ficha.itinerary_less') }} ↑</span>
            </button>
            @endif
        </section>
        @endif

        @if ($hasIncludes || $notesReal !== '' || count($recsReal) > 0)
        <section class="fi-sec" id="incluye" aria-labelledby="fi-inc">
            <h2 id="fi-inc">{{ __('ficha.includes_title') }}</h2>
            @if ($hasIncludes)
            <div class="fi-cols {{ ($includesList !== [] && $excludesList !== []) ? '' : 'fi-cols--one' }}">
                @if ($includesList !== [])
                <div class="fi-included">
                    <h3>{{ __('ficha.includes_yes') }}</h3>
                    <ul class="fi-checks">
                        @foreach ($includesList as $it)<li><span class="fi-check" aria-hidden="true">✓</span>{{ $it }}</li>@endforeach
                    </ul>
                </div>
                @endif
                @if ($excludesList !== [])
                <div class="fi-excluded">
                    <h3>{{ __('ficha.includes_no') }}</h3>
                    <ul class="fi-checks">
                        @foreach ($excludesList as $it)<li><span class="fi-cross" aria-hidden="true">×</span>{{ $it }}</li>@endforeach
                    </ul>
                </div>
                @endif
            </div>
            @endif
            @if ($notesReal !== '')
            <div class="fi-notes">{!! nl2br(e($notesReal)) !!}</div>
            @endif
            @if (count($recsReal) > 0)
            <details class="fi-recs">
                <summary>{{ __('ficha.recommendations_title') }}</summary>
                <ul>@foreach ($recsReal as $rec)<li>{{ $rec }}</li>@endforeach</ul>
            </details>
            @endif
        </section>
        @endif

        {{-- ¿Dónde te recogemos? (opcional: zonas + clave de Maps en Ajustes) --}}
        @if ($showPickup)
        <section class="fi-sec fi-sec--wide" aria-labelledby="fi-pickup">
            <h2 id="fi-pickup">{{ __('ui.pickup_zones_title') }}</h2>
            <p class="fi-sec__sub">{{ __('ui.pickup_zones_subtitle') }}</p>
            <div id="fi-pickup-map" class="fi-map"></div>
            <ul class="fi-zones">
                @foreach ($pickupZones as $zone)
                    <li><b>{{ $zone['label'] }}</b> — {{ __('ui.pickup_zones_radius', ['km' => rtrim(rtrim(number_format($zone['radius_km'], 1), '0'), '.')]) }} · {{ $zone['type'] === 'hotels' ? __('ui.pickup_zones_hotels_only') : __('ui.pickup_zones_type_all') }}</li>
                @endforeach
            </ul>
        </section>
        @endif
    </div>
    @endif

    {{-- Comparativa (opcional, administrable) --}}
    @if ($comparison)
    <div class="fi-related"><x-tour-comparison :data="$comparison" variant="d" /></div>
    @endif

    {{-- Opiniones de viajeros --}}
    <section class="fi-sec fi-related" id="resenas" aria-labelledby="fi-rev">
        <h2 id="fi-rev">{{ __('ficha.reviews_title') }}</h2>

        @if ($reviewStats['total'] > 0)
        <div class="fi-rsum">
            <div class="fi-rsum__avg">
                <strong>{{ $ratingText }}</strong>
                <span class="fi-stars" aria-hidden="true">{{ $starsGlyph($starsFilled) }}</span>
                <small>{{ $reviewStats['total'] }} {{ $reviewStats['total'] === 1 ? __('ficha.review_of_tour') : __('ficha.reviews_of_tour') }}</small>
            </div>
            <div class="fi-bars" role="group" aria-label="{{ __('ficha.reviews_bars') }}">
                @foreach ([5, 4, 3, 2, 1] as $star)
                    @php $pct = (int) ($reviewStats['distribution'][$star]['percent'] ?? 0); @endphp
                    <div>
                        <span>{{ $star }} ★</span>
                        <span class="fi-bars__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}" aria-label="{{ $star }} ★"><span class="fi-bars__fill" style="width:{{ $pct }}%"></span></span>
                        <small>{{ $pct }}%</small>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        @if ($stamp = \App\Models\Setting::tripadvisorStamp())
            <x-tripadvisor-stamp :stamp="$stamp" variant="d" />
        @endif

        @if (session('review_status'))
            <div class="fi-rform__flash fi-rform__flash--ok" role="status">{{ session('review_status') }}</div>
        @endif

        @if ($tourReviews->isNotEmpty())
        <div class="fi-rlist">
            @foreach ($tourReviews->take(3) as $rev)
                @include('tours.partials.review-item', ['rev' => $rev])
            @endforeach
        </div>
        @if ($tourReviews->count() > 3)
        <details class="fi-rmore">
            <summary class="fi-rlink" style="cursor:pointer;list-style:none">{{ __('ui.see_more') }} ({{ $tourReviews->count() - 3 }})</summary>
            <div class="fi-rlist" style="margin-top:12px">
                @foreach ($tourReviews->slice(3) as $rev)
                    @include('tours.partials.review-item', ['rev' => $rev])
                @endforeach
            </div>
        </details>
        @endif
        <a class="fi-rlink" href="{{ \App\Support\LocalizedPages::url('reviews', $locale) }}">{{ __('ficha.reviews_all') }} ↗</a>
        @else
            <p class="fi-sec__sub" style="margin-top:8px">{{ __('ui.be_first_review') }}</p>
        @endif

        {{-- Añadir una valoración (queda pendiente de aprobación) --}}
        <details class="fi-rform" x-data="{ rating: {{ (int) old('rating', 0) }} }" @if ($errors->hasAny(['rating','comment','name','email'])) open @endif>
            <summary>✎ {{ __('ui.add_review') }}</summary>
            <form method="POST" action="{{ route('tours.review.store', ['locale' => $locale, 'slug' => $tour->slug]) }}">
                @csrf
                @if ($errors->hasAny(['rating','comment','name','email']))
                    <div class="fi-rform__flash fi-rform__flash--err" role="alert">{{ $errors->first('rating') ?: $errors->first('comment') ?: $errors->first('name') ?: $errors->first('email') }}</div>
                @endif
                <div>
                    <span class="fi-field__label" id="fi-rv-r">{{ __('ui.your_rating') }} *</span>
                    <div class="fi-rform__stars" role="radiogroup" aria-labelledby="fi-rv-r">
                        <template x-for="n in 5" :key="n">
                            <button type="button" :class="n <= rating && 'is-on'" role="radio" :aria-checked="n === rating ? 'true' : 'false'" :aria-label="n + ' / 5'" @click="rating = n">★</button>
                        </template>
                    </div>
                    <input type="hidden" name="rating" :value="rating">
                </div>
                <div><label for="fi-rv-c">{{ __('ui.your_review') }} *</label><textarea id="fi-rv-c" name="comment" required minlength="10" maxlength="2000" rows="4">{{ old('comment') }}</textarea></div>
                <div><label for="fi-rv-n">{{ __('ui.name') }} *</label><input id="fi-rv-n" type="text" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name"></div>
                <div><label for="fi-rv-e">{{ __('ui.email') }} *</label><input id="fi-rv-e" type="email" name="email" value="{{ old('email') }}" required maxlength="160" autocomplete="email"><p class="fi-notice">{{ __('ui.email_not_published') }}</p></div>
                <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="fi-hp">
                <button type="submit" class="fi-rform__submit">{{ __('ui.submit_review') }}</button>
            </form>
        </details>
    </section>

    {{-- Tours relacionados --}}
    @if ($relatedTours->isNotEmpty())
    <section class="fi-sec fi-related" id="cruzada" aria-labelledby="fi-rel">
        <h2 id="fi-rel">{{ __('ficha.related_title') }}</h2>
        <div class="fi-related-grid">
            @foreach ($relatedTours as $rel)
                @php
                    $relTitle = $rel->title;
                    $relHref  = route('tours.show', ['locale' => $locale, 'slug' => $rel->slugFor($locale)]);
                    $relImg   = \App\Support\ImageVariants::card($rel->cover_image ?? null, '(min-width: 841px) 30vw, (min-width: 561px) 45vw, 100vw');
                    $relDur   = \App\Support\TourListing::durationLabel($rel->duration ?? null);
                    $relMeta  = implode(' · ', array_filter([$relDur, $rel->category?->name]));
                    $relOffer = $rel->price_before && (float) $rel->price_before > (float) $rel->price;
                @endphp
                <article class="fi-rcard">
                    <img class="fi-rcard__img" src="{{ $relImg['src'] ?: $rel->cover_url }}" @if ($relImg['srcset']) srcset="{{ $relImg['srcset'] }}" sizes="{{ $relImg['sizes'] }}" @endif alt="{{ $relTitle }}" width="400" height="160" loading="lazy" decoding="async">
                    <div class="fi-rcard__body">
                        <h3><a href="{{ $relHref }}">{{ $relTitle }}</a></h3>
                        @if ($relMeta !== '')<p>{{ $relMeta }}</p>@endif
                        <p class="fi-rcard__price"><strong>{{ $money($rel->price) }}</strong>@if ($relOffer) <s>{{ $money($rel->price_before) }}</s>@endif <small>{{ __('ficha.per_person') }}</small></p>
                        <a class="fi-rcard__link" href="{{ $relHref }}" aria-label="{{ __('ficha.see_tour') }}: {{ $relTitle }}">{{ __('ficha.see_tour') }} →</a>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
    @endif

    {{-- Preguntas frecuentes (visibles en el HTML; <details> nativo) --}}
    @if (count($tourFaqs) > 0)
    <section class="fi-sec fi-faq" id="faq" aria-labelledby="fi-faq">
        <h2 id="fi-faq">{{ __('ficha.faq_title') }}</h2>
        <div style="margin-top:8px">
        @foreach ($tourFaqs as $faq)
            <details data-ga-faq="ficha">
                <summary>{{ $faq['question'] }}</summary>
                <div>{!! nl2br(e($faq['answer'])) !!}</div>
            </details>
        @endforeach
        </div>
    </section>
    @endif

</div>{{-- /fi --}}

{{-- Visor de fotos --}}
<div class="fi-lb" :class="{ 'is-open': lb }" role="dialog" aria-modal="true" aria-label="{{ __('ficha.photo_modal') }}" @click.self="closeLb()" x-cloak>
    <div class="fi-lb__nav" x-show="gallery.length > 1">
        <button type="button" @click="prev()" aria-label="{{ __('ficha.photo_prev') }}">‹</button>
        <button type="button" @click="next()" aria-label="{{ __('ficha.photo_next') }}">›</button>
    </div>
    <button type="button" class="fi-lb__close" x-ref="lbClose" @click="closeLb()" aria-label="{{ __('ficha.photo_close') }}">×</button>
    <div class="fi-lb__stage" @click.self="closeLb()"><img :src="gallery[active]" :alt="alts[active] || ''"></div>
    <div class="fi-lb__cap"><span x-text="active + 1"></span> / <span x-text="gallery.length"></span> · <span x-text="alts[active]"></span></div>
</div>

{{-- Cajón del carrito --}}
<div class="fi-ov" id="fi-cart"
     x-data="lvtCartDrawer()"
     :class="{ 'is-open': open }"
     role="dialog" aria-modal="true" aria-label="{{ __('ficha.cart_title') }}"
     @click.self="close()"
     @keydown.escape.window="open && close()"
     @lvt-cart-open.window="show($event.detail)"
     x-cloak>
    <div class="fi-drawer" x-ref="panel">
        <div class="fi-drawer__head">
            <div class="fi-drawer__title" role="heading" aria-level="2">{{ __('ficha.cart_title') }}</div>
            <button type="button" class="fi-close" x-ref="closeBtn" @click="close()" aria-label="{{ __('ficha.cart_close') }}">×</button>
        </div>
        <p class="fi-drawer__sub">{{ __('ficha.cart_sub') }}</p>
        <template x-if="cart.items.length === 0"><div class="fi-drawer__empty">{{ __('ficha.cart_empty') }}</div></template>
        <template x-for="row in cart.items" :key="row.row_id">
            <div class="fi-crow">
                <div>
                    <strong x-text="row.title"></strong>
                    <small>
                        <span x-text="row.date_label || i18n.no_date"></span>
                        <template x-if="row.departure_label"><span> · <span x-text="row.departure_label"></span></span></template>
                        · <span x-text="i18n.travelers.replace(':count', row.quantity)"></span>
                    </small>
                    <template x-if="row.available_slots && row.available_slots.length > 1">
                        <select :aria-label="i18n.change_slot" @change="changeSlot(row, $event.target.value)">
                            <template x-for="o in row.available_slots" :key="o.value"><option :value="o.value" :selected="o.value === row.departure_time" x-text="o.label"></option></template>
                        </select>
                    </template>
                </div>
                <b x-text="money(row.subtotal)"></b>
            </div>
        </template>
        <div class="fi-drawer__total"><span>{{ __('ficha.cart_total') }}</span><strong x-text="money(cart.total)">US$0.00</strong></div>
        <p class="fi-drawer__err" role="alert" x-show="err" x-text="err" x-cloak></p>
        <a class="fi-cta" :href="cart.cart_url || '{{ route('cart.index', ['locale' => $locale]) }}'" :style="cart.items.length === 0 ? 'opacity:.45;pointer-events:none' : ''">{{ __('ficha.cart_checkout') }} <span aria-hidden="true">→</span></a>
        <div class="fi-drawer__links">
            <button type="button" @click="close()">{{ __('ficha.cart_continue') }}</button>
            <button type="button" @click="clearCart()" :disabled="cart.items.length === 0">{{ __('ficha.cart_clear') }}</button>
        </div>
    </div>
</div>
</div>{{-- /fi-page --}}

{{-- Formulario real de reserva (POST al carrito); la vista lo envía por fetch y, si falla, por envío normal --}}
<form id="form-reservar" method="POST" action="{{ route('cart.store', ['locale' => $locale]) }}" hidden x-data>
    @csrf
    <input type="hidden" name="tour_id" value="{{ $tour->id }}">
    <input type="hidden" name="travel_date" :value="$store.booking.date">
    <input type="hidden" name="departure_slot" :value="$store.booking.slot">
    <input type="hidden" name="adults" :value="$store.booking.adults">
    <input type="hidden" name="children" :value="$store.booking.children">
</form>

{{-- Barra fija móvil: "Desde US$X / persona · Ver fechas" + WhatsApp --}}
@if ((float) $tour->price > 0)
<div class="fi-bar"
     x-data="{
         shown: false,
         init() {
             this.shown = !window.matchMedia('(min-width: 768px)').matches;
             document.documentElement.classList.add('has-resbar');
             const fw = document.querySelector('.lvt-wa');
             if (fw && this.$refs.wa) this.$refs.wa.href = fw.href;
             const target = document.getElementById('seccion-reserva');
             const mq = window.matchMedia('(min-width: 768px)');
             const sync = () => {
                 const on = this.shown && !mq.matches;
                 document.documentElement.style.setProperty('--sticky-bar-h', on ? this.$el.offsetHeight + 'px' : '0px');
             };
             this.$watch('shown', () => this.$nextTick(sync));
             mq.addEventListener('change', sync);
             window.addEventListener('resize', sync);
             this.$nextTick(sync);
             if (!target || !('IntersectionObserver' in window)) return;
             new IntersectionObserver(([e]) => {
                 const cardOnScreen = e.intersectionRect.height > window.innerHeight * 0.55;
                 this.shown = !mq.matches && !cardOnScreen;
             }, { threshold: [0, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1] }).observe(target);
         },
         go() {
             const target = document.getElementById('seccion-reserva');
             if (!target) return;
             const header = document.querySelector('.site-header');
             const hh = header && getComputedStyle(header).position !== 'static' ? header.offsetHeight : 0;
             const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
             window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - hh - 8, behavior: reduce ? 'auto' : 'smooth' });
             setTimeout(() => { const d = target.querySelector('[data-booking-date]'); if (d) d.focus({ preventScroll: true }); }, reduce ? 0 : 600);
         }
     }"
     :class="shown ? 'is-on' : ''"
     :aria-hidden="shown ? 'false' : 'true'">
    <div class="fi-bar__price"><small>{{ __('ficha.bar_from') }} {{ __('ficha.bar_per_person') }}</small>{{ $money($tour->price) }}</div>
    <div class="fi-bar__actions">
        <a x-ref="wa" href="#" target="_blank" rel="noopener noreferrer" class="fi-bar__wa" aria-label="{{ __('ficha.bar_whatsapp') }}"
           data-wa-location="sticky" data-ga-cta="whatsapp" data-ga-location="sticky_whatsapp">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.978-1.045zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.148-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.017-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.247-.694.247-1.289.173-1.413z"/></svg>
        </a>
        <button type="button" class="fi-bar__cta" @click="go()" data-ga-cta="comprobar_disponibilidad" data-ga-location="sticky">{{ __('ficha.bar_see_dates') }} →</button>
    </div>
</div>
@endif

@endsection
