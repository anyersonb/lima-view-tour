<?php

namespace App\Support;

use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Buscador + asesor del home EN VENTANA: GET /{locale}/api/tours/finder.
 *
 * Reglas de TourSearch (whitelist estricta: duracion, experiencia, destino, q;
 * un valor fuera de la lista se IGNORA) pero, a diferencia de /tours (filtro
 * AND estricto), aquí se ORDENA POR AFINIDAD igual que `sortMatches` del
 * prototipo aprobado y SIEMPRE hay resultados (alternativas cercanas si no hay
 * coincidencia exacta, marcadas con exact=false).
 *
 * Puntaje por tour (solo se evalúan los criterios que llegaron):
 *   destino +7 / -6 · experiencia (tema) +5 / -3 · duración +5 / -3 ·
 *   texto libre +12 / -10.
 * `exact` = cumple TODOS los criterios recibidos (equivale al filtro de /tours).
 */
class TourFinder
{
    public const PARAM_LIMIT = 'limit';

    public const LIMIT_DEFAULT = 4;

    public const LIMIT_MAX = 12;

    private const W_DESTINATION = [7, -6];

    private const W_EXPERIENCE = [5, -3];

    private const W_DURATION = [5, -3];

    private const W_TEXT = [12, -10];

    /** Lee solo los parámetros permitidos; el resto de la query se ignora. */
    public static function filtersFromRequest(Request $request): array
    {
        return TourSearch::filtersFromRequest($request);
    }

    public static function limitFromRequest(Request $request): int
    {
        $raw = $request->query(self::PARAM_LIMIT);

        if (! is_string($raw) || ! ctype_digit($raw)) {
            return self::LIMIT_DEFAULT;
        }

        return max(1, min(self::LIMIT_MAX, (int) $raw));
    }

    /**
     * @param  array{duracion: ?string, experiencia: ?string, destino: ?string, q: ?string}  $filters
     * @return array<string, mixed>
     */
    public static function run(array $filters, string $locale, int $limit = self::LIMIT_DEFAULT): array
    {
        $limit = max(1, min(self::LIMIT_MAX, $limit));
        $duration = $filters[TourSearch::PARAM_DURATION] ?? null;
        $experience = $filters[TourSearch::PARAM_EXPERIENCE] ?? null;
        $destination = $filters[TourSearch::PARAM_DESTINATION] ?? null;
        $text = $filters[TourSearch::PARAM_QUERY] ?? null;

        $tours = Tour::published()->ordered()->with(['region', 'category'])->get();

        $textIds = $text
            ? TourSearch::scopeText(Tour::published(), $text, true, $locale)->pluck('id')->flip()
            : collect();

        $scored = [];
        foreach ($tours->values() as $i => $t) {
            $score = 0;
            $exact = true;

            if ($destination) {
                $ok = $t->region && (string) $t->region->slug === $destination;
                $score += self::W_DESTINATION[$ok ? 0 : 1];
                $exact = $exact && $ok;
            }
            if ($experience) {
                $ok = $t->category && (string) $t->category->slug === $experience;
                $score += self::W_EXPERIENCE[$ok ? 0 : 1];
                $exact = $exact && $ok;
            }
            if ($duration) {
                $ok = TourSearch::durationBucket($t->duration) === $duration;
                $score += self::W_DURATION[$ok ? 0 : 1];
                $exact = $exact && $ok;
            }
            if ($text) {
                $ok = $textIds->has($t->getKey());
                $score += self::W_TEXT[$ok ? 0 : 1];
                $exact = $exact && $ok;
            }

            $scored[] = ['tour' => $t, 'score' => $score, 'exact' => $exact, 'pos' => $i];
        }

        // Afinidad desc; a igualdad, el orden normal del catálogo (estable).
        usort($scored, static fn (array $a, array $b): int => [$b['score'], $a['pos']] <=> [$a['score'], $b['pos']]);

        $exactCount = count(array_filter($scored, static fn (array $r) => $r['exact']));
        $chosen = array_slice($scored, 0, $limit);

        $active = array_filter($filters);

        return [
            'query' => [
                TourSearch::PARAM_QUERY => $text,
                TourSearch::PARAM_DURATION => $duration,
                TourSearch::PARAM_EXPERIENCE => $experience,
                TourSearch::PARAM_DESTINATION => $destination,
            ],
            'applied' => array_map(
                static fn (array $c): array => ['param' => $c['param'], 'value' => $c['value'], 'label' => $c['label']],
                TourSearch::chips($filters, $locale)
            ),
            'has_filters' => $active !== [],
            'has_exact' => $exactCount > 0,
            'exact_count' => $exactCount,
            'total_published' => $tours->count(),
            'limit' => $limit,
            'results' => array_map(static fn (array $r): array => self::card($r['tour'], $locale, $r['exact'], $r['score']), $chosen),
            // "Ver todos / ver más" en la página real de resultados (mismos filtros).
            'see_all_url' => route('tours.index', ['locale' => $locale] + $active),
            // Sin coincidencia exacta: WhatsApp oficial con mensaje prellenado.
            'whatsapp_url' => $exactCount === 0 ? TourSearch::whatsappUrl($locale) : null,
        ];
    }

    /** @return array<string, mixed> */
    private static function card(Tour $t, string $locale, bool $exact, int $score): array
    {
        $card = HomeFinalSections::pickCard($t, $locale);
        $summary = trim(html_entity_decode(strip_tags((string) $t->localized('subtitle', $locale)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($summary === '') {
            $summary = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $t->localized('description', $locale)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }

        $card['exact'] = $exact;
        $card['score'] = $score;
        $card['summary'] = $summary !== '' ? Str::limit($summary, 140) : null;
        $card['duration'] = TourListing::durationLabel($t->duration, $locale);
        $card['thumb'] = ImageVariants::card($t->cover_image, '100px')['src'] ?: $card['image'];

        return $card;
    }
}
