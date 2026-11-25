<?php

namespace App\Support;

use App\Models\BlockedDate;
use App\Models\Setting;
use App\Models\Tour;
use Illuminate\Support\Str;

/**
 * Resumen PÚBLICO de un tour para la ventana ("sheet") del home:
 * GET /{locale}/api/tours/{slug}/sheet. Solo campos que ya se muestran en la
 * ficha pública; nada interno (costos, SEO, bookings, correos). Todo texto
 * sale como texto plano (el front lo pinta con textContent) y las URLs las
 * arma el servidor (nunca de entrada del usuario).
 *
 * Contrato: contrato-datos.md, sección "HOME TAL CUAL (21-nov)".
 */
class TourSheet
{
    public const GALLERY_MAX = 8;

    public const DESCRIPTION_MAX = 320;

    public const ADULTS_MAX = 20;

    /** @return array<string, mixed> */
    public static function build(Tour $tour, string $locale): array
    {
        $tour->loadMissing(['region', 'category']);

        $title = (string) ($tour->localized('title', $locale) ?: $tour->title_es);
        $slug = $tour->slugFor($locale);
        $pageUrl = route('tours.show', ['locale' => $locale, 'slug' => $slug]);
        $region = $tour->region;
        $regionName = $region ? (string) ($region->{"name_{$locale}"} ?: $region->name_es) : null;
        $modality = TourListing::modality($tour->group_type, $locale);
        $private = ($modality['slug'] ?? null) === 'private';
        $discount = $tour->discount_percent;
        $pickupIncluded = ! TourFlags::has('pickup_included') || (bool) $tour->pickup_included;

        $subtitle = self::plain($tour->localized('subtitle', $locale));
        $description = self::plain($tour->localized('description', $locale));

        return [
            'id' => $tour->getKey(),
            'slug' => $slug,
            'title' => $title,
            'card_title' => $tour->cardTitle($locale) ?? $title,
            'subtitle' => $subtitle !== '' ? $subtitle : null,
            'summary' => $description !== '' ? Str::limit($description, self::DESCRIPTION_MAX) : null,
            'region' => $region ? ['slug' => (string) $region->slug, 'name' => $regionName] : null,
            'location' => $tour->cardLocation($locale) ?? $regionName,
            'category' => $tour->category ? (string) ($tour->category->{"name_{$locale}"} ?: $tour->category->name_es) : null,
            'image' => $tour->cover_url,
            'gallery' => self::gallery($tour),
            'rating' => $tour->reviewSummary(),
            'duration' => TourListing::durationLabel($tour->duration, $locale),
            'language' => $tour->languagesLabel($locale),
            'modality' => $modality['label'] ?? null,
            // Hora de salida propia del tour ("HH:MM", hora de Lima). Sin hora cargada = null (no se inventa la de por defecto).
            'departure_time' => BookingCalendar::hasOwnDepartureTime($tour) ? BookingCalendar::departureTime($tour) : null,
            'includes' => self::lines($tour->localized('includes', $locale)),
            'excludes' => self::lines($tour->localized('excludes', $locale)),
            'pickup' => [
                'included' => $pickupIncluded,
                'label' => $pickupIncluded ? __('ui.chip_pickup', [], $locale) : null,
                'zones' => $pickupIncluded ? self::pickupZones() : [],
            ],
            'price' => [
                'amount' => (float) $tour->price,
                'currency' => (string) ($tour->currency ?: 'USD'),
                'before' => $discount !== null ? (float) $tour->price_before : null,
                'discount_percent' => $discount,
                'unit' => $private ? 'group' : 'pp',
                'kind' => $discount !== null ? 'now' : ($private ? 'private' : 'regular'),
            ],
            'urls' => [
                'page' => $pageUrl,
                'dates' => $pageUrl.'#seccion-reserva',
                'dates_desktop' => $pageUrl.'#seccion-reserva-desktop',
                'whatsapp' => self::whatsappUrl(__('ui.sheet_whatsapp_text', ['tour' => $title], $locale)),
            ],
            // Flujo "Reservar" desde la ventana: elegir fecha + adultos -> POST cart.store -> ir a cart_url.
            'reserve' => self::reserve($tour, $locale),
        ];
    }

