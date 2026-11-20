<?php

namespace App\Support;

use App\Models\Region;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;

/**
 * Secciones del home FINAL (diseño aprobado 20-nov): "Elige tu experiencia",
 * "Explora Perú por destinos", banda de confianza y reseñas con filtros.
 *
 * Misma regla que HomeSections: todo dato es REAL (BD/admin); null o lista
 * vacía = la vista OCULTA la sección o el ítem. Nunca valores de relleno.
 * Contrato: contrato-datos.md, sección "FINAL HOME".
 */
class HomeFinalSections
{
    /** Máximo de tours en "Elige tu experiencia". */
    public const PICK_MAX = 4;

    /** Máximo de destinos y de enlaces a tours por destino. */
    public const DESTINATIONS_MAX = 3;

    public const DESTINATION_TOURS_MAX = 3;

    /** Máximo de fotos por reseña. */
    public const REVIEW_PHOTOS_MAX = 4;

    public const PLATFORMS = ['google', 'tripadvisor', 'viator', 'web'];

    // ─── "Elige tu experiencia" ──────────────────────────────────────────

    /**
     * Tours destacados (is_featured, publicados), en el orden manual de
     * "featured_order" (NULL al final) y luego `order`; máx. 4. No se
     * completa con tours no destacados: sin destacados = null (ocultar).
     *
     * @return array<int, array<string, mixed>>|null
     */
    public static function pickTours(?string $locale = null): ?array
    {
        $locale = self::locale($locale);

        $tours = Tour::published()->featured()
            ->with(['region', 'category'])
            ->orderByRaw('featured_order IS NULL')
            ->orderBy('featured_order')
            ->ordered()
            ->limit(self::PICK_MAX)
            ->get();

        if ($tours->isEmpty()) {
            return null;
        }

        return $tours->map(fn (Tour $t) => self::pickCard($t, $locale))->all();
    }

    /**
     * @return array{
     *   id: int, region: ?string, region_slug: ?string, title: string, url: string,
     *   dates_url: string, dates_url_desktop: string, image: string, badge: ?string,
     *   rating: ?array{rating: float, count: int}, chips: array<int, array{type: string, label: string}>,
     *   price: float, currency: string, price_before: ?float, discount_percent: ?int
     * }
     */
    public static function pickCard(Tour $t, string $locale): array
    {
        $url = HomeSections::tourUrl($t, $locale);
        $badge = trim((string) $t->badge_text);

        $chips = [];
        if ($label = TourListing::durationLabel($t->duration, $locale)) {
            $chips[] = ['type' => 'duration', 'label' => $label];
        }
        if ($label = $t->languagesLabel($locale)) {
            $chips[] = ['type' => 'language', 'label' => $label];
        }
        if ($t->pickup_included) {
            $chips[] = ['type' => 'pickup', 'label' => __('ui.chip_pickup', [], $locale)];
        }
        if ($t->free_cancellation) {
            $chips[] = ['type' => 'cancellation', 'label' => __('ui.chip_free_cancellation', [], $locale)];
        }
        if ($modality = TourListing::modality($t->group_type, $locale)) {
            $chips[] = ['type' => 'group', 'label' => $modality['label']];
        }

        return [
            'id' => $t->getKey(),
            'region' => $t->region ? (string) ($t->region->{"name_{$locale}"} ?: $t->region->name_es) : null,
            'region_slug' => $t->region ? (string) $t->region->slug : null,
            'title' => $t->title,
            'url' => $url,
            // En la ficha hay dos selectores de fecha (móvil y escritorio) con id distinto.
            'dates_url' => $url.'#seccion-reserva',
            'dates_url_desktop' => $url.'#seccion-reserva-desktop',
            'image' => $t->cover_url,
            'badge' => $badge !== '' ? $badge : null,
            'rating' => $t->reviewSummary(),
            'chips' => $chips,
            'price' => (float) $t->price,
            'currency' => (string) ($t->currency ?: 'USD'),
            'price_before' => $t->discount_percent !== null ? (float) $t->price_before : null,
            'discount_percent' => $t->discount_percent,
        ];
    }

    // ─── "Explora Perú por destinos" ─────────────────────────────────────

