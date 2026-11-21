<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Tour;

/**
 * Contenido SEO que SALE del home TAL CUAL y pasa al final de /{locale}/tours:
 * "Tours de un día desde Lima" y "Cómo elegir tu tour en Perú" (+ comparador).
 * Mismos textos administrables de siempre (Ajustes → Home), mismos defaults.
 * Cada bloque es null cuando no hay nada que mostrar (la vista lo oculta).
 * Contrato: contrato-datos.md, "HOME TAL CUAL (21-nov)".
 */
class ToursSeoSections
{
    public const DAY_TRIPS_MAX = 4;

    /**
     * @return array{
     *   day_trips: array{title: string, tours: array<int, array<string, mixed>>}|null,
     *   choose: array{eyebrow: string, title: string, intro: string, items: array<int, array{title: string, desc: string}>, compare: ?array}|null
     * }
     */
    public static function all(?string $locale = null): array
    {
        $locale = in_array($locale, ['es', 'en', 'pt'], true) ? $locale : (in_array(app()->getLocale(), ['es', 'en', 'pt'], true) ? app()->getLocale() : 'es');

        return [
            'day_trips' => self::dayTrips($locale),
            'choose' => self::choose($locale),
        ];
    }

    /**
     * Misma regla que tenía el home: 1) tours marcados "show_in_day_trips";
     * 2) sin marcados, tours de la región Lima que no sean destacados;
     * 3) si no hay, tours de Lima aunque repitan. Máx. 4.
     */
    public static function dayTrips(string $locale): ?array
    {
        $tours = collect();

        if (TourFlags::has('show_in_day_trips')) {
            $tours = Tour::published()->where('show_in_day_trips', true)->ordered()->limit(self::DAY_TRIPS_MAX)->get();
        }

        if ($tours->isEmpty()) {
            $lima = static fn () => Tour::published()->ordered()
                ->whereHas('region', static fn ($q) => $q->where('name_es', 'Lima'));
            $featured = Tour::published()->featured()->pluck('id')->all();
            $tours = $lima()->when($featured !== [], static fn ($q) => $q->whereNotIn('id', $featured))
                ->limit(self::DAY_TRIPS_MAX)->get();
            if ($tours->isEmpty()) {
                $tours = $lima()->limit(self::DAY_TRIPS_MAX)->get();
            }
        }

        if ($tours->isEmpty()) {
            return null;
        }

        $tours->load(['region', 'category']);

        return [
            'title' => Setting::getLocalized('home_sec_cities_title', $locale) ?? __('ui.seo_day_trips_title', [], $locale),
            'tours' => $tours->map(fn (Tour $t) => HomeFinalSections::pickCard($t, $locale))->all(),
        ];
    }

    public static function choose(string $locale): ?array
    {
        $raw = Setting::get('home_choose_items');
        $rows = is_string($raw) ? json_decode($raw, true) : $raw;
        $rows = is_array($rows) && $rows !== [] ? array_values($rows) : HomeDefaults::chooseItems();

        $items = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) (($row["title_{$locale}"] ?? '') ?: ($row['title_es'] ?? '')));
            $desc = trim((string) (($row["desc_{$locale}"] ?? '') ?: ($row['desc_es'] ?? '')));
            if ($title !== '' || $desc !== '') {
                $items[] = ['title' => $title, 'desc' => $desc];
            }
        }

        if ($items === []) {
            return null;
        }

        return [
            'eyebrow' => Setting::getLocalized('home_sec_choose_eyebrow', $locale) ?? __('ui.seo_choose_eyebrow', [], $locale),
            'title' => strip_tags(Setting::getLocalized('home_sec_choose_title', $locale) ?? __('ui.seo_choose_title', [], $locale)),
            'intro' => strip_tags(Setting::getLocalized('home_sec_choose_intro', $locale) ?? __('ui.seo_choose_intro', [], $locale)),
            'items' => $items,
            'compare' => HomeSections::compare($locale),
        ];
    }
}
