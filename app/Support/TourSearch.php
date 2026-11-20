<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Region;
use App\Models\Setting;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Buscador del home (3 preguntas) y filtro server-side de /{locale}/tours.
 *
 * GET /{locale}/tours?duracion=medio-dia|1-dia|2-mas-dias
 *                    &experiencia=<slug de Categoría>
 *                    &destino=<slug de Región>
 *
 * - Whitelist estricta: un valor que no sea uno de los permitidos se IGNORA
 *   (no es error ni se refleja en la página).
 * - Duración: se deriva del texto libre `tours.duration` con
 *   TourListing::classifyDuration(). Un tour cuya duración no se puede leer
 *   (vacía o texto raro) no entra bajo ningún tramo: nunca se inventa.
 * - Las opciones del buscador son SOLO las que tienen >= 1 tour publicado.
 */
class TourSearch
{
    public const PARAM_DURATION = 'duracion';

    public const PARAM_EXPERIENCE = 'experiencia';

    public const PARAM_DESTINATION = 'destino';

    /** Búsqueda de texto del hero (título/subtítulo/región/categoría). */
    public const PARAM_QUERY = 'q';

    public const QUERY_MAX_LENGTH = 80;

    /** Máximo de palabras que se evalúan de `q` (todas deben coincidir). */
    public const QUERY_MAX_TERMS = 5;

    /** Máximo de chips rápidos del hero. */
    public const QUICK_CHIPS_MAX = 8;

    private const LOCALES = ['es', 'en', 'pt'];

    /** Valores válidos de ?duracion= (en orden de presentación). */
    public const DURATIONS = ['medio-dia', '1-dia', '2-mas-dias'];

    /** Tramo de duración (medio-dia | 1-dia | 2-mas-dias) o null si no es legible. */
    public static function durationBucket(?string $raw): ?string
    {
        $n = TourListing::ascii($raw);

        if ($n === '') {
            return null;
        }

        $bucket = TourListing::classifyDuration($raw)['bucket'];

        return match ($bucket) {
            'medio-dia' => 'medio-dia',
            'full-day' => '1-dia',
            '2-dias', '3-mas-dias' => '2-mas-dias',
            // "1 día" declarado explícitamente cae en 'otros' en classifyDuration().
            default => preg_match('/^1\s*(?:d|dias?|days?)\b/', $n) ? '1-dia' : null,
        };
    }

    /** Etiqueta localizada del tramo. */
    public static function durationLabel(string $bucket, ?string $locale = null): string
    {
        return __('ui.search_duration_'.str_replace('-', '_', $bucket), [], $locale ?? app()->getLocale());
    }

    /**
     * Opciones del buscador del home. Cada lista solo trae opciones con tours
     * publicados; si una lista queda vacía la vista debe ocultar esa pregunta
     * (y si las tres están vacías, todo el buscador).
     *
     * @return array{
     *   action: string,
     *   params: array{duration: string, experience: string, destination: string, query: string},
     *   query_max: int,
     *   durations: array<int, array{value: string, label: string, count: int}>,
     *   experiences: array<int, array{value: string, label: string, count: int}>,
     *   destinations: array<int, array{value: string, label: string, count: int}>,
     *   has_options: bool
     * }
     */
    public static function options(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $tours = Tour::published()->get(['id', 'duration', 'category_id', 'region_id']);

        $durations = [];
        foreach (self::DURATIONS as $bucket) {
            $count = $tours->filter(fn (Tour $t) => self::durationBucket($t->duration) === $bucket)->count();
            if ($count > 0) {
                $durations[] = ['value' => $bucket, 'label' => self::durationLabel($bucket, $locale), 'count' => $count];
            }
        }

        $experiences = Category::active()->orderBy('order')->get()
            ->map(fn (Category $c) => [
                'value' => (string) $c->slug,
                'label' => (string) ($c->{"name_{$locale}"} ?: $c->name_es),
                'count' => $tours->where('category_id', $c->id)->count(),
            ])
            ->filter(fn (array $o) => $o['count'] > 0 && $o['value'] !== '')
            ->values()->all();

        $destinations = Region::active()->orderBy('order')->get()
            ->map(fn (Region $r) => [
                'value' => (string) $r->slug,
                'label' => (string) ($r->{"name_{$locale}"} ?: $r->name_es),
                'count' => $tours->where('region_id', $r->id)->count(),
            ])
            ->filter(fn (array $o) => $o['count'] > 0 && $o['value'] !== '')
            ->values()->all();

        return [
            'action' => route('tours.index', ['locale' => $locale]),
            'params' => [
                'duration' => self::PARAM_DURATION,
                'experience' => self::PARAM_EXPERIENCE,
                'destination' => self::PARAM_DESTINATION,
                'query' => self::PARAM_QUERY,
            ],
            'query_max' => self::QUERY_MAX_LENGTH,
            'durations' => $durations,
            'experiences' => $experiences,
            'destinations' => $destinations,
            'has_options' => $durations !== [] || $experiences !== [] || $destinations !== [],
        ];
    }

