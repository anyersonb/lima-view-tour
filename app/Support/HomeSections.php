<?php

namespace App\Support;

use App\Models\Offer;
use App\Models\Region;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\Tour;

/**
 * Datos REALES de las secciones nuevas del home. Cada método devuelve null
 * (o un array con valores null) cuando el cliente aún no cargó el dato en el
 * admin: la vista debe OCULTAR esa sección/ítem, nunca pintar un valor de
 * relleno. Contrato completo en contrato-datos.md.
 */
class HomeSections
{
    /** Máximo de hitos del tour estrella. */
    public const STAR_MAX_MILESTONES = 4;

    // ─── 2. Tour estrella ────────────────────────────────────────────────

    /**
     * @return array{
     *   tour: Tour, title: string, lead: ?string, milestones: array<int,string>,
     *   url: string, image: string, price: float, price_before: ?float,
     *   discount_percent: ?int, currency: string, duration: ?string,
     *   rating: ?array{rating: float, count: int}
     * }|null
     */
    public static function star(?string $locale = null): ?array
    {
        $locale ??= app()->getLocale();
        $tour = self::publishedTour(Setting::get('home_star_tour_id'));

        if (! $tour) {
            return null;
        }

        return [
            'tour' => $tour,
            // Titular propio (por idioma, cae a ES); sin él, el título real del tour.
            'title' => Setting::getLocalized('home_star_title', $locale) ?? $tour->title,
            'lead' => Setting::getLocalized('home_star_lead', $locale),
            'milestones' => self::milestones($locale),
            'url' => self::tourUrl($tour, $locale),
            'image' => $tour->cover_url,
            'price' => (float) $tour->price,
            'price_before' => $tour->discount_percent !== null ? (float) $tour->price_before : null,
            'discount_percent' => $tour->discount_percent,
            'currency' => (string) ($tour->currency ?: 'USD'),
            'duration' => TourListing::durationLabel($tour->duration, $locale),
            'rating' => $tour->reviewSummary(),
        ];
    }

    /** Hitos del idioma (cae a ES si ese idioma no tiene ninguno), máx. 4. */
    private static function milestones(string $locale): array
    {
        foreach (array_unique([$locale, 'es']) as $l) {
            $texts = [];
            foreach (self::decodeList(Setting::get("home_star_milestones_{$l}")) as $item) {
                $text = is_array($item) ? ($item['text'] ?? null) : $item;
                if (is_string($text) && trim($text) !== '') {
                    $texts[] = trim($text);
                }
            }
            if ($texts !== []) {
                return array_slice($texts, 0, self::STAR_MAX_MILESTONES);
            }
        }

        return [];
    }

    // ─── 3. Comparador "¿X o Y?" ─────────────────────────────────────────

    /**
     * @return array{intro: ?string, tours: array<int, array<string, mixed>>}|null  siempre 2 tours
     */
    public static function compare(?string $locale = null): ?array
    {
        $locale ??= app()->getLocale();
        $a = self::publishedTour(Setting::get('home_compare_tour_a'));
        $b = self::publishedTour(Setting::get('home_compare_tour_b'));

        if (! $a || ! $b || $a->getKey() === $b->getKey()) {
            return null;
        }

        return [
            'intro' => Setting::getLocalized('home_compare_intro', $locale),
            'tours' => [self::compareCard($a, $locale), self::compareCard($b, $locale)],
        ];
    }

    private static function compareCard(Tour $t, string $locale): array
    {
        $t->loadMissing(['category', 'region']);

        return [
            'id' => $t->getKey(),
            'title' => $t->title,
            'url' => self::tourUrl($t, $locale),
            'image' => $t->cover_url,
            'price' => (float) $t->price,
            'price_before' => $t->discount_percent !== null ? (float) $t->price_before : null,
            'discount_percent' => $t->discount_percent,
            'currency' => (string) ($t->currency ?: 'USD'),
            'duration' => TourListing::durationLabel($t->duration, $locale),
            'category' => $t->category ? (string) ($t->category->{"name_{$locale}"} ?: $t->category->name_es) : null,
            'region' => $t->region ? (string) ($t->region->{"name_{$locale}"} ?: $t->region->name_es) : null,
            'pickup' => (bool) $t->pickup_included,
            'free_cancellation' => (bool) $t->free_cancellation,
            'rating' => $t->reviewSummary(),
        ];
    }

    // ─── 4. Cifras de confianza ──────────────────────────────────────────

    /**
     * Valores TAL CUAL están en Ajustes. value = null => ocultar ese ítem.
     * `label` siempre trae texto (el del admin o el genérico traducido).
     *
     * @return array{
     *   rating: array{value: ?string, label: string, source: ?string},
     *   travelers: array{value: ?string, label: string},
     *   years: array{value: ?string, label: string},
     *   tours: array{value: ?string, label: string},
     *   any: bool
     * }
     */
    public static function stats(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $val = static function (string $key): ?string {
            $v = Setting::get($key);

            return (is_string($v) || is_numeric($v)) && trim((string) $v) !== '' ? trim((string) $v) : null;
        };
        $label = static fn (string $key, string $default): string => $val($key) ?? __($default, [], $locale);

        $stats = [
            'rating' => [
                'value' => $val('stats_rating'),
                'label' => $label('home_stat_rating_label', 'ui.home_stat_rating'),
                'source' => Setting::getLocalized('stats_rating_source', $locale),
            ],
            'travelers' => [
                'value' => $val('home_stat_travelers'),
                'label' => $label('home_stat_travelers_label', 'ui.home_stat_travelers'),
            ],
            'years' => [
                'value' => $val('stats_years'),
                'label' => $label('home_stat_years_label', 'ui.home_stat_years'),
            ],
            'tours' => [
                'value' => $val('stats_tours'),
                'label' => $label('home_stat_tours_label', 'ui.home_stat_tours'),
            ],
        ];

        $stats['any'] = collect($stats)->contains(fn ($s) => is_array($s) && $s['value'] !== null);

        return $stats;
    }

