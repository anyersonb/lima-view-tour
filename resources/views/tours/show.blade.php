@extends('layouts.app')

@php
    $locale     = app()->getLocale();
    $gallery    = $tour->gallery ?? [];
    $itinerary  = $tour->{"itinerary_{$locale}"} ?? $tour->itinerary_es ?? [];
    $includes   = $tour->{"includes_{$locale}"}  ?? $tour->includes_es  ?? [];
    $excludes   = $tour->{"excludes_{$locale}"}  ?? $tour->excludes_es  ?? [];
    $recommendations = $tour->{"recommendations_{$locale}"} ?? $tour->recommendations_es ?? null;
    $notes      = $tour->{"notes_{$locale}"}     ?? $tour->notes_es     ?? null;
    $contactPhone = \App\Models\Setting::get('contact_phone', '+51 925 886 725');

    $recommendationLines = $recommendations
        ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $recommendations))))
        : ['Llevar protector solar SPF50+', 'Lentes de sol y sombrero', 'Ropa cómoda y zapatillas', 'Cámara fotográfica', 'Agua embotellada', 'Documento de identidad'];

    $notesText = $notes ?: 'Las operaciones pueden ajustarse por condiciones climáticas; ante cancelación se reembolsa o reagenda sin costo.';

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
            if (!empty($time) && preg_match('/^(\d{1,2}):(\d{2})\s*(am|pm)?/iu', $time, $tm)) {
                $h = (int) $tm[1];
                $min = $tm[2];
                $mer = mb_strtolower($tm[3] ?? '');
                if ($mer === 'pm' && $h < 12) { $h += 12; }
                if ($mer === 'am' && $h === 12) { $h = 0; }
                $time = sprintf('%02d:%02d', $h, $min);
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
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}
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

