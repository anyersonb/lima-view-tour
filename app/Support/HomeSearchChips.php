<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Region;
use App\Models\Setting;
use App\Models\Tour;

/**
 * Chips rápidos bajo el buscador del hero ("Medio día", "Día completo",
 * "Historia"...). Cada chip es un enlace a /{locale}/tours con UN filtro ya
 * validado por TourSearch (duracion | experiencia | destino).
 *
 * Fuente:
 *  1) Ajustes -> Home -> "Chips del buscador" (repetidor `home_search_chips`)
 *     si tiene al menos un chip válido;
 *  2) si está vacío o ninguno es válido: automáticos = tramos de duración
 *     (medio día, día completo) + categorías activas, SOLO los que tengan al
 *     menos 1 tour publicado. No se inventa ningún chip: "Historia" o
 *     "Arqueología" aparecen solo si existe una categoría con ese nombre
 *     (o si el cliente crea un chip propio apuntando a una categoría real).
 */
class HomeSearchChips
{
    public const SETTING = 'home_search_chips';

    /** Tipos de destino admitidos por el repetidor. */
    public const TYPES = ['duracion', 'categoria', 'destino'];

    /** Tramos de duración que se ofrecen como chip (el de 2+ días no es "rápido"). */
    public const AUTO_DURATIONS = ['medio-dia', '1-dia'];

    /**
     * @return array<int, array{key: string, type: string, value: string, label: string, url: string, count: int}>
     */
    public static function all(?string $locale = null): array
    {
        $locale = in_array($locale, ['es', 'en', 'pt'], true) ? $locale : (in_array(app()->getLocale(), ['es', 'en', 'pt'], true) ? app()->getLocale() : 'es');
        $tours = Tour::published()->get(['id', 'duration', 'category_id', 'region_id']);

        if ($tours->isEmpty()) {
            return [];
        }

        $custom = self::fromSettings($tours, $locale);

        return array_slice($custom !== [] ? $custom : self::automatic($tours, $locale), 0, TourSearch::QUICK_CHIPS_MAX);
    }

    /** @return array<int, array<string, mixed>> */
    private static function automatic($tours, string $locale): array
    {
        $chips = [];

        foreach (TourSearch::DURATIONS as $bucket) {
            if (! in_array($bucket, self::AUTO_DURATIONS, true)) {
                continue;
            }
            $count = $tours->filter(fn (Tour $t) => TourSearch::durationBucket($t->duration) === $bucket)->count();
            if ($count > 0) {
                $chips[] = self::chip('duracion', $bucket, __('ui.chip_duration_'.str_replace('-', '_', $bucket), [], $locale), $count, $locale);
            }
        }

        foreach (Category::active()->orderBy('order')->get() as $c) {
            $count = $tours->where('category_id', $c->id)->count();
            if ($count > 0 && (string) $c->slug !== '') {
                $chips[] = self::chip('categoria', (string) $c->slug, self::name($c, $locale), $count, $locale);
            }
        }

        return $chips;
    }

    /** @return array<int, array<string, mixed>> */
    private static function fromSettings($tours, string $locale): array
    {
        $raw = Setting::get(self::SETTING);
        $rows = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($rows)) {
            return [];
        }

        $chips = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = $row['type'] ?? null;
            $value = is_string($row['value'] ?? null) ? trim($row['value']) : '';
            if (! in_array($type, self::TYPES, true) || $value === '') {
                continue;
            }

            [$count, $natural] = match ($type) {
                'duracion' => [
                    in_array($value, TourSearch::DURATIONS, true)
                        ? $tours->filter(fn (Tour $t) => TourSearch::durationBucket($t->duration) === $value)->count() : 0,
                    in_array($value, TourSearch::DURATIONS, true) ? TourSearch::durationLabel($value, $locale) : '',
                ],
                'categoria' => (($c = Category::active()->where('slug', $value)->first())
                    ? [$tours->where('category_id', $c->id)->count(), self::name($c, $locale)] : [0, '']),
                default => (($r = Region::active()->where('slug', $value)->first())
                    ? [$tours->where('region_id', $r->id)->count(), self::name($r, $locale)] : [0, '']),
            };

            // Sin tours reales detrás, el chip no se muestra (llevaría a un vacío).
            if ($count < 1) {
                continue;
            }

            $label = '';
            foreach (array_unique([$locale, 'es']) as $l) {
                $candidate = $row["label_{$l}"] ?? null;
                if (is_string($candidate) && trim($candidate) !== '') {
                    $label = trim($candidate);
                    break;
                }
            }
            $label = mb_substr($label !== '' ? $label : $natural, 0, 40);

            if ($label !== '') {
                $chips[] = self::chip($type, $value, $label, $count, $locale);
            }
        }

        return $chips;
    }

    private static function chip(string $type, string $value, string $label, int $count, string $locale): array
    {
        $param = match ($type) {
            'duracion' => TourSearch::PARAM_DURATION,
            'categoria' => TourSearch::PARAM_EXPERIENCE,
            default => TourSearch::PARAM_DESTINATION,
        };

        return [
            'key' => $type.':'.$value,
            'type' => $type,
            'value' => $value,
            'label' => $label,
            'url' => route('tours.index', ['locale' => $locale, $param => $value]),
            'count' => $count,
        ];
    }

    private static function name(Category|Region $m, string $locale): string
    {
        return (string) ($m->{"name_{$locale}"} ?: $m->name_es);
    }
}
