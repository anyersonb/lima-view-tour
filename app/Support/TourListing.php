<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalizes the free-text CMS fields of a tour (duration, language, group
 * type) for the tours listing: facet classification for the client-side
 * filters AND localized labels for the cards and the filter drawer.
 *
 * Never invents data: an empty or unreadable field yields null / an empty
 * list, so the caller hides the feature instead of showing a made-up value.
 */
class TourListing
{
    public static function ascii(?string $s): string
    {
        return Str::of((string) $s)->lower()->ascii()->trim()->toString();
    }

    /**
     * Facet for the duration filter + sort weight.
     *
     * @return array{bucket: ?string, weight: ?int}
     */
    public static function classifyDuration(?string $raw): array
    {
        $n = self::ascii($raw);

        if ($n === '') {
            return ['bucket' => null, 'weight' => null];
        }

        if (preg_match('/(\d+)\s*(?:d|dias?|days?)\b/', $n, $m)) {
            $days = (int) $m[1];
            if ($days >= 3) {
                return ['bucket' => '3-mas-dias', 'weight' => $days * 24];
            }
            if ($days === 2) {
                return ['bucket' => '2-dias', 'weight' => 48];
            }
            // 1 day declared explicitly => full day.
        }

        if (preg_match('/full\s*day|dia completo|dia inteiro|todo el dia/', $n)) {
            return ['bucket' => 'full-day', 'weight' => 8];
        }

        // "hrs" / "hr" were missing: "11 Hrs" used to fall into "otros" (weight 999).
        if (preg_match('/(\d+)\s*(?:h|hrs?|horas?|hours?)\b/', $n, $m)) {
            $hours = (int) $m[1];

            return $hours <= 5
                ? ['bucket' => 'medio-dia', 'weight' => $hours]
                : ['bucket' => 'full-day', 'weight' => $hours];
        }

        if (preg_match('/medio\s*dia|meio\s*dia|half\s*day/', $n)) {
            return ['bucket' => 'medio-dia', 'weight' => 4];
        }

        return ['bucket' => 'otros', 'weight' => 999];
    }

    /**
     * Duration text in the given locale ("11 Hrs" => "11 horas" / "11 hours").
     * Null when the tour has no duration (no invented "Full Day" fallback).
     * Text that matches no known pattern is returned as the CMS wrote it.
     */
    public static function durationLabel(?string $raw, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $original = trim((string) $raw);

        if ($original === '') {
            return null;
        }

        $n = self::ascii($original);
        $count = static fn (string $base, string $num): string => __(
            'ui.'.$base.(((float) str_replace(',', '.', $num)) === 1.0 ? '_one' : '_other'),
            ['count' => $num],
            $locale
        );

        if (preg_match('/(\d+)\s*(?:d|dias?|days?)\b/', $n, $m) && (int) $m[1] >= 2) {
            return $count('duration_days', $m[1]);
        }

        if (preg_match('/full\s*day|dia completo|dia inteiro|todo el dia/', $n)
            || (preg_match('/^1\s*(?:d|dias?|days?)\b/', $n))) {
            return __('ui.duration_full_day', [], $locale);
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:h|hrs?|horas?|hours?)\b/', $n, $m)) {
            return $count('duration_hours', $m[1]);
        }

        if (preg_match('/medio\s*dia|meio\s*dia|half\s*day/', $n)) {
            return __('ui.duration_half_day', [], $locale);
        }

        return $original;
    }

    /**
     * Languages declared by the tour, in the order the CMS wrote them.
     *
     * @return array<int, array{code: string, label: string}>
     */
    public static function languages(?string $raw, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $n = self::ascii($raw);

        if ($n === '') {
            return [];
        }

        $known = ['es', 'en', 'pt', 'fr'];
        $out = [];

        foreach (preg_split('/[\/,&+]| y | e | and /', $n) ?: [] as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $code = match (true) {
                str_contains($part, 'espan') || str_contains($part, 'spanish') || $part === 'es' => 'es',
                str_contains($part, 'ingl') || str_contains($part, 'english') || $part === 'en' => 'en',
                str_contains($part, 'portugu') || $part === 'pt' => 'pt',
                str_contains($part, 'franc') || str_contains($part, 'french') || $part === 'fr' => 'fr',
                default => Str::slug($part) ?: null,
            };

            if (! $code || isset($out[$code])) {
                continue;
            }

            $out[$code] = [
                'code' => $code,
                'label' => in_array($code, $known, true) ? __('ui.lang_'.$code, [], $locale) : Str::title($part),
            ];
        }

        return array_values($out);
    }

    /** @return array<int, string> */
    public static function languageCodes(?string $raw): array
    {
        return array_column(self::languages($raw), 'code');
    }

    /** "Español / Inglés", "Spanish / English"... Null if the tour declares none. */
    public static function languageLabel(?string $raw, ?string $locale = null): ?string
    {
        $labels = array_column(self::languages($raw, $locale), 'label');

        return $labels === [] ? null : implode(' / ', $labels);
    }

    /**
     * Canonical modality of the tour. Equivalent spellings collapse into one
     * ("Grupal", "Tour Grupal"). Values that are not a modality ("MIN. 02
     * PERSONAS") return null so they never show up in the filter.
     *
     * @return array{slug: string, label: string}|null
     */
    public static function modality(?string $raw, ?string $locale = null): ?array
    {
        $n = self::ascii($raw);

        if ($n === '' || preg_match('/\bmin(?:imo|imum)?\b|\d+\s*(?:personas?|people|pessoas|pax)\b/', $n)) {
            return null;
        }

        $private = (bool) preg_match('/privad|private/', $n);
        $group = (bool) preg_match('/grup|group|compartid|shared/', $n);

        $slug = match (true) {
            $private && $group => 'group-private',
            $private => 'private',
            $group => 'group',
            default => null,
        };

        return $slug
            ? ['slug' => $slug, 'label' => __('ui.modality_'.str_replace('-', '_', $slug), [], $locale ?? app()->getLocale())]
            : null;
    }
}