    /**
     * Regiones activas con >= 1 tour publicado, por Region.order; la primera
     * es la grande (`featured` = true). Máx. 3. Lista vacía = ocultar.
     *
     * @return array<int, array{
     *   slug: string, name: string, eyebrow: ?string, description: ?string, image: ?string,
     *   url: string, tours_count: int, featured: bool, tours: array<int, array{title: string, url: string}>
     * }>
     */
    public static function destinations(?string $locale = null): array
    {
        $locale = self::locale($locale);

        $regions = Region::active()->orderBy('order')->orderBy('id')
            ->with(['tours' => fn ($q) => $q->published()->ordered()])
            ->get()
            ->filter(fn (Region $r) => $r->tours->isNotEmpty() && (string) $r->slug !== '')
            ->take(self::DESTINATIONS_MAX)
            ->values();

        return $regions->map(function (Region $r, int $i) use ($locale): array {
            $text = static function (string $field) use ($r, $locale): ?string {
                $v = trim((string) ($r->{"{$field}_{$locale}"} ?: $r->{"{$field}_es"}));

                return $v !== '' ? $v : null;
            };

            return [
                'slug' => (string) $r->slug,
                'name' => (string) ($r->{"name_{$locale}"} ?: $r->name_es),
                'eyebrow' => $text('eyebrow'),
                'description' => $text('description'),
                'image' => ImagePath::url($r->hero_image),
                'url' => route('tours.category', ['locale' => $locale, 'categoria' => $r->slug]),
                'tours_count' => $r->tours->count(),
                'featured' => $i === 0,
                'tours' => $r->tours->take(self::DESTINATION_TOURS_MAX)->map(fn (Tour $t) => [
                    'title' => $t->title,
                    'url' => HomeSections::tourUrl($t, $locale),
                ])->all(),
            ];
        })->all();
    }

    // ─── Banda de confianza ──────────────────────────────────────────────

    /**
     * Hasta 4 ítems en el orden del diseño: cancelación, años, viajeros,
     * valoración. Solo los que el admin cargó. Null si no hay ninguno.
     *
     * @return array{items: array<int, array{key: string, value: string, label: ?string, source: ?string}>}|null
     */
    public static function trust(?string $locale = null): ?array
    {
        $locale = self::locale($locale);
        $stats = HomeSections::stats($locale);
        $items = [];

        if ($c = self::cancellation($locale)) {
            $items[] = ['key' => 'cancellation', 'value' => $c['title'], 'label' => $c['text'], 'source' => null];
        }
        foreach (['years', 'travelers', 'rating'] as $key) {
            if ($stats[$key]['value'] !== null) {
                $items[] = [
                    'key' => $key,
                    'value' => $stats[$key]['value'],
                    'label' => $stats[$key]['label'],
                    'source' => $stats[$key]['source'] ?? null,
                ];
            }
        }

        return $items === [] ? null : ['items' => $items];
    }

    /**
     * Cancelación para la banda. Orden: ajustes cortos por idioma
     * (`home_trust_cancel_title_*` / `home_trust_cancel_text_*`) y, si no hay,
     * el "Resumen de cancelación" de Datos legales (recortado). Nada cargado = null.
     *
     * @return array{title: string, text: ?string}|null
     */
    public static function cancellation(?string $locale = null): ?array
    {
        $locale = self::locale($locale);
        $title = Setting::getLocalized('home_trust_cancel_title', $locale);
        $text = Setting::getLocalized('home_trust_cancel_text', $locale);

        if ($title !== null) {
            return ['title' => $title, 'text' => $text];
        }
        if ($text !== null) {
            return ['title' => __('ui.trust_cancellation_generic', [], $locale), 'text' => $text];
        }

        $summary = Setting::getLocalized('legal_cancellation_summary', $locale);

        return $summary !== null
            ? ['title' => __('ui.trust_cancellation_generic', [], $locale), 'text' => Str::limit(preg_replace('/\s+/u', ' ', $summary) ?? $summary, 90)]
            : null;
    }

    // ─── Reseñas: tarjetas + filtros ─────────────────────────────────────

    /** google | tripadvisor | viator | web (cualquier otro origen = web). */
    public static function platformKey(?string $source): string
    {
        $s = strtolower(trim((string) $source));

        return match (true) {
            str_contains($s, 'google') => 'google',
            str_contains($s, 'tripadvisor') => 'tripadvisor',
            str_contains($s, 'viator') => 'viator',
            default => 'web',
        };
    }

