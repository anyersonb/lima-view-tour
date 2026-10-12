<?php

namespace App\Support;

/**
 * Pure text rules behind the 2026-10-12 migration that aligns the home copy
 * with the client's data: the company has "+11 años" (not 16 / not 10) and the
 * pickup promise is not universal (Lima zone vs Cusco Plaza de Armas).
 *
 * Kept out of the migration so it can be unit-tested and so the new texts have
 * a single home (the same strings live in lang ui.home_faq_pickup_answer).
 *
 * PENDING CLIENT CONFIRMATION: the pickup wording.
 */
final class HomeCopyAlignment
{
    public const YEARS = 11;

    public const PICKUP = [
        'es' => 'Sí. En los tours que salen de Lima te recogemos en tu hotel dentro de nuestra zona de recojo: Miraflores, San Isidro y Barranco. En Cusco, el recojo es en hoteles de la zona de la Plaza de Armas.',
        'en' => 'Yes. On tours departing from Lima we pick you up at your hotel within our pickup zone: Miraflores, San Isidro and Barranco. In Cusco, pickup is at hotels in the Plaza de Armas area.',
        'pt' => 'Sim. Nos tours que saem de Lima buscamos você no seu hotel dentro da nossa zona de recolhimento: Miraflores, San Isidro e Barranco. Em Cusco, o recolhimento é em hotéis da região da Plaza de Armas.',
    ];

    /** The stored value of the hero counter ("+16", "16+", "10"...) when it is a stale seniority. */
    public static function isStaleYearsCounter(string $value): bool
    {
        return (bool) preg_match('/^\s*\+?\s*(10|16)\s*\+?\s*$/', $value);
    }

    /**
     * "+16 años de experiencia", "más de 10 años de experiencia", "over 10 years
     * of experience", "16+ years of experience", "mais de 10 anos de experiência"
     * -> same wording with 11. Only when the sentence says "experience": a bare
     * "10 años" can be an age rule and must not be touched.
     */
    public static function fixYears(string $text): string
    {
        $years = self::YEARS;
        $exp = '(?:de\s+|of\s+)?(?:experiencia|experience|experiência)';

        $patterns = [
            // prefix words / plus before the number
            '/((?:\+|m[aá]s de\s+|mas de\s+|over\s+|more than\s+|mais de\s+)\s*)(?:10|16)(\+?)(\s*(?:a[ñn]os|anos|years)\s+'.$exp.')/iu',
            // number followed by plus: "16+ years of experience"
            '/(?<![\d.])(?:10|16)(\+)(\s*(?:a[ñn]os|anos|years)\s+'.$exp.')/iu',
        ];

        $text = preg_replace($patterns[0], '${1}'.$years.'${2}${3}', $text) ?? $text;

        return preg_replace($patterns[1], $years.'${1}${2}', $text) ?? $text;
    }

    public static function isPickupQuestion(string $question): bool
    {
        return (bool) preg_match('/recojo|pick-?up|recolh/iu', $question);
    }

    /**
     * Applies every rule to a decoded setting value (string or nested array).
     * $key is the setting key: stats_years is matched as a whole value.
     */
    public static function transform(string $key, mixed $value): mixed
    {
        if (is_string($value)) {
            if ($key === 'stats_years' && self::isStaleYearsCounter($value)) {
                return '+'.self::YEARS;
            }

            return self::fixYears($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        if (in_array($key, ['home_faqs', 'faqs'], true) && array_is_list($value)) {
            $value = array_map(fn ($item) => is_array($item) ? self::alignFaqItem($item) : $item, $value);
        }

        return array_map(fn ($v) => self::transform('', $v), $value);
    }

    /** Both FAQ shapes: q_es/a_es (home_faqs) and question_es/answer_es (faqs). */
    private static function alignFaqItem(array $item): array
    {
        foreach (['es', 'en', 'pt'] as $l) {
            $q = (string) ($item["q_{$l}"] ?? $item["question_{$l}"] ?? '');
            if ($q === '' || ! self::isPickupQuestion($q)) {
                continue;
            }
            $answerKey = array_key_exists("a_{$l}", $item) ? "a_{$l}" : "answer_{$l}";
            foreach (['es', 'en', 'pt'] as $al) {
                $key = str_starts_with($answerKey, 'a_') ? "a_{$al}" : "answer_{$al}";
                $item[$key] = self::PICKUP[$al];
            }

            return $item;
        }

        return $item;
    }
}