    /**
     * Lee y valida los 3 parámetros contra la whitelist. Siempre devuelve las
     * 3 claves (valor o null).
     *
     * @return array{duracion: ?string, experiencia: ?string, destino: ?string}
     */
    public static function filtersFromRequest(Request $request): array
    {
        $clean = static function (mixed $v): ?string {
            // Arrays (?destino[]=x) o valores no escalares: se descartan.
            if (! is_string($v)) {
                return null;
            }
            $v = trim($v);

            return ($v !== '' && strlen($v) <= 80 && preg_match('/^[a-z0-9-]+$/', $v)) ? $v : null;
        };

        $duration = $clean($request->query(self::PARAM_DURATION));
        $experience = $clean($request->query(self::PARAM_EXPERIENCE));
        $destination = $clean($request->query(self::PARAM_DESTINATION));

        return [
            self::PARAM_DURATION => in_array($duration, self::DURATIONS, true) ? $duration : null,
            self::PARAM_EXPERIENCE => $experience !== null
                && Category::active()->where('slug', $experience)->exists() ? $experience : null,
            self::PARAM_DESTINATION => $destination !== null
                && Region::active()->where('slug', $destination)->exists() ? $destination : null,
            self::PARAM_QUERY => self::normalizeQuery($request->query(self::PARAM_QUERY)),
        ];
    }

    /**
     * Texto libre de búsqueda saneado: solo string (un array `q[]=` se
     * descarta), sin caracteres de control, espacios colapsados y máx. 80
     * caracteres. Null si queda vacío. No se limita el alfabeto (acentos,
     * ñ, ç...): el escape se hace al consultar (escapeLike) y al pintar ({{ }}).
     */
    public static function normalizeQuery(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = preg_replace('/[\p{C}\s]+/u', ' ', $value);
        if (! is_string($clean)) { // UTF-8 inválido
            return null;
        }

        $clean = trim(mb_substr(trim($clean), 0, self::QUERY_MAX_LENGTH));

        return $clean !== '' ? $clean : null;
    }