    /**
     * Datos para reservar sin salir del home. El front envía un POST con
     * `X-CSRF-TOKEN` (meta csrf-token de la página) y `Accept: application/json`
     * a `url` con `fields`; si responde success=true navega a `cart_url`.
     * 422 = fecha/pasajeros inválidos (errors por campo); no hay cobro aquí.
     *
     * @return array<string, mixed>
     */
    public static function reserve(Tour $tour, string $locale): array
    {
        return [
            'url' => route('cart.store', ['locale' => $locale]),
            'method' => 'POST',
            'cart_url' => route('cart.index', ['locale' => $locale]),
            'fields' => ['tour_id' => $tour->getKey(), 'travel_date' => null, 'departure_slot' => null, 'adults' => 2, 'children' => 0],
            // Fecha mínima, horarios (hoy solo los que cumplen el margen) y mismo día: todo calculado en servidor (hora de Lima).
            'availability' => BookingCalendar::availability($tour),
            'min_date' => BookingCalendar::earliestDate($tour),
            'max_date' => BookingCalendar::latestDate(),
            'blocked_dates' => BlockedDate::blockedDatesFor($tour->getKey()),
            'blocked_weekdays' => BlockedDate::blockedWeekdaysFor($tour->getKey()),
            'adults_min' => 1,
            'adults_max' => self::ADULTS_MAX,
        ];
    }

    /**
     * wa.me con el WhatsApp OFICIAL de Ajustes (whatsapp, y si falta el
     * teléfono de contacto) y el mensaje prellenado. Null si no hay número.
     */
    public static function whatsappUrl(string $message): ?string
    {
        $digits = preg_replace('/\D/', '', (string) (Setting::get('whatsapp') ?: LegalInfo::phone()));

        if ($digits === null || $digits === '') {
            return null;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }

    /** @return array<int, array{src: string, thumb: string}> */
    private static function gallery(Tour $tour): array
    {
        $paths = array_values(array_filter((array) ($tour->gallery ?? []), 'is_string'));
        $out = [];

        foreach (array_slice($paths, 0, self::GALLERY_MAX) as $path) {
            $full = ImageVariants::responsive($path, 960);
            if ($full) {
                $out[] = ['src' => $full, 'thumb' => ImageVariants::responsive($path, 480) ?: $full];
            }
        }

        if ($out === []) {
            $cover = ImageVariants::responsive((string) $tour->cover_image, 960) ?: $tour->cover_url;
            $out[] = ['src' => $cover, 'thumb' => ImageVariants::responsive((string) $tour->cover_image, 480) ?: $cover];
        }

        return $out;
    }

    /**
     * Zonas de recojo (etiquetas) cuando el admin las activó en Ajustes.
     *
     * @return array<int, string>
     */
    private static function pickupZones(): array
    {
        if (! (bool) Setting::get('pickup_enabled', false)) {
            return [];
        }

        $raw = Setting::get('pickup_zones');
        $zones = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);

        return array_values(array_filter(array_map(
            static fn ($z) => is_array($z) && is_string($z['label'] ?? null) ? trim($z['label']) : '',
            $zones
        ), static fn (string $l) => $l !== ''));
    }

    /**
     * Lista de textos planos desde un campo json ("incluye"/"no incluye"):
     * acepta strings o filas {item|text|label}.
     *
     * @return array<int, string>
     */
    private static function lines(mixed $value): array
    {
        $out = [];

        foreach (is_array($value) ? $value : [] as $row) {
            $text = is_array($row) ? ($row['item'] ?? $row['text'] ?? $row['label'] ?? null) : $row;
            $text = is_string($text) ? self::plain($text) : '';
            if ($text !== '') {
                $out[] = $text;
            }
        }

        return $out;
    }

    private static function plain(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
