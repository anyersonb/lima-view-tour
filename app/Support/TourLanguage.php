<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Language in which the customer wants to take the tour (guide language).
 *
 * Contract with the front-end: the stored/posted value is ALWAYS a code
 * ('es' | 'en' | 'pt'). Older cart cards posted labels ("English", "Español",
 * "Inglés"...); normalize() maps those to the code so nothing breaks while the
 * selects are being changed. Everything user-facing (e-mails, panel) shows
 * label(), never the code.
 *
 * bookings.tour_language comes from a migration that may not be applied yet
 * (production migrates from a button): code that reads/writes it goes through
 * hasColumn().
 */
final class TourLanguage
{
    public const CODES = ['es', 'en', 'pt'];

    /** Endonyms: the guide language is shown the same way the selects show it. */
    private const LABELS = ['es' => 'Español', 'en' => 'English', 'pt' => 'Português'];

    private const ALIASES = [
        'es' => ['es', 'esp', 'espanol', 'espanyol', 'spanish', 'castellano', 'castilian'],
        'en' => ['en', 'eng', 'english', 'ingles', 'ingl'],
        'pt' => ['pt', 'por', 'portugues', 'portuguese', 'portugues br', 'brazilian'],
    ];

    private static ?bool $hasColumn = null;

    /** Code for a code or a label in any of the supported languages; null if unknown/empty. */
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $key = self::fold($value);

        // "pt-BR", "en_US", "es-PE": keep the language part.
        $base = preg_split('/[-_\s]/', $key)[0] ?? $key;

        foreach (self::ALIASES as $code => $aliases) {
            if (in_array($key, $aliases, true) || in_array($base, $aliases, true)) {
                return $code;
            }
        }

        return null;
    }

    /** Human label for a code (or label); falls back to the raw text when unknown. */
    public static function label(?string $value): ?string
    {
        $code = self::normalize($value);

        if ($code !== null) {
            return self::LABELS[$code];
        }

        $raw = trim((string) $value);

        return $raw !== '' ? $raw : null;
    }

    /** @return array<string, string> code => label (for selects) */
    public static function options(): array
    {
        return self::LABELS;
    }

    /** Posted value (any form) or, when absent/unknown, the site language of the request. */
    public static function resolve(?string $posted, ?string $fallbackLocale = null): string
    {
        return self::normalize($posted)
            ?? self::normalize($fallbackLocale ?? app()->getLocale())
            ?? 'es';
    }

    public static function hasColumn(): bool
    {
        return self::$hasColumn ??= Schema::hasColumn('bookings', 'tour_language');
    }

    /** Drop the memoized schema lookup (tests, long-lived workers). */
    public static function flush(): void
    {
        self::$hasColumn = null;
    }

    private static function fold(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');

        return strtr($value, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c',
        ]);
    }
}