    public static function platformLabel(string $key, ?string $locale = null): string
    {
        return match ($key) {
            'google' => 'Google',
            'tripadvisor' => 'Tripadvisor',
            'viator' => 'Viator',
            default => __('ui.review_platform_web', [], self::locale($locale)),
        };
    }

    /**
     * Tarjetas de reseña normalizadas + datos de los filtros, calculados SOLO
     * sobre las tarjetas que el home muestra (así ningún filtro devuelve vacío).
     * Acepta modelos Testimonial (CMS) y objetos de las APIs (Google/Tripadvisor).
     * Los Testimonial deben venir ya filtrados con published().
     *
     * @param  iterable<int, mixed>  $items
     * @return array{
     *   cards: array<int, array<string, mixed>>,
     *   platforms: array<int, array{key: string, label: string, count: int}>,
     *   destinations: array<int, array{slug: string, name: string, count: int}>,
     *   photos_count: int,
     *   show_platform_filter: bool, show_destination_filter: bool, show_photos_filter: bool
     * }|null  null = 0 tarjetas
     */
    public static function reviews(iterable $items, ?string $locale = null): ?array
    {
        $locale = self::locale($locale);
        $items = collect($items)->values();

        if ($items->isEmpty()) {
            return null;
        }

        $models = new EloquentCollection($items->filter(fn ($i) => $i instanceof Testimonial)->all());
        $models->loadMissing('tour.region');
        $regions = Region::active()->get()->keyBy('id');

        $cards = $items->map(function ($r) use ($locale, $regions): array {
            $isModel = $r instanceof Testimonial;
            $tour = $isModel ? $r->tour : null;
            $tour = $tour && $tour->is_published ? $tour : null;
            $region = $tour && $tour->region_id ? $regions->get($tour->region_id) : null;
            $photos = $isModel ? array_slice($r->photo_urls, 0, self::REVIEW_PHOTOS_MAX) : [];
            $platform = self::platformKey($isModel ? $r->source : data_get($r, 'source'));
            $avatar = data_get($r, 'avatar');
            $country = trim((string) data_get($r, 'country'));
            $title = $isModel ? $r->title : null;

            return [
                'id' => $isModel ? $r->getKey() : null,
                'name' => (string) data_get($r, 'name'),
                'country' => $country !== '' ? $country : null,
                'rating' => (float) data_get($r, 'rating', 0),
                'title' => $title !== null && trim($title) !== '' ? trim($title) : null,
                'quote' => (string) ($isModel ? $r->quote : data_get($r, 'quote')),
                'date' => $isModel ? $r->display_date : null,
                'traveler_type' => $isModel && $r->traveler_type ? (string) $r->traveler_type : null,
                'tour' => $tour ? ['title' => $tour->title, 'url' => HomeSections::tourUrl($tour, $locale)] : null,
                'platform' => $platform,
                'platform_label' => self::platformLabel($platform, $locale),
                'destination' => $region ? (string) $region->slug : null,
                'photos' => $photos,
                'has_photos' => $photos !== [],
                'avatar_url' => is_string($avatar) && trim($avatar) !== '' ? ImagePath::url(trim($avatar)) : null,
            ];
        });

        $platforms = [];
        foreach (self::PLATFORMS as $key) {
            $count = $cards->where('platform', $key)->count();
            if ($count > 0) {
                $platforms[] = ['key' => $key, 'label' => self::platformLabel($key, $locale), 'count' => $count];
            }
        }

        $destinations = [];
        foreach ($regions->sortBy('order') as $region) {
            $count = $cards->where('destination', (string) $region->slug)->count();
            if ($count > 0) {
                $destinations[] = [
                    'slug' => (string) $region->slug,
                    'name' => (string) ($region->{"name_{$locale}"} ?: $region->name_es),
                    'count' => $count,
                ];
            }
        }

        $photosCount = $cards->where('has_photos', true)->count();

        return [
            'cards' => $cards->all(),
            'platforms' => $platforms,
            'destinations' => $destinations,
            'photos_count' => $photosCount,
            'show_platform_filter' => count($platforms) >= 2,
            'show_destination_filter' => count($destinations) >= 2,
            'show_photos_filter' => $photosCount > 0,
        ];
    }

    private static function locale(?string $locale): string
    {
        $locale ??= app()->getLocale();

        return in_array($locale, ['es', 'en', 'pt'], true) ? $locale : 'es';
    }
}