    // ─── 5. Reseñas: distribución + filtro por destino ───────────────────

    /**
     * Agregados de TODAS las reseñas visibles (Testimonial::published():
     * status=approved Y is_active) — no solo las destacadas. Devuelve null con
     * 0 reseñas: la vista debe ocultar el bloque de distribución.
     *
     * @return array{
     *   total: int, average: float,
     *   distribution: array<int, array{count: int, percent: int}>,
     *   destinations: array<int, array{slug: string, name: string, count: int, average: float,
     *                                  distribution: array<int, array{count: int, percent: int}>}>,
     *   destination_by_testimonial: array<int, string>
     * }|null
     */
    public static function reviewStats(?string $locale = null): ?array
    {
        $locale ??= app()->getLocale();
        $rows = Testimonial::published()->with('tour:id,region_id')->get(['id', 'tour_id', 'rating']);

        if ($rows->isEmpty()) {
            return null;
        }

        $regions = Region::active()->get()->keyBy('id');
        $byRegion = [];
        $destinationByTestimonial = [];

        foreach ($rows as $r) {
            $region = $r->tour?->region_id ? $regions->get($r->tour->region_id) : null;
            if ($region) {
                $byRegion[$region->id][] = (float) $r->rating;
                $destinationByTestimonial[$r->id] = (string) $region->slug;
            }
        }

        $destinations = [];
        foreach ($regions->sortBy('order') as $region) {
            $ratings = $byRegion[$region->id] ?? [];
            if ($ratings === []) {
                continue;
            }
            $destinations[] = [
                'slug' => (string) $region->slug,
                'name' => (string) ($region->{"name_{$locale}"} ?: $region->name_es),
                'count' => count($ratings),
                'average' => round(array_sum($ratings) / count($ratings), 1),
                'distribution' => self::distribution($ratings),
            ];
        }

        $all = $rows->map(fn ($r) => (float) $r->rating)->all();

        return [
            'total' => count($all),
            'average' => round(array_sum($all) / count($all), 1),
            'distribution' => self::distribution($all),
            'destinations' => $destinations,
            'destination_by_testimonial' => $destinationByTestimonial,
        ];
    }

    /**
     * Conteo/porcentaje por estrellas 5..1 (misma regla que Tour::reviewStats:
     * ROUND(rating) y porcentajes con floor()).
     *
     * @param  array<int, float>  $ratings
     * @return array<int, array{count: int, percent: int}>
     */
    public static function distribution(array $ratings): array
    {
        $counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($ratings as $rating) {
            $counts[(int) max(1, min(5, round($rating)))]++;
        }
        $total = max(1, count($ratings));

        $out = [];
        foreach ($counts as $star => $count) {
            $out[$star] = ['count' => $count, 'percent' => (int) floor(($count / $total) * 100)];
        }

        return $out;
    }

    // ─── 6. Banda promo ──────────────────────────────────────────────────

    /**
     * Primera oferta activa y vigente (is_active y valid_until null o futura;
     * si apunta a un tour, ese tour debe estar publicado). Null => sin banda.
     *
     * @return array{
     *   id: int, title: string, description: ?string, cta_label: ?string,
     *   cta_url: ?string, image: ?string, price: ?float,
     *   valid_until: ?\Illuminate\Support\Carbon, tour_id: ?int
     * }|null
     */
    public static function promo(?string $locale = null): ?array
    {
        $locale ??= app()->getLocale();

        $offers = Offer::active()->with('tour')->orderBy('order')->orderBy('id')->get();

        foreach ($offers as $o) {
            if ($o->tour_id && (! $o->tour || ! $o->tour->is_published)) {
                continue;
            }

            $title = trim((string) ($o->{"title_{$locale}"} ?: $o->title_es));
            if ($title === '') {
                continue;
            }

            $description = trim((string) ($o->{"description_{$locale}"} ?: $o->description_es));
            $cta = trim((string) ($o->{"cta_label_{$locale}"} ?: $o->cta_label_es));

            return [
                'id' => $o->id,
                'title' => $title,
                'description' => $description !== '' ? $description : null,
                'cta_label' => $cta !== '' ? $cta : null,
                'cta_url' => self::safeUrl($o->cta_url) ?? ($o->tour ? self::tourUrl($o->tour, $locale) : null),
                'image' => ImagePath::url($o->image),
                'price' => $o->price !== null && (float) $o->price > 0 ? (float) $o->price : null,
                'valid_until' => $o->valid_until,
                'tour_id' => $o->tour_id,
            ];
        }

        return null;
    }

    // ─── helpers ─────────────────────────────────────────────────────────

    public static function tourUrl(Tour $tour, string $locale): string
    {
        return route('tours.show', ['locale' => $locale, 'slug' => $tour->slugFor($locale)]);
    }

    private static function publishedTour(mixed $id): ?Tour
    {
        if (! is_numeric($id) || (int) $id < 1) {
            return null;
        }

        return Tour::published()->find((int) $id);
    }

    /** Solo http(s) o ruta interna "/algo": evita javascript: y similares. */
    private static function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $url) || (str_starts_with($url, '/') && ! str_starts_with($url, '//'))) {
            return $url;
        }

        return null;
    }

    /** Setting guardado como JSON string (Settings::save) o array. */
    private static function decodeList(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return is_array($raw) ? array_values($raw) : [];
    }
}