    /** Escapa % _ y el propio carácter de escape ('!') para usarlos en LIKE ... ESCAPE '!'. */
    public static function escapeLike(string $term): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
    }

    /**
     * Filtra por texto: cada palabra de $q (máx. 5) debe aparecer en el
     * título o subtítulo (idioma actual + ES), en el nombre de la región o en
     * el de la categoría. Con $withDescription también en la descripción ES.
     * LIKE siempre con ESCAPE '!' y valores enlazados (nunca interpolados;
     * los nombres de columna salen de una lista fija + locale en whitelist).
     */
    public static function scopeText(Builder $query, string $q, bool $withDescription = false, ?string $locale = null): Builder
    {
        $locale ??= app()->getLocale();
        $locale = in_array($locale, self::LOCALES, true) ? $locale : 'es';
        $grammar = $query->getQuery()->getGrammar();
        $like = static fn (string $col): string => $grammar->wrap($col)." like ? escape '!'";

        $terms = array_slice(
            array_values(array_filter(preg_split('/[\s,;]+/u', $q) ?: [], static fn ($t) => $t !== '')),
            0,
            self::QUERY_MAX_TERMS
        );

        $tourCols = array_unique(["title_{$locale}", 'title_es', "subtitle_{$locale}", 'subtitle_es']);
        if ($withDescription) {
            $tourCols[] = 'description_es';
        }
        $nameCols = array_unique(["name_{$locale}", 'name_es']);

        foreach ($terms as $term) {
            $pattern = '%'.self::escapeLike($term).'%';

            $query->where(function (Builder $w) use ($tourCols, $nameCols, $like, $pattern): void {
                foreach ($tourCols as $col) {
                    $w->orWhereRaw($like($col), [$pattern]);
                }
                foreach (['region', 'category'] as $relation) {
                    $w->orWhereHas($relation, function (Builder $r) use ($nameCols, $like, $pattern): void {
                        $r->where(function (Builder $n) use ($nameCols, $like, $pattern): void {
                            foreach ($nameCols as $col) {
                                $n->orWhereRaw($like($col), [$pattern]);
                            }
                        });
                    });
                }
            });
        }

        return $query;
    }

    /**
     * Aplica los filtros ya validados y devuelve los tours publicados.
     *
     * @param  array{duracion: ?string, experiencia: ?string, destino: ?string}  $filters
     * @return Collection<int, Tour>
     */
    public static function apply(array $filters): Collection
    {
        $query = Tour::published()->ordered();

        if ($filters[self::PARAM_EXPERIENCE] ?? null) {
            $slug = $filters[self::PARAM_EXPERIENCE];
            $query->whereHas('category', fn ($q) => $q->where('slug', $slug));
        }

        if ($filters[self::PARAM_DESTINATION] ?? null) {
            $slug = $filters[self::PARAM_DESTINATION];
            $query->whereHas('region', fn ($q) => $q->where('slug', $slug));
        }

        if ($text = $filters[self::PARAM_QUERY] ?? null) {
            self::scopeText($query, $text);
        }

        $tours = $query->get();

        if ($bucket = $filters[self::PARAM_DURATION] ?? null) {
            $tours = $tours->filter(fn (Tour $t) => self::durationBucket($t->duration) === $bucket)->values();
        }

        return $tours;
    }

    /** @param array<string, ?string> $filters */
    public static function isActive(array $filters): bool
    {
        return count(array_filter($filters)) > 0;
    }

    /**
     * Chips de "lo que buscaste" para pintar encima de los resultados
     * (cada uno con la URL que lo quita).
     *
     * @param  array<string, ?string>  $filters
     * @return array<int, array{param: string, value: string, label: string, remove_url: string}>
     */
    public static function chips(array $filters, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $chips = [];

        foreach ($filters as $param => $value) {
            if (! $value) {
                continue;
            }

            $label = match ($param) {
                self::PARAM_QUERY => $value,
                self::PARAM_DURATION => self::durationLabel($value, $locale),
                self::PARAM_EXPERIENCE => ($c = Category::where('slug', $value)->first()) ? (string) ($c->{"name_{$locale}"} ?: $c->name_es) : $value,
                default => ($r = Region::where('slug', $value)->first()) ? (string) ($r->{"name_{$locale}"} ?: $r->name_es) : $value,
            };

            $chips[] = [
                'param' => $param,
                'value' => $value,
                'label' => $label,
                'remove_url' => route('tours.index', ['locale' => $locale] + array_filter(array_diff_key($filters, [$param => 1]))),
            ];
        }

        return $chips;
    }

    /**
     * Enlace de WhatsApp para el estado "sin resultados", con mensaje
     * prellenado. Null si el admin no cargó WhatsApp ni teléfono.
     */
    public static function whatsappUrl(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $digits = preg_replace('/\D/', '', (string) (Setting::get('whatsapp') ?: LegalInfo::phone()));

        if ($digits === '') {
            return null;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode(__('ui.search_empty_whatsapp_text', [], $locale));
    }
}