/* ── Tarjeta reserva compacta (desktop) ── */
.booking-compact-card { background: #fff; border-radius: 1.25rem; box-shadow: 0 2px 16px rgba(21,71,75,.10); overflow: hidden; }
.booking-compact-head { background: #15474B; padding: .625rem 1rem; display: flex; align-items: center; justify-content: space-between; }
.booking-compact-body { padding: 1rem; }

/* ── Barra flotante bottom v2 ── */
.sticky-bar-v2 { padding-bottom: env(safe-area-inset-bottom, 0); }

/* El header global del sitio se mantiene como en el resto del sitio (no se oculta) */

/* ════════════════════════════════════════
   MOBILE SHOW — estilos exclusivos <768px
   ════════════════════════════════════════ */
@media (max-width: 767px) {

/* Variables */
:root {
  --m-green: #083b31;
  --m-green2: #0e5a47;
  --m-cream: #fbfaf6;
  --m-line: #ece7de;
  --m-text: #131313;
  --m-muted: #6e7278;
  --m-gold: #f0aa1b;
  --m-orange: #d96b33;
}

/* Hero mobile */
.m-hero { height: 356px; position: relative; color: #fff; }
.m-hero::after {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(0,0,0,.45), rgba(0,0,0,.04) 42%, rgba(0,0,0,.25));
  pointer-events: none;
}
.m-topbar {
  position: relative;
  z-index: 1;
  height: 74px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px;
}
.m-logo {
  font-family: Georgia, "Times New Roman", serif;
  text-align: center;
  font-size: 18px;
  letter-spacing: 3px;
  line-height: 1;
  color: #fff;
}
.m-logo span { display: block; font-size: 11px; letter-spacing: 4px; margin-top: 2px; }
.m-hero-badges {
  position: absolute;
  left: 18px;
  bottom: 22px;
  z-index: 2;
  max-width: 300px;
}
.m-main-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(255,255,255,.94);
  color: #083b31;
  border-radius: 14px;
  padding: 9px 14px;
  box-shadow: 0 10px 20px rgba(0,0,0,.14);
  font-size: 12.5px;
  font-weight: 900;
  letter-spacing: .12px;
}
.m-main-badge .m-ico { color: #e0a21f; font-size: 13px; line-height: 1; }
.m-subline {
  margin-top: 8px;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: rgba(7,29,25,.36);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(255,255,255,.18);
  border-radius: 999px;
  padding: 8px 11px;
  color: #fff;
  font-size: 10.5px;
  font-weight: 700;
  box-shadow: 0 8px 18px rgba(0,0,0,.12);
}
.m-subitem { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
.m-subitem .m-ico { font-size: 11.5px; line-height: 1; }
.m-subitem.safe .m-ico { color: #79d39e; }
.m-subitem.urgent .m-ico { color: #ff9d5c; }
.m-divider { width: 1px; height: 14px; background: rgba(255,255,255,.22); }
.m-hero-nav {
  position: absolute; top: 50%; transform: translateY(-50%); z-index: 2;
  width: 36px; height: 36px; border-radius: 50%; border: none; cursor: pointer;
  background: rgba(0,0,0,.38); color: #fff; font-size: 22px; line-height: 1;
  display: flex; align-items: center; justify-content: center;
  backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
}
.m-hero-nav.prev { left: 12px; }
.m-hero-nav.next { right: 12px; }
.m-counter {
  position: absolute;
  z-index: 2;
  right: 18px;
  bottom: 14px;
  background: rgba(0,0,0,.55);
  color: #fff;
  border-radius: 16px;
  padding: 6px 10px;
  font-size: 12px;
}

/* Título + rating mobile */
.m-title {
  font-family: Georgia, "Times New Roman", serif;
  font-size: 27px;
  line-height: 1.08;
  margin: 0 0 14px;
  color: #111;
}
.m-rating {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  font-weight: 700;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.m-stars { color: #ffb000; letter-spacing: 1px; }
.m-verified {
  display: inline-flex;
  background: #0a8b4d;
  color: white;
  border-radius: 50%;
  width: 14px;
  height: 14px;
  align-items: center;
  justify-content: center;
  font-size: 9px;
}

/* Compact booking card mobile */
.m-compact { background: #f6f5f2; border: 1px solid #e5dfd6; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,.035); margin-top: 10px; }
.m-compact-head { display: flex; justify-content: space-between; align-items: center; background: #dc9343; color: #fff; padding: 7px 10px; font-size: 9.5px; font-weight: 900; letter-spacing: .55px; text-transform: uppercase; }
.m-compact-body { padding: 9px 10px 10px; }
.m-price-row { display: grid; grid-template-columns: .9fr 1.1fr; gap: 8px; align-items: stretch; margin-bottom: 10px; }
.m-price-box { min-width: 0; border-radius: 12px; padding: 8px 9px; background: #fff; border: 1px solid #e6dfd4; }
.m-price-box.old-box { background: #fbfaf8; }
.m-price-box.new-box { background: linear-gradient(180deg, #fffef9 0%, #f3f7ef 100%); border-color: #dde6d9; box-shadow: 0 4px 10px rgba(0,0,0,.03); }
.m-price-label { display: inline-block; font-size: 8px; font-weight: 900; letter-spacing: .7px; color: #727c84; text-transform: uppercase; padding-bottom: 4px; margin-bottom: 6px; border-bottom: 1px solid #e1dbd0; line-height: 1; }
.m-price-label.current { color: #2d9555; border-bottom-color: #d8e5d5; }
.m-price-value { line-height: 1; }
.m-price-value.old { font-size: 15px; color: #81888f; text-decoration: line-through; text-decoration-color: #cc5b4e; text-decoration-thickness: 1.8px; }
.m-price-value.new { font-family: Georgia, serif; font-size: 24px; color: #0a3240; }
.m-price-unit { font-size: 8.5px; font-weight: 700; color: #6f7981; margin-top: 4px; line-height: 1.05; letter-spacing: .25px; }
.m-field { margin-bottom: 7px; }
.m-label { display: block; font-size: 9px; font-weight: 800; color: #465e68; letter-spacing: .22px; text-transform: uppercase; margin-bottom: 4px; }
.m-input-row {
  height: 36px;
  border: 1px solid #d7dad9;
  border-radius: 18px;
  background: #fff;
  padding: 0 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
  color: #29404a;
  width: 100%;
  box-sizing: border-box;
}
.m-mini-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 7px; }
.m-total { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; padding: 11px 13px; background: linear-gradient(180deg,#f3f7ef 0%,#e9f3e4 100%); border: 1px solid #d4e3cf; border-radius: 12px; color: #2d5a3d; font-size: 13px; font-weight: 800; }
.m-total strong { font-family: Georgia, serif; font-size: 23px; line-height: 1; color: #0a3240; font-weight: 800; }
.m-total .m-total-sub { display: block; font-size: 10px; font-weight: 600; color: #5f7466; letter-spacing: .2px; margin-top: 2px; }
.m-cta { margin-top: 8px; background: #dc9343; color: #fff; border-radius: 20px; text-align: center; padding: 10px 8px; font-size: 12px; font-weight: 900; letter-spacing: .12px; cursor: pointer; border: none; width: 100%; display: block; }
.m-foot { margin-top: 7px; text-align: center; color: #7d8b93; font-size: 9.5px; }

/* Trust row mobile */
.m-trust-row { display: grid; grid-template-columns: 1fr 1fr; border-top: 1px solid var(--m-line); border-bottom: 1px solid var(--m-line); margin: 8px 0 14px; }
.m-trust { padding: 12px 4px; text-align: center; font-size: 13px; font-weight: 800; color: #131313; }
.m-trust small { display: block; color: #555; font-weight: 600; margin-top: 2px; font-size: 12px; }
.m-notice { background: #f0f7ee; border-radius: 8px; color: #0c5a3d; text-align: center; padding: 10px; font-size: 13px; font-weight: 900; }
.m-features { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 18px; text-align: center; }
.m-feature .m-ico { font-size: 27px; margin-bottom: 6px; }
.m-feature b { display: block; font-size: 12px; line-height: 1.15; }
.m-feature small { display: block; color: #555; font-size: 11px; margin-top: 3px; line-height: 1.2; }

/* Section heading mobile */
.m-h2 { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.m-h2 h2 { font-family: Georgia, serif; font-size: 20px; margin: 0; color: #111; }
.m-h2 a { font-size: 12px; color: var(--m-green); font-weight: 900; text-decoration: none; }

/* Experience row mobile */
.m-exp-row { display: flex; gap: 10px; overflow: auto; padding-bottom: 8px; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; }
.m-exp-row::-webkit-scrollbar { display: none; }
.m-exp { flex: 0 0 46%; max-width: 46%; scroll-snap-align: start; }
.m-exp img { width: 100%; height: 120px; border-radius: 10px; object-fit: cover; }
.m-exp b { font-size: 12px; display: block; margin-top: 6px; }
.m-exp small { font-size: 11px; color: #555; line-height: 1.15; display: block; }
.m-dots { text-align: center; color: #c8c8c8; letter-spacing: 7px; margin-top: 8px; }
.m-dots .active { color: #111; }

/* Timeline mobile */
.m-timeline { border-top: 1px solid var(--m-line); padding-top: 4px; }
.m-stop {
  display: grid;
  grid-template-columns: 48px 38px 1fr;
  gap: 8px;
  align-items: start;
  position: relative;
  margin: 16px 0;
}
.m-stop::before {
  content: "";
  position: absolute;
  left: 17px;
  top: 28px;
  bottom: -22px;
  width: 2px;
  background: var(--m-green);
}
.m-stop:last-child::before { display: none; }
.m-time {
  display: inline-flex; align-items: center; justify-content: center;
  font-weight: 800; font-size: 12px; color: var(--m-green);
  background: #eef3ef; border: 1px solid #d8e5d5; border-radius: 8px;
  padding: 3px 0; margin-top: 1px; letter-spacing: .3px;
  font-variant-numeric: tabular-nums;
}
.m-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: var(--m-green);
  margin: 4px 0 0 12px;
  box-shadow: 0 0 0 4px #e7f0eb;
}
.m-stop b { font-size: 13px; }
.m-stop p { margin: 2px 0 0; color: #4c4f54; font-size: 12px; line-height: 1.25; }

/* Info list accordion mobile */
.m-info-list { display: flex; flex-direction: column; border: 1px solid var(--m-line); border-radius: 14px; overflow: hidden; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,.04); }
.m-info-list details { border-bottom: 1px solid var(--m-line); }
.m-info-list details:last-child { border-bottom: 0; }
.m-info-item { display: grid; grid-template-columns: 44px 1fr 18px; gap: 12px; align-items: center; padding: 15px 15px; cursor: pointer; transition: background .2s ease; list-style: none; }
.m-info-item:hover { background: #fafbfa; }
.m-info-list details[open] > .m-info-item { background: #f1f7f1; }
.m-info-list details[open] > .m-info-item b { color: var(--m-green); }
.m-info-ico { width: 40px; height: 40px; border-radius: 11px; display: grid; place-items: center; background: var(--m-green); color: #fff; font-size: 20px; }
.m-info-ico svg { width: 20px; height: 20px; }
.m-info-item b { font-size: 14px; color: #14201c; font-weight: 800; }
.m-info-item p { font-size: 12px; color: #6b7077; margin: 2px 0 0; line-height: 1.3; }
.m-info-list details > div { font-size: 13px !important; color: #444 !important; line-height: 1.6 !important; }
.m-info-list details > div > div { padding: 3px 0; border-bottom: 1px dashed #eee; }
.m-info-list details > div > div:last-child { border-bottom: 0; }

/* Review cards mobile */
.m-review-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.m-platform { border: 1px solid var(--m-line); border-radius: 9px; padding: 13px; }
.m-platform .brand { font-weight: 900; font-size: 13px; }
.m-platform .score { font-size: 22px; font-weight: 900; margin: 9px 0 2px; }
.m-platform small { color: #555; }
.m-platform a { display: block; margin-top: 8px; color: #111; text-decoration: none; font-weight: 800; font-size: 12px; }
.m-comment { display: grid; grid-template-columns: 42px 1fr; gap: 10px; padding: 15px 0; border-bottom: 1px solid var(--m-line); }
.m-comment img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; }
.m-comment b { font-size: 13px; }
.m-comment .m-date { float: right; color: #777; font-size: 11px; font-weight: 500; }
.m-comment p { font-size: 12px; margin: 5px 0 0; line-height: 1.35; }
/* ── Caja de reseñas (form estilo WooCommerce adaptado al diseño) ── */
.m-review-flash { margin: 12px 0; padding: 11px 13px; background: #eaf6ee; border: 1px solid #bfe3cc; border-radius: 10px; color: #176a3d; font-size: 12.5px; font-weight: 700; }
.m-reviews-empty { font-size: 12.5px; color: #6b7077; margin: 12px 0 0; }
.m-review-form-wrap { margin-top: 14px; border: 1px solid var(--m-line); border-radius: 14px; overflow: hidden; background: #fff; }
.m-review-toggle { display: flex; align-items: center; gap: 8px; padding: 13px 15px; cursor: pointer; font-weight: 800; font-size: 13px; color: var(--m-green); background: #f4f8f4; list-style: none; }
.m-review-toggle::-webkit-details-marker { display: none; }
.m-review-toggle .chev { margin-left: auto; transition: transform .25s ease; }
.m-review-form-wrap[open] .m-review-toggle .chev { transform: rotate(180deg); }
.m-review-form { padding: 14px 15px 16px; }
.m-review-form .fld { margin-bottom: 11px; }
.m-review-form label { display: block; font-size: 11px; font-weight: 800; color: #465e68; text-transform: uppercase; letter-spacing: .2px; margin-bottom: 5px; }
.m-review-form label .req { color: #e13b2f; }
.m-review-form textarea, .m-review-form input[type="text"], .m-review-form input[type="email"] {
  width: 100%; border: 1px solid #d7dad9; border-radius: 10px; background: #fff; padding: 9px 11px; font-size: 13px; color: #29404a; font-family: inherit; outline: none;
}
.m-review-form textarea:focus, .m-review-form input:focus { border-color: var(--m-green); box-shadow: 0 0 0 2px rgba(8,59,49,.10); }
.m-review-form textarea { min-height: 84px; resize: vertical; }
.m-rate-stars { display: inline-flex; gap: 4px; font-size: 26px; line-height: 1; cursor: pointer; }
.m-rate-stars span { color: #d8dcd8; transition: color .12s ease; }
.m-rate-stars span.on { color: #ffb000; }
.m-review-hint { font-size: 10.5px; color: #8a8f95; margin-top: 4px; }
.m-review-errors { background: #fdecea; border: 1px solid #f5c6c0; color: #c0392b; border-radius: 9px; padding: 9px 11px; font-size: 12px; margin-bottom: 11px; font-weight: 600; }
.m-review-submit { width: 100%; background: var(--m-green); color: #fff; border: none; border-radius: 22px; padding: 12px; font-size: 13px; font-weight: 900; cursor: pointer; box-shadow: 0 8px 18px rgba(7,59,47,.18); }
.m-review-hp { position: absolute; left: -9999px; width: 1px; height: 1px; opacity: 0; }
.m-outline-btn { display: block; border: 1px solid #222; border-radius: 20px; text-align: center; padding: 10px; margin: 12px 40px 4px; text-decoration: none; color: #111; font-weight: 900; font-size: 13px; }

/* Rec cards mobile */
.m-rec-list { display: flex; flex-direction: column; gap: 14px; }
.m-rec-card {
  display: grid;
  grid-template-columns: 126px 1fr 98px;
  gap: 12px;
  padding: 12px;
  border: 1px solid #dedede;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 6px 24px rgba(0,0,0,.07);
}
.m-rec-image-wrap { position: relative; }
.m-rec-card img { width: 126px; height: 142px; object-fit: cover; border-radius: 14px; }
.m-rec-badge {
  position: absolute;
  top: 10px;
  left: 0;
  background: #083b31;
  color: #fff;
  font-size: 10px;
  font-weight: 900;
  padding: 6px 10px 6px 10px;
  border-radius: 0 10px 10px 0;
  box-shadow: 0 4px 10px rgba(0,0,0,.15);
}
.m-rec-main { padding-right: 4px; border-right: 1px solid #e7e2d9; }
.m-rec-main h3 { margin: 0 0 6px; font-family: Georgia, serif; font-size: 18px; line-height: 1.05; color: #12323a; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.m-rec-duration { font-size: 12px; color: #d98c27; font-weight: 700; margin: 0 0 6px; }
.m-rec-bullets { display: grid; gap: 5px; }
.m-rec-bullets div { font-size: 11px; color: #23424a; display: flex; align-items: center; gap: 6px; }
.m-rec-rating { margin-top: 8px; font-size: 11px; color: #6a6f75; font-weight: 600; }
.m-rec-rating .s { color: #f0aa1b; margin-right: 4px; }
.m-rec-side { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
.m-rec-side .offer { font-size: 11px; color: #de8b2a; font-weight: 900; letter-spacing: .5px; text-transform: uppercase; margin-bottom: 5px; }
.m-rec-side .price { line-height: 1; }
.m-rec-side .price strong { font-family: Georgia, serif; font-size: 19px; color: #06312c; }
.m-rec-side .price small { display: block; color: #777; font-size: 10px; margin-top: 2px; }
.m-rec-side .disc { display: inline-block; background: #eef0ef; border-radius: 17px; padding: 6px 10px; font-size: 13px; font-weight: 900; color: #0a2031; margin: 10px 0 6px; }
.m-rec-side .before { font-size: 11px; color: #73777b; margin-bottom: 8px; }
.m-rec-btn { background: #083b31; color: #fff; font-weight: 900; border-radius: 14px; padding: 10px 12px; text-align: center; box-shadow: 0 8px 20px rgba(8,59,49,.18); font-size: 11px; text-decoration: none; display: block; }

/* Confidence grid mobile */
.m-confidence { background: #fbfaf6; padding: 18px 20px 24px; }
.m-confidence-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 6px; text-align: center; }
.m-confidence-grid div { font-size: 10.5px; font-weight: 800; }
.m-confidence-grid .m-ico { font-size: 25px; color: var(--m-green); display: block; margin-bottom: 5px; }
.m-confidence-grid small { display: block; color: #555; font-weight: 500; margin-top: 3px; }

/* Floating bottom bar mobile */
.m-floating-bar {
  position: fixed;
  left: 50%;
  transform: translateX(-50%);
  bottom: 8px;
  width: min(390px, calc(100vw - 16px));
  z-index: 9999;
}
.m-bar-inner {
  background: rgba(255,255,255,.99);
  border: 1px solid #e8e0d4;
  border-radius: 16px;
  box-shadow: 0 8px 22px rgba(0,0,0,.10);
  padding: 9px 12px;
}
.m-booking-strip { display: flex; align-items: center; }
.m-bs-col { min-width: 0; }
.m-bs-col.left { width: 34%; padding-right: 10px; border-right: 1px solid #e7dfd3; }
.m-bs-col.center { width: 28%; padding: 0 10px; }
.m-bs-col.right { width: 38%; padding-left: 10px; }
.m-bs-discount {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #fff3f1;
  color: #d84c43;
  border: 1px solid #f0d9d4;
  border-radius: 8px;
  padding: 4px 6px;
  font-size: 8.5px;
  font-weight: 900;
  line-height: 1;
  white-space: nowrap;
}
.m-bs-before { margin-top: 6px; color: #7d8187; line-height: 1.05; }
.m-bs-before .lbl { display: block; font-size: 9px; letter-spacing: .2px; margin-bottom: 2px; }
.m-bs-before .old { font-size: 12px; color: #8a8e93; text-decoration: line-through; text-decoration-color: #d0594e; text-decoration-thickness: 1.6px; }
.m-bs-now { font-size: 9px; font-weight: 900; color: #2e9a57; letter-spacing: .3px; margin-bottom: 2px; }
.m-bs-price { font-family: Georgia, "Times New Roman", serif; font-size: 23px; line-height: 1; color: #082f2e; font-weight: 700; white-space: nowrap; margin-bottom: 4px; }
.m-bs-person { display: inline-flex; align-items: center; gap: 4px; background: #eef2e8; border-radius: 5px; padding: 3px 6px; color: #1f252a; font-size: 9.5px; font-weight: 700; white-space: nowrap; }
.m-bs-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  background: linear-gradient(135deg, #0a4338, #072c2b);
  color: #fff;
  border-radius: 13px;
  min-height: 48px;
  padding: 0 10px;
  font-size: 13px;
  font-weight: 900;
  box-shadow: 0 6px 14px rgba(7,44,43,.16);
  text-decoration: none;
}
.m-bs-arrow {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #f5a623;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 14px;
  font-weight: 900;
  flex: 0 0 24px;
}

/* Padding inferior del contenido mobile para la barra flotante */
.m-content-wrap { padding-bottom: 16px; }

} /* end @media max-width: 767px */

/* ═══════════════════════════════════════
   DESKTOP TWO-COLUMN LAYOUT (md+)
   ═══════════════════════════════════════ */
@media (min-width: 768px) {
    .d-two-col {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
        gap: 2rem;
        align-items: start;
    }
}
@media (min-width: 1024px) {
    .d-two-col { gap: 2.5rem; }
    .d-sidebar-sticky { position: sticky; top: 6rem; }
}

/* ── Slider "Otros viajeros también reservaron" (desktop): 2 cards por vista ── */
.d-related { position: relative; }
.d-related-track {
    display: flex;
    gap: 1rem;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    scroll-behavior: smooth;
    padding-bottom: .25rem;
    scrollbar-width: none;
}
.d-related-track::-webkit-scrollbar { display: none; }
.d-related-track > * { flex: 0 0 calc(50% - .5rem); scroll-snap-align: start; min-width: 0; }
.d-related-nav {
    position: absolute; top: 38%; transform: translateY(-50%);
    width: 40px; height: 40px; border-radius: 9999px;
    background: #fff; color: #15474B;
    box-shadow: 0 4px 14px rgba(20,62,64,.18);
    display: grid; place-items: center; cursor: pointer; z-index: 5;
    border: 1px solid rgba(20,71,75,.1);
}
.d-related-nav:hover { background: #15474B; color: #fff; }
.d-related-nav.prev { left: -14px; }
.d-related-nav.next { right: -14px; }

/* ── Modo nocturno DESACTIVADO (tema claro forzado; reactivar quitando "and (min-width:99999px)") ── */
@media (prefers-color-scheme: dark) and (min-width: 99999px) {
    .booking-compact-card,
    .booking-compact-body { background: #14302f !important; }
    .booking-compact-card { box-shadow: 0 2px 16px rgba(0,0,0,.5); }
}

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
</script>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('booking', {
            adults: 1,
            children: 0,
            date: @json(now()->addDays(7)->format('Y-m-d')),
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

        var blockedDates    = window.LVT_BLOCKED_DATES    || [];
        var blockedWeekdays = window.LVT_BLOCKED_WEEKDAYS || [];
        var tomorrow        = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        tomorrow.setHours(0, 0, 0, 0);

        @if($fpLocale !== 'en')
        var locale = flatpickr.l10ns['{{ $fpLocale }}'] || flatpickr.l10ns.default;
        @else
        var locale = flatpickr.l10ns.default;
        @endif

        var config = {
            minDate:    tomorrow,
            dateFormat: 'Y-m-d',
            locale:     locale,
            disable: [
                // Specific blocked dates
                ...blockedDates,
                // Recurring weekdays
                function (date) {
                    return blockedWeekdays.includes(date.getDay());
                }
            ],
            onChange: function (selectedDates, dateStr) {
                // Keep the Alpine store in sync after flatpickr selection
                if (dateStr && typeof Alpine !== 'undefined') {
                    Alpine.store('booking').date = dateStr;
                }
            }
        };

        // Initialize on both booking date inputs (mobile + desktop)
        document.querySelectorAll('[data-booking-date]').forEach(function (el) {
            // Set initial value from Alpine store if available
            var initialDate = (typeof Alpine !== 'undefined')
                ? Alpine.store('booking').date
                : el.value;
            config.defaultDate = initialDate || tomorrow;
            flatpickr(el, config);
        });
    }

    // Wait for DOM + Alpine to be ready
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Alpine !== 'undefined') {
            initFlatpickr();
        } else {
            document.addEventListener('alpine:init', initFlatpickr);
        }
    });
}());
</script>
@endpush

@section('content')

{{-- ─────────── BREADCRUMB (solo md+) ─────────── --}}
<section class="hidden md:block bg-white pt-5 pb-2">
    <div class="container mx-auto max-w-7xl px-5 lg:px-10">
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
@php
// Highlights para el carrusel "¿Qué vivirás?" — compartido entre mobile y desktop
$highlightsFromItinerary = [];
if (!empty($itinerary)) {
    foreach (array_slice($itinerary, 0, 4) as $k => $step) {
        $highlightsFromItinerary[] = [
            'img'   => $galleryUrls[$k] ?? ($galleryUrls[0] ?? $tour->cover_url),
            'title' => $step['title'] ?? '',
            'desc'  => $step['description'] ?? '',
            'icon'  => $step['icon'] ?? 'van',
        ];
    }
} elseif (count($galleryUrls) > 0) {
    foreach (array_slice($galleryUrls, 0, 4) as $k => $url) {
        $highlightsFromItinerary[] = [
            'img'   => $url,
            'title' => '',
            'desc'  => '',
            'icon'  => 'van',
        ];
    }
}
@endphp
<script>
    window.__lvtGallery = {!! json_encode($galleryUrls, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!};
    window.__lvtPrice   = {{ (float)$tour->price }};
</script>
<section class="bg-cream-100 pb-6 md:pb-28"
         x-data="{
             gallery: window.__lvtGallery || [],
             active: 0,
             heroTouchX: 0,
             expActive: 0,
             lightbox: false,
             showAll: false,
             adults: 1,
             children: 0,
             price: window.__lvtPrice || 0,
             get total() { return ((this.adults + this.children) * this.price).toFixed(0); },
             open(i) { this.active = (i !== undefined) ? i : this.active; this.lightbox = true; document.body.style.overflow = 'hidden'; },
             close() { this.lightbox = false; this.showAll = false; document.body.style.overflow = ''; },
             prev() { this.active = (this.active - 1 + this.gallery.length) % this.gallery.length; },
             next() { this.active = (this.active + 1) % this.gallery.length; }
         }"
         @keydown.escape.window="lightbox && close()"
         @keydown.arrow-left.window="lightbox && prev()"
         @keydown.arrow-right.window="lightbox && next()">

    {{-- ══════════════════════════════════════
         HERO MOBILE (< md) — diseño mockup exacto
    ══════════════════════════════════════ --}}
    <div class="md:hidden">
        <div class="m-hero"
             style="background: url('{{ addslashes($galleryUrls[0] ?? $tour->cover_url) }}') center/cover no-repeat;"
             :style="`background: url('${gallery[active]}') center/cover no-repeat`"
             @touchstart="heroTouchX = $event.changedTouches[0].clientX"
             @touchend="(() => { const dx = $event.changedTouches[0].clientX - heroTouchX; if (gallery.length > 1 && Math.abs(dx) > 40) { dx < 0 ? next() : prev(); } })()">
            {{-- Flechas de navegación del slider (solo si hay más de 1 imagen) --}}
            <template x-if="gallery.length > 1">
                <div>
                    <button type="button" class="m-hero-nav prev" @click.stop="prev()" aria-label="Foto anterior">‹</button>
                    <button type="button" class="m-hero-nav next" @click.stop="next()" aria-label="Foto siguiente">›</button>
                </div>
            </template>
            {{-- Badges abajo izquierda --}}
            <div class="m-hero-badges">
                <div class="m-main-badge">
                    <span class="m-ico">★</span>
                    <span>{{ $tour->badge_text ?: 'Más reservado' }}</span>
                </div>
                <div class="m-subline">
                    <span class="m-subitem safe"><span class="m-ico">🛡</span><span>Cancelación gratuita</span></span>
                    <span class="m-divider" aria-hidden="true"></span>
                    <span class="m-subitem urgent"><span class="m-ico">🔥</span><span>Últimos cupos</span></span>
                </div>
            </div>
            {{-- Contador galería abajo derecha --}}
            <button type="button" class="m-counter" @click="open(active)" aria-label="Ver galería">
                ▣ <span x-text="active + 1">1</span>/{{ $totalImgs }}
            </button>
        </div>
    </div>

    {{-- Hero desktop: ahora se renderiza dentro del grid (columna izquierda). Este div queda vacío intencionalmente. --}}

    {{-- ══════════════════════════════════════
         BLOQUE MOBILE (< md) — título, reserva, trust, features, experience, timeline, info, reviews, related, confidence
    ══════════════════════════════════════ --}}
    <div class="md:hidden m-content-wrap">

        {{-- LIGHTBOX mobile --}}
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
            <div class="relative flex-1 grid place-items-center px-4 overflow-hidden">
                @if ($totalImgs > 1)
                    <button type="button" @click="prev()" class="absolute left-2 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 transition grid place-items-center text-white z-10" aria-label="Foto anterior">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" @click="next()" class="absolute right-2 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 transition grid place-items-center text-white z-10" aria-label="Foto siguiente">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                @endif
                <img :src="gallery[active]" alt="{{ $tour->title }}"
                     class="w-auto h-auto max-w-full max-h-full object-contain rounded-xl shadow-2xl">
            </div>
        </div>

        {{-- 2. TÍTULO + RATING --}}
        <div style="padding:18px 20px 0;">
            <h1 class="m-title">{{ $titleDisplay }}</h1>
            <div class="m-rating">
                <span class="m-stars" aria-label="{{ $tourRating }} de 5 estrellas">★★★★★</span>
                <span>{{ $tourRating }}</span>
                <span>({{ $reviewsCount }} opiniones verificadas)</span>
                <span class="m-verified" aria-label="Verificado">✓</span>
            </div>

            {{-- 3. COMPACT BOOKING --}}
            <div id="seccion-reserva" class="m-compact"
                 x-data="{
                     price: window.__lvtPrice || 0,
                     get total() { return ((this.$store.booking.adults + this.$store.booking.children) * this.price).toFixed(0); }
                 }">
                <div class="m-compact-head">
                    <span>Reserva online</span>
                    <span>Cancelación gratis</span>
                </div>
                <div class="m-compact-body">
                    {{-- Fila precio --}}
                    <div class="m-price-row">
                        @if ($hasOffer)
                        <div class="m-price-box old-box">
                            <div class="m-price-label">ANTES</div>
                            <div class="m-price-value old">US${{ number_format((float)$tour->price_before, 0) }}</div>
                        </div>
                        @endif
                        <div class="m-price-box new-box {{ $hasOffer ? '' : '' }}" style="{{ !$hasOffer ? 'grid-column:1/-1' : '' }}">
                            <div class="m-price-label current">AHORA</div>
                            <div class="m-price-value new">US${{ number_format((float)$tour->price, 0) }}</div>
                            <div class="m-price-unit">USD / PERSONA</div>
                        </div>
                    </div>
                    {{-- Fecha --}}
                    <div class="m-field">
                        <span class="m-label">Fecha del tour</span>
                        <div class="m-input-row">
                            <input type="date"
                                   data-booking-date
                                   x-model="$store.booking.date"
                                   min="{{ now()->addDay()->format('Y-m-d') }}"
                                   aria-label="Fecha del tour"
                                   style="border:none;background:transparent;font-size:12px;color:#29404a;width:100%;outline:none;">
                            <span aria-hidden="true">📅</span>
                        </div>
                    </div>
                    {{-- Adultos / Niños --}}
                    <div class="m-mini-grid">
                        <div class="m-field">
                            <span class="m-label">Adultos</span>
                            <div class="m-input-row">
                                <button type="button" @click="$store.booking.adults = Math.max(1, $store.booking.adults - 1)" aria-label="Menos adultos" style="background:none;border:none;cursor:pointer;font-size:16px;color:#083b31;padding:0 4px;">−</button>
                                <span x-text="$store.booking.adults">1</span>
                                <button type="button" @click="$store.booking.adults++" aria-label="Más adultos" style="background:none;border:none;cursor:pointer;font-size:16px;color:#083b31;padding:0 4px;">+</button>
                            </div>
                        </div>
                        <div class="m-field">
                            <span class="m-label">Niños</span>
                            <div class="m-input-row">
                                <button type="button" @click="$store.booking.children = Math.max(0, $store.booking.children - 1)" aria-label="Menos niños" style="background:none;border:none;cursor:pointer;font-size:16px;color:#083b31;padding:0 4px;">−</button>
                                <span x-text="$store.booking.children">0</span>
                                <button type="button" @click="$store.booking.children++" aria-label="Más niños" style="background:none;border:none;cursor:pointer;font-size:16px;color:#083b31;padding:0 4px;">+</button>
                            </div>
                        </div>
                    </div>
                    {{-- Total --}}
                    <div class="m-total">
                        <span>Total estimado
                            <span class="m-total-sub"
                                  x-text="`${$store.booking.adults} adulto${$store.booking.adults>1?'s':''}` + ($store.booking.children>0 ? ` + ${$store.booking.children} niño${$store.booking.children>1?'s':''}` : '') + ` × US$${price}`">
                            </span>
                        </span>
                        <strong>US$<span x-text="total">{{ number_format((float)$tour->price, 0) }}</span></strong>
                    </div>
                    {{-- CTA --}}
                    <button type="submit" form="form-reservar" class="m-cta">RESERVAR &nbsp; ›</button>
                    <div class="m-foot">Pago seguro · Sin cargos extra</div>
                </div>
            </div>
        </div>

        {{-- 4. TRUST ROW + NOTICE + FEATURES --}}
        <div style="padding:0 20px 18px;">
            <div class="m-trust-row">
                <div class="m-trust">🛡️ Cancelación gratuita<small>Hasta 24h antes</small></div>
                <div class="m-trust">🔒 Pago seguro<small>y protegido</small></div>
            </div>
            <div class="m-notice">🔥 {{ $bookingsWeek }} viajeros reservaron este tour esta semana</div>
            <div class="m-features">
                <div class="m-feature"><div class="m-ico" aria-hidden="true">🚌</div><b>Recojo incluido</b><small>Desde tu hotel</small></div>
                <div class="m-feature"><div class="m-ico" aria-hidden="true">🌐</div><b>Guía bilingüe</b><small>{{ $tour->language ?: 'Español / Inglés' }}</small></div>
                <div class="m-feature"><div class="m-ico" aria-hidden="true">🛡️</div><b>Cancelación gratuita</b><small>Hasta 24h antes</small></div>
                <div class="m-feature"><div class="m-ico" aria-hidden="true">👥</div><b>Grupos pequeños</b><small>Experiencia personalizada</small></div>
            </div>
        </div>

        {{-- 5. ¿QUÉ VIVIRÁS? — experience row --}}
        @if (count($highlightsFromItinerary) > 0)
        <div style="padding:18px 20px;">
            <div class="m-h2">
                <h2>¿Qué vivirás en este tour?</h2>
                <a href="#m-itinerary">Ver más</a>
            </div>
            @php $expCount = count($highlightsFromItinerary); @endphp
            <div class="m-exp-row" x-ref="expRow"
                 @scroll.debounce.40ms="expActive = Math.round($el.scrollLeft / ($el.scrollWidth / {{ $expCount }}))">
                @foreach ($highlightsFromItinerary as $k => $hl)
                <div class="m-exp">
                    <img src="{{ $hl['img'] }}" alt="{{ $hl['title'] ?: $tour->title }}" loading="lazy" width="86" height="94">
                    @if (!empty($hl['title']))<b>{{ \Illuminate\Support\Str::limit($hl['title'], 20) }}</b>@endif
                    @if (!empty($hl['desc']))<small>{{ \Illuminate\Support\Str::limit($hl['desc'], 30) }}</small>@endif
                </div>
                @endforeach
            </div>
            <div class="m-dots">
                @for ($d = 0; $d < $expCount; $d++)
                    <span :class="{ 'active': expActive === {{ $d }} }"
                          @click="$refs.expRow.scrollTo({ left: {{ $d }} * ($refs.expRow.scrollWidth / {{ $expCount }}), behavior: 'smooth' })"
                          style="cursor:pointer;">●</span>
                @endfor
            </div>
        </div>
        @endif

        {{-- 6. ITINERARIO --}}
        @if (count($itinerary) > 0)
        <div id="m-itinerary" style="padding:18px 20px;">
            <div class="m-h2">
                <h2>Itinerario del tour</h2>
                <a href="#m-info-desc">Ver completo</a>
            </div>
            <div class="m-timeline">
                @foreach ($itinerary as $step)
                <div class="m-stop">
                    <div class="m-time">{{ $step['time'] ?? '' }}</div>
                    <div class="m-dot" aria-hidden="true"></div>
                    <div>
                        @if (!empty($step['title']))<b>{{ $step['title'] }}</b>@endif
                        @if (!empty($step['description']))<p>{{ $step['description'] }}</p>@endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- 7. INFORMACIÓN IMPORTANTE — acordeón --}}
        <div style="padding:18px 20px;">
            <h2 style="font-family:Georgia,serif;margin:0 0 12px;font-size:22px;color:#111;">Información importante</h2>
            <div class="m-info-list">
                <details id="m-info-desc" class="group">
                    <summary class="m-info-item">
                        <div class="m-info-ico" aria-hidden="true"><svg fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg></div>
                        <div>
                            <b>Descripción</b>
                            <p>{{ \Illuminate\Support\Str::limit(strip_tags($tour->{"description_$locale"} ?? $tour->description_es ?? ''), 60) }}</p>
                        </div>
                        <svg class="acc-chevron" style="width:16px;height:16px;color:#083b31;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div style="padding:0 14px 14px 66px;font-size:12px;color:#555;line-height:1.5;">
                        {!! nl2br(e($tour->description ?? $notesText)) !!}
                    </div>
                </details>
                <details class="group">
                    <summary class="m-info-item">
                        <div class="m-info-ico" aria-hidden="true"><svg fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"/></svg></div>
                        <div>
                            <b>Itinerario completo</b>
                            <p>Revisa el plan detallado del tour.</p>
                        </div>
                        <svg class="acc-chevron" style="width:16px;height:16px;color:#083b31;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div style="padding:0 14px 14px 66px;font-size:12px;color:#555;line-height:1.5;">
                        @foreach ($itinerary as $step)
                            <div style="margin-bottom:4px;">@if(!empty($step['time']))<strong>{{ $step['time'] }}</strong> — @endif{{ $step['title'] }}{{ !empty($step['description']) ? ': '.$step['description'] : '' }}</div>
                        @endforeach
                    </div>
                </details>
                <details class="group">
                    <summary class="m-info-item">
                        <div class="m-info-ico" aria-hidden="true"><svg fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                        <div>
                            <b>Servicios incluidos</b>
                            <p>Todo lo que está incluido en tu experiencia.</p>
                        </div>
                        <svg class="acc-chevron" style="width:16px;height:16px;color:#083b31;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div style="padding:0 14px 14px 66px;font-size:12px;color:#555;line-height:1.5;">
                        @foreach ($includes as $item)
                            <div style="margin-bottom:4px;">✓ {{ is_array($item) ? ($item['label'] ?? $item[0] ?? '') : $item }}</div>
                        @endforeach
                        @if (count($excludes) > 0)
                            <div style="margin-top:8px;font-weight:700;">No incluye:</div>
                            @foreach ($excludes as $item)
                                <div style="margin-bottom:4px;">✗ {{ is_array($item) ? ($item['label'] ?? $item[0] ?? '') : $item }}</div>
                            @endforeach
                        @endif
                    </div>
                </details>
                <details class="group">
                    <summary class="m-info-item">
                        <div class="m-info-ico" aria-hidden="true"><svg fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18"/></svg></div>
                        <div>
                            <b>Recomendaciones</b>
                            <p>Consejos para que disfrutes al máximo.</p>
                        </div>
                        <svg class="acc-chevron" style="width:16px;height:16px;color:#083b31;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div style="padding:0 14px 14px 66px;font-size:12px;color:#555;line-height:1.5;">
                        @foreach ($recommendationLines as $rec)
                            <div style="margin-bottom:4px;">&#9679; {{ $rec }}</div>
                        @endforeach
                    </div>
                </details>
                <details class="group">
                    <summary class="m-info-item">
                        <div class="m-info-ico" aria-hidden="true"><svg fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg></div>
                        <div>
                            <b>Notas importantes</b>
                            <p>Lo que debes saber antes de reservar.</p>
                        </div>
                        <svg class="acc-chevron" style="width:16px;height:16px;color:#083b31;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div style="padding:0 14px 14px 66px;font-size:12px;color:#555;line-height:1.5;">
                        {!! nl2br(e($notesText)) !!}
                    </div>
                </details>
            </div>
        </div>

        {{-- 8. OPINIONES / RESEÑAS (caja estilo WooCommerce, dinámica) --}}
        <div id="reviews" style="padding:18px 20px;">
            <div class="m-h2">
                <h2>Opiniones de nuestros viajeros</h2>
                @if ($tourReviews->count() > 0)
                    <span style="font-size:12px;color:#6b7077;font-weight:800;">{{ $tourReviews->count() }} {{ $tourReviews->count() === 1 ? 'valoración' : 'valoraciones' }}</span>
                @endif
            </div>
            <div class="m-review-cards">
                <div class="m-platform">
                    <div class="brand" style="color:#4285f4;">G Google</div>
                    <div class="score">4.7 <small>/5</small></div>
                    <div class="m-stars">★★★★★</div>
                    <small>22 reseñas</small>
                    <a href="#">Ver en Google →</a>
                </div>
                <div class="m-platform">
                    <div class="brand" style="color:#00a680;">● Tripadvisor</div>
                    <div class="score">4.6 <small>/5</small></div>
                    <div class="m-stars">★★★★★</div>
                    <small>8 reseñas</small>
                    <a href="#">Ver en Tripadvisor →</a>
                </div>
            </div>

            @if (session('review_status'))
                <div class="m-review-flash">{{ session('review_status') }}</div>
            @endif

            {{-- Lista de reseñas del tour --}}
            @forelse ($tourReviews as $rev)
                @php
                    $rName   = $rev->name ?: 'Viajero';
                    $rDate   = $rev->created_at ? \Carbon\Carbon::parse($rev->created_at)->translatedFormat('j \d\e F, Y') : '';
                    $rText   = $rev->{"quote_$locale"} ?: $rev->quote_es;
                    $rRating = max(1, min(5, (int) round($rev->rating)));
                @endphp
                <div class="m-comment">
                    <div style="width:38px;height:38px;border-radius:50%;background:#15474b;color:#fff;display:grid;place-items:center;font-size:14px;font-weight:700;flex-shrink:0;" aria-hidden="true">{{ mb_strtoupper(mb_substr($rName,0,1,'UTF-8'),'UTF-8') }}</div>
                    <div>
                        <b>{{ $rName }} <span class="m-verified" aria-label="Verificado">✓</span></b>
                        @if ($rDate)<span class="m-date">{{ $rDate }}</span>@endif
                        <div class="m-stars" aria-label="Valorado con {{ $rRating }} de 5">{{ str_repeat('★', $rRating) }}<span style="color:#d8dcd8;">{{ str_repeat('★', 5 - $rRating) }}</span></div>
                        @if ($rText)<p>{{ $rText }}</p>@endif
                    </div>
                </div>
            @empty
                <p class="m-reviews-empty">Sé el primero en dejar una reseña de este tour.</p>
            @endforelse

            {{-- Formulario "Añade una valoración" (crea reseña pendiente de aprobación) --}}
            <details class="m-review-form-wrap" {{ ($errors->any() || session('review_status')) ? 'open' : '' }}>
                <summary class="m-review-toggle">
                    <span aria-hidden="true">✍️</span> Añade una valoración
                    <span class="chev" aria-hidden="true">⌄</span>
                </summary>
                <form method="POST" action="{{ route('tours.review.store', ['locale' => $locale, 'slug' => $tour->slug]) }}" class="m-review-form"
                      x-data="{ rating: {{ (int) old('rating', 0) }} }">
                    @csrf
                    @if ($errors->any())
                        <div class="m-review-errors">{{ $errors->first() }}</div>
                    @endif
                    <div class="fld">
                        <label>Tu puntuación <span class="req">*</span></label>
                        <div class="m-rate-stars" role="radiogroup" aria-label="Puntuación">
                            <template x-for="n in 5" :key="n">
                                <span :class="{ 'on': n <= rating }" @click="rating = n" role="radio" :aria-checked="n === rating" :aria-label="`${n} estrellas`">★</span>
                            </template>
                        </div>
                        <input type="hidden" name="rating" :value="rating">
                    </div>
                    <div class="fld">
                        <label for="rev-comment">Tu reseña <span class="req">*</span></label>
                        <textarea id="rev-comment" name="comment" required minlength="10" maxlength="2000">{{ old('comment') }}</textarea>
                    </div>
                    <div class="fld">
                        <label for="rev-name">Nombre <span class="req">*</span></label>
                        <input id="rev-name" type="text" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name">
                    </div>
                    <div class="fld">
                        <label for="rev-email">Correo electrónico <span class="req">*</span></label>
                        <input id="rev-email" type="email" name="email" value="{{ old('email') }}" required maxlength="160" autocomplete="email">
                        <div class="m-review-hint">No se publicará. Solo para verificar tu reseña.</div>
                    </div>
                    {{-- Honeypot anti-spam --}}
                    <input class="m-review-hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <button type="submit" class="m-review-submit">Enviar reseña</button>
                </form>
            </details>
        </div>

        {{-- 9. OTROS VIAJEROS TAMBIÉN RESERVARON — rec-cards --}}
        <div style="padding:12px 20px 18px;">
            <div class="m-h2">
                <h2>Otros viajeros también reservaron</h2>
                <a href="{{ route('tours.index', ['locale' => $locale]) }}">Ver todos</a>
            </div>
            <div class="m-rec-list">
                @foreach ($sidebarTours as $rel)
                    @php
                        $relSlug    = is_object($rel) ? ($rel->slug ?? '#') : '#';
                        $relTitleRaw = is_object($rel) ? ($rel->title_es ?? $rel->title ?? 'Tour') : ($rel->title ?? 'Tour');
                        $relTitle   = (function ($raw) {
                            $stop = ['de','del','la','las','el','los','y','o','a','en','con','por','un','una'];
                            $words = preg_split('/\s+/u', mb_strtolower(trim($raw), 'UTF-8'));
                            $out = [];
                            foreach ($words as $i => $w) {
                                if ($w === '') continue;
                                $out[] = ($i > 0 && in_array($w, $stop, true)) ? $w : mb_convert_case($w, MB_CASE_TITLE, 'UTF-8');
                            }
                            return implode(' ', $out);
                        })($relTitleRaw);
                        $relPrice   = (float)(is_object($rel) ? ($rel->price ?? 0) : ($rel->price ?? 0));
                        $relBefore  = is_object($rel) ? ($rel->price_before ?? null) : ($rel->price_before ?? null);
                        $relRating  = is_object($rel) ? ($rel->rating ?? 4.8) : ($rel->rating ?? 4.8);
                        $relReviews = is_object($rel) ? ($rel->reviews_count ?? 0) : ($rel->reviews_count ?? 0);
                        $relCover   = is_object($rel) && method_exists($rel, 'getCoverUrlAttribute') ? $rel->cover_url : ($rel->cover_url ?? asset('assets/banners/banner-hero.jpg'));
                        $relDuration= is_object($rel) ? ($rel->duration ?? 'Full Day') : ($rel->duration ?? 'Full Day');
                        $relHasOffer= $relBefore && (float)$relBefore > $relPrice;
                        $relPct     = $relHasOffer ? (int) round(((float)$relBefore - $relPrice) / (float)$relBefore * 100) : 0;
                        $relHref    = $relSlug !== '#' ? route('tours.show', ['locale' => $locale, 'slug' => $relSlug]) : '#';
                        $relIncludes = is_object($rel) ? ($rel->{"includes_$locale"} ?? $rel->includes_es ?? []) : [];
                        $relBullets  = array_slice(array_filter(array_map(fn($i) => is_array($i) ? ($i['label'] ?? $i[0] ?? '') : (string)$i, $relIncludes ?: [])), 0, 2);
                    @endphp
                    <div class="m-rec-card">
                        <div class="m-rec-image-wrap">
                            <img src="{{ $relCover }}" alt="{{ $relTitle }}" loading="lazy" width="126" height="142">
                            @if ($relHasOffer)
                                <div class="m-rec-badge">-{{ $relPct }}%</div>
                            @endif
                        </div>
                        <div class="m-rec-main">
                            <h3>{{ $relTitle }}</h3>
                            <div class="m-rec-duration">{{ $relDuration }}</div>
                            @if (count($relBullets) > 0)
                            <div class="m-rec-bullets">
                                @foreach ($relBullets as $bullet)
                                    <div><span aria-hidden="true" style="color:#0a8b4d;">✓</span> {{ $bullet }}</div>
                                @endforeach
                            </div>
                            @endif
                            <div class="m-rec-rating"><span class="s">★★★★★</span>{{ $relRating }}@if($relReviews > 0) <span>({{ $relReviews }})</span>@endif</div>
                        </div>
                        <div class="m-rec-side">
                            @if ($relHasOffer)
                                <div class="offer">OFERTA</div>
                                <div class="price">
                                    <strong>US${{ number_format($relPrice, 0) }}</strong>
                                    <small>por persona</small>
                                </div>
                                <div class="disc">-{{ $relPct }}%</div>
                                <div class="before">Antes US${{ number_format((float)$relBefore, 0) }}</div>
                            @else
                                <div class="price">
                                    <strong>US${{ number_format($relPrice, 0) }}</strong>
                                    <small>por persona</small>
                                </div>
                            @endif
                            <a href="{{ $relHref }}" class="m-rec-btn">Ver tour</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- 10. RESERVA CON CONFIANZA --}}
        <div class="m-confidence">
            <h2 style="font-family:Georgia,serif;margin:0 0 12px;font-size:20px;color:#1a0d75;">Reserva con confianza</h2>
            <div class="m-confidence-grid">
                <div><span class="m-ico" aria-hidden="true">🛡️</span>Cancelación gratuita<small>Hasta 24h antes</small></div>
                <div><span class="m-ico" aria-hidden="true">🔒</span>Pago seguro y protegido</div>
                <div><span class="m-ico" aria-hidden="true">🎧</span>Atención al cliente 24/7</div>
                <div><span class="m-ico" style="color:#c76b33;" aria-hidden="true">🏛️</span>Más de 10 años de experiencia</div>
            </div>
        </div>

    </div>{{-- /md:hidden --}}

    {{-- ══════════════════════════════════════
         BLOQUE DESKTOP (md+) — grid 2 columnas
    ══════════════════════════════════════ --}}
    <div class="hidden md:block">
    {{-- Grid principal: columna izquierda (contenido) + columna derecha (reserva sticky) --}}
    <div class="container mx-auto max-w-7xl px-5 lg:px-10 pt-4 pb-20">
    <div class="d-two-col">

    {{-- ─── COLUMNA IZQUIERDA ─── --}}
    <div class="space-y-5 lg:space-y-6 min-w-0">

        {{-- ── HERO GALERÍA DESKTOP ── --}}
        <div class="relative rounded-2xl overflow-hidden h-[380px] lg:h-[500px] bg-teal-800/10">
            <img :src="gallery[active] || '{{ addslashes($galleryUrls[0] ?? $tour->cover_url) }}'"
                 src="{{ $galleryUrls[0] ?? $tour->cover_url }}"
                 alt="{{ $tour->title }}"
                 class="w-full h-full object-cover"
                 loading="eager"
                 width="900" height="500"
                 onerror="this.src='{{ asset('assets/banners/banner-hero.jpg') }}'">

            {{-- Badges hero desktop --}}
            <div class="absolute top-4 left-4 flex flex-col gap-2 z-10">
                @if ($isMostBooked)
                    <span class="inline-flex items-center gap-1.5 bg-white/95 text-teal-800 text-xs font-semibold px-3 py-1.5 rounded-full shadow-sm">
                        <svg class="w-4 h-4 text-orange-400 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        Más reservado
                    </span>
                @endif
                <span class="inline-flex items-center gap-1.5 bg-teal-800/90 backdrop-blur-sm text-white text-xs font-semibold px-3 py-1.5 rounded-full shadow-sm">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Cancelación gratuita
                </span>
                <span class="inline-flex items-center gap-1.5 bg-orange-500/90 backdrop-blur-sm text-white text-xs font-semibold px-3 py-1.5 rounded-full shadow-sm">
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.963 2.286a.75.75 0 00-1.071-.136 9.742 9.742 0 00-3.539 6.177A7.547 7.547 0 016.648 6.61a.75.75 0 00-1.152.082A9 9 0 1015.68 4.534a7.46 7.46 0 01-2.717-2.248zM15.75 14.25a3.75 3.75 0 11-7.313-1.172c.628.465 1.35.81 2.133 1a5.99 5.99 0 011.925-3.545 3.75 3.75 0 013.255 3.717z" clip-rule="evenodd"/></svg>
                    Últimos cupos
                </span>
            </div>

            {{-- Miniaturas abajo (si hay más de 1 imagen) --}}
            @if ($totalImgs > 1)
            <div class="absolute bottom-0 inset-x-0 px-4 pb-3 flex gap-2 z-10">
                @foreach (array_slice($galleryUrls, 0, 5) as $tIdx => $tUrl)
                    <button type="button"
                            @click="active = {{ $tIdx }}"
                            class="w-14 h-10 lg:w-16 lg:h-12 rounded-lg overflow-hidden ring-2 transition shrink-0 focus-visible:outline focus-visible:outline-2 focus-visible:outline-orange-400"
                            :class="active === {{ $tIdx }} ? 'ring-orange-400 opacity-100' : 'ring-transparent opacity-60 hover:opacity-90'"
                            aria-label="Foto {{ $tIdx + 1 }}">
                        <img src="{{ $tUrl }}"
                             alt=""
                             class="w-full h-full object-cover"
                             loading="lazy"
                             onerror="this.src='{{ asset('assets/banners/banner-hero.jpg') }}'">
                    </button>
                @endforeach
                @if ($totalImgs > 5)
                    <button type="button"
                            @click="open(active)"
                            class="w-14 h-10 lg:w-16 lg:h-12 rounded-lg overflow-hidden bg-black/60 text-white text-[10px] font-bold flex items-center justify-center ring-2 ring-transparent hover:ring-orange-400 transition shrink-0"
                            aria-label="Ver todas las fotos">
                        +{{ $totalImgs - 5 }}
                    </button>
                @endif
            </div>
            @endif

            {{-- Botón galería completa --}}
            <button type="button"
                    @click="open(active)"
                    class="absolute bottom-3 right-3 z-20 inline-flex items-center gap-1.5 bg-black/55 hover:bg-black/70 text-white text-xs font-semibold px-3 py-1.5 rounded-full transition-colors"
                    :class="{'hidden': {{ $totalImgs }} > 1}"
                    aria-label="Ver galería de fotos">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>
                </svg>
                <span aria-live="polite"><span x-text="active + 1">1</span> / {{ $totalImgs }}</span>
            </button>
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
             2. TÍTULO + RATING (columna izquierda)
        ══════════════════════════════════════ --}}
        <section aria-labelledby="tour-title">
            <h1 id="tour-title" class="font-display text-2xl md:text-3xl lg:text-4xl text-teal-800 leading-tight">
                {{ $titleDisplay }}
            </h1>
            <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1.5">
                <span class="inline-flex gap-0.5" aria-label="{{ $tourRating }} de 5 estrellas">
                    @for ($s = 0; $s < 5; $s++)
                        <svg class="w-4 h-4 text-orange-400 fill-current" viewBox="0 0 20 20" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    @endfor
                </span>
                <span class="font-bold text-teal-800 text-sm">{{ $tourRating }}</span>
                <span class="text-teal-800/60 text-sm">({{ $reviewsCount }} opiniones verificadas)</span>
                <span class="inline-flex items-center gap-1 bg-state-success/10 text-state-success text-[11px] font-semibold px-2 py-0.5 rounded-full">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    Verificado
                </span>
            </div>
        </section>

        {{-- ══════════════════════════════════════
             3. CONFIANZA / URGENCIA
             trust-row + notice + 4 features
        ══════════════════════════════════════ --}}
        <section aria-label="Confianza y garantías">
            {{-- Trust row --}}
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div class="flex items-center gap-2.5 bg-white ring-1 ring-teal-800/10 rounded-2xl px-3 py-3">
                    <svg class="w-5 h-5 text-teal-700 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    <div>
                        <p class="text-xs font-bold text-teal-800">Cancelación gratuita</p>
                        <p class="text-[11px] text-teal-800/55">Hasta 24h antes</p>
                    </div>
                </div>
                <div class="flex items-center gap-2.5 bg-white ring-1 ring-teal-800/10 rounded-2xl px-3 py-3">
                    <svg class="w-5 h-5 text-teal-700 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                    <div>
                        <p class="text-xs font-bold text-teal-800">Pago seguro</p>
                        <p class="text-[11px] text-teal-800/55">y protegido</p>
                    </div>
                </div>
            </div>

            {{-- Notice urgencia --}}
            <div class="inline-flex items-center gap-2 bg-orange-50 border border-orange-200 text-teal-800 text-sm font-medium px-4 py-2.5 rounded-full w-full justify-center mt-1">
                <svg class="w-4 h-4 text-orange-500 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.963 2.286a.75.75 0 00-1.071-.136 9.742 9.742 0 00-3.539 6.177A7.547 7.547 0 016.648 6.61a.75.75 0 00-1.152.082A9 9 0 1015.68 4.534a7.46 7.46 0 01-2.717-2.248zM15.75 14.25a3.75 3.75 0 11-7.313-1.172c.628.465 1.35.81 2.133 1a5.99 5.99 0 011.925-3.545 3.75 3.75 0 013.255 3.717z" clip-rule="evenodd"/></svg>
                <span><strong>{{ $bookingsWeek }}</strong> viajeros reservaron este tour esta semana</span>
            </div>

            {{-- Grid 4 features --}}
            <div class="grid grid-cols-4 gap-2 sm:gap-4 mt-4">
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
        </section>

        {{-- ══════════════════════════════════════
             4. ¿QUÉ VIVIRÁS? — CARRUSEL HIGHLIGHTS
             Deriva de $itinerary real + $galleryUrls.
             Si no hay datos reales → ocultar sección.
        ══════════════════════════════════════ --}}
        @if (count($highlightsFromItinerary) > 0)
        <section aria-labelledby="highlights-heading">
        @php $totalHighlights = count($highlightsFromItinerary); @endphp
        <div x-data="{
                activeSlide: 0,
                totalSlides: {{ $totalHighlights }},
                updateDot(el) {
                    const firstChild = el.children[0];
                    if (!firstChild) return;
                    const itemW = firstChild.offsetWidth + 12; // 12 = gap (.75rem)
                    this.activeSlide = Math.round(el.scrollLeft / itemW);
                }
             }">
            <div class="flex items-center justify-between mb-4">
                <h2 id="highlights-heading" class="font-display text-xl lg:text-2xl text-teal-800">¿Qué vivirás en este tour?</h2>
            </div>
            <div class="highlights-track"
                 x-ref="track"
                 @scroll.passive="updateDot($el)">
                @foreach ($highlightsFromItinerary as $k => $hl)
                    <div class="bg-white rounded-2xl overflow-hidden shadow-sm ring-1 ring-teal-800/5">
                        <div class="h-32 lg:h-40 overflow-hidden relative bg-teal-800/10">
                            <img src="{{ $hl['img'] }}"
                                 alt="{{ $hl['title'] ?: $tour->title }}"
                                 class="w-full h-full object-cover"
                                 loading="lazy"
                                 width="240" height="160">
                        </div>
                        @if (!empty($hl['title']))
                        <div class="p-3 pt-4">
                            <div class="w-8 h-8 rounded-full bg-white ring-1 ring-teal-800/15 grid place-items-center text-teal-700 mb-2 shadow-sm">
                                @if (($hl['icon'] ?? '') === 'boat')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                                @elseif (($hl['icon'] ?? '') === 'utensils')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8.25v-1.5m0 1.5c-1.355 0-2.697.056-4.024.166C6.845 8.51 6 9.473 6 10.608v2.513m6-4.87c1.355 0 2.697.056 4.025.166C17.155 8.51 18 9.473 18 10.608v2.513m-3-4.87v-1.5m-3 10.125v2.25m0-2.25a.375.375 0 100-.75.375.375 0 000 .75z"/></svg>
                                @elseif (($hl['icon'] ?? '') === 'palm')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c0 0-4 3-4 8h8c0-5-4-8-4-8zm0 8v10"/></svg>
                                @elseif (($hl['icon'] ?? '') === 'buggy')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909"/></svg>
                                @else
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"/></svg>
                                @endif
                            </div>
                            <p class="text-xs font-bold text-teal-800 leading-tight">{{ $hl['title'] }}</p>
                            @if (!empty($hl['desc']))
                                <p class="text-[11px] text-teal-800/60 mt-0.5 leading-tight line-clamp-2">{{ $hl['desc'] }}</p>
                            @endif
                        </div>
                        @endif
                    </div>
                @endforeach
            </div>
            {{-- Dots --}}
            @if ($totalHighlights > 1)
            <div class="flex justify-center gap-2 mt-3" aria-hidden="true">
                @for ($d = 0; $d < $totalHighlights; $d++)
                    <button type="button"
                            @click="activeSlide = {{ $d }}; (function(el, idx){ const itemW = (el.children[0]?.offsetWidth ?? 0) + 12; el.scrollTo({ left: idx * itemW, behavior: 'smooth' }); })($refs.track, {{ $d }})"
                            class="h-2 rounded-full transition-all duration-200"
                            :class="activeSlide === {{ $d }} ? 'bg-orange-500 w-4' : 'bg-teal-800/25 w-2'"
                            aria-label="Slide {{ $d + 1 }}">
                    </button>
                @endfor
            </div>
            @endif
        </div>
        </section>
        @endif

        {{-- ══════════════════════════════════════
             5. ITINERARIO DEL TOUR
             Solo se muestra si hay pasos reales.
        ══════════════════════════════════════ --}}
        @if (count($itinerary) > 0)
        <section aria-labelledby="itinerary-heading" class="pb-2">
            <div class="flex items-center justify-between mb-4">
                <h2 id="itinerary-heading" class="font-display text-xl lg:text-2xl text-teal-800">Itinerario del tour</h2>
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
        </section>
        @endif

        {{-- ══════════════════════════════════════
             6. INFORMACIÓN IMPORTANTE — ACORDEÓN
             Normal: 4 ítems | Oferta: 5 ítems
        ══════════════════════════════════════ --}}
        <section aria-labelledby="info-heading" class="bg-white rounded-2xl ring-1 ring-teal-800/10 shadow-sm overflow-hidden">
            <h2 id="info-heading" class="font-display text-base text-teal-800 px-5 pt-5 pb-3">Información importante</h2>
            <div class="divide-y divide-teal-800/8">

                {{-- Descripción --}}
                <details class="group">
                    <summary class="flex items-center gap-3 px-5 py-4 cursor-pointer select-none">
                        <span class="w-10 h-10 rounded-xl bg-teal-800 grid place-items-center shrink-0" aria-hidden="true">
                            <svg class="w-5 h-5 text-orange-300" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-teal-800">Descripción</p>
                            <p class="text-xs text-teal-800/55 truncate">{{ \Illuminate\Support\Str::limit(strip_tags($tour->{"description_$locale"} ?? $tour->description_es ?? ''), 60) }}</p>
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
        </section>

        {{-- ══════════════════════════════════════
             7. OPINIONES DE NUESTROS VIAJEROS
        ══════════════════════════════════════ --}}
        <section aria-labelledby="reviews-heading" class="bg-white rounded-2xl ring-1 ring-teal-800/10 shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 id="reviews-heading" class="font-display text-base lg:text-lg text-teal-800">Opiniones de nuestros viajeros</h2>
                <a href="#reviews-heading" class="text-xs font-semibold text-orange-500 hover:text-orange-600 transition-colors">Ver todas</a>
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

            {{-- Flash de envío --}}
            @if (session('review_status'))
                <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold px-4 py-3">{{ session('review_status') }}</div>
            @endif

            {{-- Reviews individuales del tour --}}
            @if($tourReviews->count() > 0)
            <div class="space-y-3">
                @php
                $avatarColors = ['bg-teal-700', 'bg-orange-500', 'bg-teal-600', 'bg-amber-600', 'bg-cyan-700'];
                @endphp
                @foreach ($tourReviews as $tIdx => $testimonial)
                    @php
                        $tName     = $testimonial->author_name ?? $testimonial->name ?? 'Viajero';
                        $tDate     = $testimonial->created_at ? \Carbon\Carbon::parse($testimonial->created_at)->translatedFormat('j M Y') : '';
                        $tText     = $testimonial->{"quote_$locale"} ?? $testimonial->quote_es ?? $testimonial->{"content_$locale"} ?? $testimonial->content_es ?? $testimonial->content ?? '';
                        $tRating   = (int)($testimonial->rating ?? 5);
                        $tAvatar   = $testimonial->avatar_url ?? null;
                        $tInitials = mb_strtoupper(mb_substr(trim($tName), 0, 1, 'UTF-8'), 'UTF-8');
                        if (str_contains($tName, ' ')) {
                            $parts = explode(' ', trim($tName));
                            $tInitials = mb_strtoupper(mb_substr($parts[0], 0, 1, 'UTF-8') . mb_substr(end($parts), 0, 1, 'UTF-8'), 'UTF-8');
                        }
                        $tColor = $avatarColors[$tIdx % count($avatarColors)];
                    @endphp
                    <div class="p-4 bg-cream-100 rounded-xl">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2.5">
                                @if ($tAvatar)
                                    <img src="{{ $tAvatar }}" alt="{{ $tName }}" class="w-9 h-9 rounded-full object-cover shrink-0" loading="lazy" width="36" height="36">
                                @else
                                    <div class="w-9 h-9 rounded-full {{ $tColor }} text-white grid place-items-center text-xs font-bold shrink-0" aria-hidden="true">{{ $tInitials }}</div>
                                @endif
                                <div>
                                    <p class="text-xs font-bold text-teal-800 flex items-center gap-1">
                                        {{ $tName }}
                                        <span class="inline-flex items-center gap-0.5 bg-state-success/10 text-state-success text-[9px] font-semibold px-1.5 py-0.5 rounded-full" aria-label="Verificado">
                                            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        </span>
                                    </p>
                                    <div class="flex gap-0.5 mt-0.5" aria-hidden="true">
                                        @for ($s = 0; $s < 5; $s++)
                                            <svg class="w-3 h-3 {{ $s < $tRating ? 'text-orange-400' : 'text-orange-200' }} fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        @endfor
                                    </div>
                                </div>
                            </div>
                            @if ($tDate)
                                <span class="text-[10px] text-teal-800/45 shrink-0">{{ $tDate }}</span>
                            @endif
                        </div>
                        @if ($tText)
                            <p class="text-xs text-teal-800/75 leading-relaxed">{{ $tText }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
            @else
                <p class="text-sm text-teal-800/55">Sé el primero en dejar una reseña de este tour.</p>
            @endif

            {{-- Formulario "Añade una valoración" (desktop) — crea reseña pendiente de aprobación --}}
            <div class="mt-5 pt-5 border-t border-teal-800/10" x-data="{ open: {{ ($errors->any() || session('review_status')) ? 'true' : 'false' }}, rating: {{ (int) old('rating', 0) }} }">
                <button type="button" @click="open = !open" class="flex items-center gap-2 text-sm font-bold text-teal-800">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Añade una valoración
                    <svg class="w-4 h-4 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <form x-show="open" x-cloak method="POST" action="{{ route('tours.review.store', ['locale' => $locale, 'slug' => $tour->slug]) }}" class="mt-4 space-y-3">
                    @csrf
                    @if ($errors->any())
                        <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs font-medium px-3 py-2">{{ $errors->first() }}</div>
                    @endif
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wide text-teal-800 mb-1.5">Tu puntuación *</label>
                        <div class="flex gap-1 text-2xl leading-none cursor-pointer">
                            <template x-for="n in 5" :key="n">
                                <span @click="rating = n" :class="n <= rating ? 'text-orange-400' : 'text-teal-800/20'">★</span>
                            </template>
                        </div>
                        <input type="hidden" name="rating" :value="rating">
                    </div>
                    <div>
                        <label for="d-rev-comment" class="block text-[11px] font-bold uppercase tracking-wide text-teal-800 mb-1.5">Tu reseña *</label>
                        <textarea id="d-rev-comment" name="comment" required minlength="10" maxlength="2000" rows="4" class="w-full rounded-xl border border-teal-800/20 bg-cream-100 px-3 py-2.5 text-sm text-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-700">{{ old('comment') }}</textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="d-rev-name" class="block text-[11px] font-bold uppercase tracking-wide text-teal-800 mb-1.5">Nombre *</label>
                            <input id="d-rev-name" type="text" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name" class="w-full rounded-xl border border-teal-800/20 bg-cream-100 px-3 py-2.5 text-sm text-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-700">
                        </div>
                        <div>
                            <label for="d-rev-email" class="block text-[11px] font-bold uppercase tracking-wide text-teal-800 mb-1.5">Correo *</label>
                            <input id="d-rev-email" type="email" name="email" value="{{ old('email') }}" required maxlength="160" autocomplete="email" class="w-full rounded-xl border border-teal-800/20 bg-cream-100 px-3 py-2.5 text-sm text-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-700">
                            <p class="text-[10px] text-teal-800/45 mt-1">No se publicará.</p>
                        </div>
                    </div>
                    <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="absolute -left-[9999px] w-px h-px opacity-0">
                    <button type="submit" class="inline-flex items-center justify-center gap-2 bg-teal-800 hover:bg-teal-700 text-white font-bold text-sm rounded-full py-2.5 px-6 transition-colors">Enviar reseña</button>
                </form>
            </div>
        </section>

        {{-- ══════════════════════════════════════
             8. OTROS VIAJEROS TAMBIÉN RESERVARON
        ══════════════════════════════════════ --}}
        <section aria-labelledby="related-heading" class="bg-white rounded-2xl ring-1 ring-teal-800/10 shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 id="related-heading" class="font-display text-base lg:text-lg text-teal-800">Otros viajeros también reservaron</h2>
                <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="text-xs font-semibold text-orange-500 hover:text-orange-600 transition-colors">Ver todos</a>
            </div>
            {{-- Slider tipo home: 2 cards por vista en desktop --}}
            <div class="d-related" x-data>
                <div class="d-related-track" x-ref="relTrack">
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
                        $relHref = $relSlug !== '#' ? route('tours.show', ['locale' => $locale, 'slug' => $relSlug]) : '#';
                    @endphp
                    <div class="px-1">
                        <x-tour-card
                            :title="$relTitle"
                            :slug="$relSlug"
                            :img="$relCover"
                            :imgAlt="$relTitle"
                            :before="$relHasOffer ? $relBefore : null"
                            :now="$relPrice"
                            :rating="$relRating"
                            :reviews="$relReviews"
                            :duration="$relDuration"
                        />
                    </div>
                @endforeach
                </div>
                @if ($sidebarTours->count() > 2)
                    <button type="button" class="d-related-nav prev" aria-label="Anterior"
                            @click="$refs.relTrack.scrollBy({ left: -$refs.relTrack.clientWidth * 0.9, behavior: 'smooth' })">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" class="d-related-nav next" aria-label="Siguiente"
                            @click="$refs.relTrack.scrollBy({ left: $refs.relTrack.clientWidth * 0.9, behavior: 'smooth' })">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                @endif
            </div>
        </section>

        {{-- ══════════════════════════════════════
             9. RESERVA CON CONFIANZA
        ══════════════════════════════════════ --}}
        <section aria-labelledby="confidence-heading" class="bg-white rounded-2xl ring-1 ring-teal-800/10 shadow-sm p-5">
            <h2 id="confidence-heading" class="font-display text-base font-bold text-teal-800 mb-4">Reserva con confianza</h2>
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
        </section>

    </div>{{-- /columna izquierda --}}

    {{-- ─── COLUMNA DERECHA — tarjeta de reserva sticky ─── --}}
    <aside class="hidden md:block d-sidebar-sticky" aria-label="Reserva">
        <div id="seccion-reserva-desktop" class="booking-compact-card"
             x-data="{ price: window.__lvtPrice || 0, get total() { return ((this.$store.booking.adults + this.$store.booking.children) * this.price).toFixed(0); } }">
            {{-- Cabecera --}}
            <div class="booking-compact-head">
                <span class="text-white text-xs font-semibold tracking-wide">Reserva online</span>
                <span class="inline-flex items-center gap-1 text-white/80 text-[11px]">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Cancelación gratis
                </span>
            </div>
            {{-- Cuerpo --}}
            <div class="booking-compact-body space-y-3">
                {{-- Precio --}}
                <div class="flex items-end gap-4 flex-wrap">
                    @if ($hasOffer)
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-teal-800/50 font-semibold leading-none">ANTES</p>
                            <p class="font-price text-base text-teal-800/50 line-through leading-none">US${{ number_format((float)$tour->price_before, 0) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-orange-500 font-semibold leading-none">AHORA</p>
                            <p class="font-price text-3xl font-bold text-teal-800 leading-none">US${{ number_format((float)$tour->price, 0) }}</p>
                            <p class="text-[11px] text-teal-800/55 mt-0.5">/ por persona</p>
                        </div>
                        <span class="ml-auto inline-flex items-center gap-1 bg-orange-50 border border-orange-200 text-orange-600 text-[11px] font-bold px-2 py-1 rounded-lg">
                            -{{ $discountPct }}%
                        </span>
                    @else
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-teal-800/55 font-semibold leading-none">Desde</p>
                            <p class="font-price text-3xl font-bold text-teal-800 leading-none">US${{ number_format((float)$tour->price, 0) }}</p>
                            <p class="text-[11px] text-teal-800/55 mt-0.5">/ por persona</p>
                        </div>
                    @endif
                </div>

                {{-- Campo Fecha --}}
                <div>
                    <label for="sb-date" class="block text-[11px] font-semibold text-teal-800 mb-1">Fecha del tour</label>
                    <input type="date" id="sb-date"
                           data-booking-date
                           x-model="$store.booking.date"
                           class="w-full rounded-xl border border-teal-800/20 bg-cream-100 px-3 py-2.5 text-sm text-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:border-transparent"
                           min="{{ now()->addDay()->format('Y-m-d') }}"
                           aria-label="Seleccionar fecha del tour">
                </div>

                {{-- Adultos --}}
                <div>
                    <label class="block text-[11px] font-semibold text-teal-800 mb-1">Adultos</label>
                    <div class="flex items-center gap-3 rounded-xl border border-teal-800/20 bg-cream-100 px-3 py-2">
                        <button type="button"
                                @click="$store.booking.adults = Math.max(1, $store.booking.adults - 1)"
                                class="w-7 h-7 rounded-full bg-teal-800/10 hover:bg-teal-800/20 text-teal-800 font-bold text-base grid place-items-center transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-teal-700"
                                aria-label="Reducir adultos">−</button>
                        <span class="flex-1 text-center text-sm font-bold text-teal-800 tabular-nums" x-text="$store.booking.adults">1</span>
                        <button type="button"
                                @click="$store.booking.adults++"
                                class="w-7 h-7 rounded-full bg-teal-800/10 hover:bg-teal-800/20 text-teal-800 font-bold text-base grid place-items-center transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-teal-700"
                                aria-label="Aumentar adultos">+</button>
                    </div>
                </div>

                {{-- Niños --}}
                <div>
                    <label class="block text-[11px] font-semibold text-teal-800 mb-1">Niños</label>
                    <div class="flex items-center gap-3 rounded-xl border border-teal-800/20 bg-cream-100 px-3 py-2">
                        <button type="button"
                                @click="$store.booking.children = Math.max(0, $store.booking.children - 1)"
                                class="w-7 h-7 rounded-full bg-teal-800/10 hover:bg-teal-800/20 text-teal-800 font-bold text-base grid place-items-center transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-teal-700"
                                aria-label="Reducir niños">−</button>
                        <span class="flex-1 text-center text-sm font-bold text-teal-800 tabular-nums" x-text="$store.booking.children">0</span>
                        <button type="button"
                                @click="$store.booking.children++"
                                class="w-7 h-7 rounded-full bg-teal-800/10 hover:bg-teal-800/20 text-teal-800 font-bold text-base grid place-items-center transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-teal-700"
                                aria-label="Aumentar niños">+</button>
                    </div>
                </div>

                {{-- Total estimado --}}
                <div class="flex items-center justify-between bg-cream-100 ring-1 ring-teal-800/10 rounded-xl px-4 py-3">
                    <div>
                        <p class="text-xs font-semibold text-teal-800">Total estimado</p>
                        <p class="text-[10px] text-teal-800/50 mt-0.5"
                           x-text="`${$store.booking.adults} adulto${$store.booking.adults>1?'s':''}` + ($store.booking.children>0 ? ` + ${$store.booking.children} niño${$store.booking.children>1?'s':''}` : '') + ` × US$${price}`">
                        </p>
                    </div>
                    <strong class="font-price text-2xl font-bold text-teal-800">US$<span x-text="total">{{ number_format((float)$tour->price, 0) }}</span></strong>
                </div>

                {{-- CTA Reservar --}}
                <button type="submit"
                        form="form-reservar"
                        class="w-full inline-flex items-center justify-center gap-2 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white font-bold text-sm rounded-full py-3.5 px-6 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-teal-800 uppercase tracking-wide">
                    Reservar ahora
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                </button>

                {{-- Badges de confianza --}}
                <div class="grid grid-cols-2 gap-2 pt-1 border-t border-teal-800/10">
                    <div class="flex items-center gap-1.5 text-teal-800/70">
                        <svg class="w-4 h-4 text-teal-700 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                        <span class="text-[10px] font-semibold">Cancelación gratuita</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-teal-800/70">
                        <svg class="w-4 h-4 text-teal-700 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                        <span class="text-[10px] font-semibold">Pago seguro</span>
                    </div>
                </div>

                {{-- Pie --}}
                <p class="text-center text-[11px] text-teal-800/45">Sin cargos extra · Confirmación inmediata</p>
            </div>
        </div>
    </aside>{{-- /columna derecha --}}

    </div>{{-- /md:grid --}}
    </div>{{-- /container --}}
    </div>{{-- /hidden md:block wrapper --}}
</section>

{{-- Form de reserva — compartido por mobile y desktop --}}
<form id="form-reservar"
      method="POST"
      action="{{ route('cart.store', ['locale' => $locale]) }}"
      class="hidden" x-data>
    @csrf
    <input type="hidden" name="tour_id" value="{{ $tour->id }}">
    {{-- Campos canónicos: única fuente de verdad = $store.booking (sincronizado por mobile y desktop) --}}
    <input type="hidden" name="travel_date" :value="$store.booking.date">
    <input type="hidden" name="adults" :value="$store.booking.adults">
    <input type="hidden" name="children" :value="$store.booking.children">
</form>

{{-- ─────────── BARRA FLOTANTE BOTTOM mobile — diseño mockup exacto ─────────── --}}
{{-- Banner flotante mobile retirado: dentro de la propia interna el CTA "Ver tour" no aplica
     (el usuario ya está en el tour). La reserva se hace en la card "Reserva online" de arriba. --}}

{{-- ─────────── STICKY FOOTER desktop (md+) ─────────── --}}
<div class="hidden fixed bottom-0 inset-x-0 z-40 bg-white shadow-2xl ring-1 ring-teal-800/10 sticky-bar">
    <div class="container mx-auto px-4 sm:px-5">
        <div class="flex items-center justify-between py-3 gap-4">
            <div class="shrink-0">
                @if ($hasOffer)
                    <p class="text-[9px] text-teal-800/50 line-through font-price leading-none">US${{ number_format((float)$tour->price_before, 0) }}</p>
                @else
                    <p class="text-[10px] uppercase tracking-wider text-teal-800/55 font-semibold leading-none">Desde</p>
                @endif
                <p class="font-price text-2xl font-bold text-teal-800 leading-tight">US${{ number_format((float)$tour->price, 0) }}</p>
                <p class="text-[10px] text-teal-800/45 leading-none">por persona</p>
            </div>
            <div class="flex items-center gap-2 text-teal-800/70 flex-1 justify-center">
                <svg class="w-5 h-5 text-teal-700 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                <div>
                    <p class="text-xs font-semibold text-teal-800">Cancelación gratuita</p>
                    <p class="text-[11px] text-teal-800/50">Hasta 24h antes</p>
                </div>
            </div>
            <button type="submit"
                    form="form-reservar"
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
