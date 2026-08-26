<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\Page;
use App\Models\SlugRedirect;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

/**
 * Mapa único de "quién ocupa cada URL del sitio" (2026-08-25).
 *
 * Nace al abrir el slug español a edición desde el panel. Los ->unique() de
 * Filament miran UNA columna: bastan para que dos tours no compartan `slug`,
 * pero no ven los tres choques que sí ocurren en este sitio:
 *
 *  1. Fallback por idioma. /en/tours/detalle/x resuelve primero por `slug_en`
 *     y si no hay, cae al `slug` español. Si el tour A tiene slug='x' y el B
 *     tiene slug_en='x', ambos apuntan a /en/tours/detalle/x: gana B y la
 *     ficha de A queda inalcanzable en inglés. Ninguna columna está repetida,
 *     así que el ->unique() lo deja pasar.
 *  2. Rutas escritas en código. routes/web.php registra paths literales ANTES
 *     de /tours/detalle/{slug} (por ejemplo el 301 del tour de Huacachina).
 *     Un slug que caiga en uno de esos paths nunca llega al controlador.
 *  3. Historial de la era WordPress. config/legacy_redirects.php declara 410s
 *     y 301s sobre slugs sueltos en la raíz, que el Route::fallback() atiende
 *     antes de mirar el contenido publicado.
 *
 * Devuelve conflictos con dos severidades: BLOCKING cuando un contenido
 * quedaría sin URL alcanzable (se impide guardar) y WARNING cuando solo se
 * degrada una URL heredada (se avisa y se deja guardar).
 */
class RouteRegistry
{
    public const LOCALES = ['es', 'en', 'pt'];

    /** Idioma cuyo slug alimenta el fallback de los otros dos. */
    public const BASE_LOCALE = 'es';

    /** Prefijo público bajo /{locale}/ de cada tipo con URL propia. */
    protected const PREFIXES = [
        Tour::class     => 'tours/detalle',
        BlogPost::class => 'blog',
    ];

    /** Ruta que DEBE atender ese path. Si lo atiende otra, hay choque. */
    protected const ROUTE_NAMES = [
        Tour::class     => 'tours.show',
        BlogPost::class => 'blog.show',
    ];

    protected const ARTICLES = [
        Tour::class     => 'el tour',
        BlogPost::class => 'la entrada de blog',
        Page::class     => 'la página',
    ];

    // ─────────────────────────────────────────────────────────────────────
    // Slugs y paths
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Normaliza como lo hace el resto del proyecto, pero SIN recortar a 60:
     * acá solo se compara, y cuatro tours en producción tienen slugs de hasta
     * 95 caracteres. Recortarlos para comparar inventaría choques falsos.
     */
    public static function normalize(?string $slug): ?string
    {
        if ($slug === null || trim($slug) === '') {
            return null;
        }

        return Str::slug($slug) ?: null;
    }

    /**
     * Slug con el que un contenido responde en un idioma, ya con el fallback
     * al español aplicado.
     *
     * @param  array<string, string|null>  $slugs  ['es' => ..., 'en' => ..., 'pt' => ...]
     */
    public static function effectiveSlug(array $slugs, string $locale): ?string
    {
        $base = static::normalize($slugs[static::BASE_LOCALE] ?? null);

        if ($locale === static::BASE_LOCALE) {
            return $base;
        }

        return static::normalize($slugs[$locale] ?? null) ?: $base;
    }

    /** Slugs de un registro, en el formato que espera effectiveSlug(). */
    public static function slugsOf(Model $record): array
    {
        return [
            'es' => $record->slug ?? null,
            'en' => $record->slug_en ?? null,
            'pt' => $record->slug_pt ?? null,
        ];
    }

    public static function pathFor(string $modelClass, string $locale, string $slug): string
    {
        $prefix = static::PREFIXES[$modelClass] ?? null;

        return $prefix === null
            ? "/{$locale}/{$slug}"
            : "/{$locale}/{$prefix}/{$slug}";
    }

    /** URL pública completa, para mostrarla en el panel. */
    public static function urlFor(string $modelClass, string $locale, string $slug): string
    {
        return rtrim((string) config('app.url'), '/').static::pathFor($modelClass, $locale, $slug);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Detección de conflictos
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Todos los choques que provocarían estos slugs.
     *
     * @param  string  $modelClass  Tour::class, BlogPost::class o Page::class
     * @param  int|null  $recordId  null al crear
     * @param  array<string, string|null>  $slugs
     * @param  string|null  $forLocale  Limita el análisis al idioma del campo
     *                                  que se está editando. El slug español
     *                                  se revisa SIEMPRE en los tres, porque
     *                                  alimenta el fallback de los otros dos.
     * @return array<int, RouteConflict>
     */
    public static function conflictsFor(string $modelClass, ?int $recordId, array $slugs, ?string $forLocale = null): array
    {
        $locales = ($forLocale === null || $forLocale === static::BASE_LOCALE)
            ? static::LOCALES
            : [$forLocale];

        $conflicts = [];

        foreach ($locales as $locale) {
            $slug = static::effectiveSlug($slugs, $locale);

            if ($slug === null) {
                continue;
            }

            $conflicts = array_merge(
                $conflicts,
                static::siblingConflicts($modelClass, $recordId, $locale, $slug, $slugs),
                static::hardcodedRouteConflicts($modelClass, $locale, $slug),
                static::redirectHistoryConflicts($modelClass, $recordId, $locale, $slug),
            );
        }

        // Los choques de la raíz solo dependen del slug español: el
        // Route::fallback() trabaja sin prefijo de idioma.
        $baseSlug = static::effectiveSlug($slugs, static::BASE_LOCALE);

        if ($baseSlug !== null && ($forLocale === null || $forLocale === static::BASE_LOCALE)) {
            $conflicts = array_merge(
                $conflicts,
                static::legacyRootConflicts($modelClass, $baseSlug),
            );
        }

        // Bloqueantes primero: es lo que el editor tiene que resolver.
        usort($conflicts, fn (RouteConflict $a, RouteConflict $b) => (int) $b->isBlocking() <=> (int) $a->isBlocking());

        return $conflicts;
    }

    /** Solo los que impiden guardar. */
    public static function blockingConflictsFor(string $modelClass, ?int $recordId, array $slugs, ?string $forLocale = null): array
    {
        return array_values(array_filter(
            static::conflictsFor($modelClass, $recordId, $slugs, $forLocale),
            fn (RouteConflict $c) => $c->isBlocking()
        ));
    }

    /**
     * Otro contenido del mismo tipo que responde al mismo path en ese idioma.
     */
    protected static function siblingConflicts(string $modelClass, ?int $recordId, string $locale, string $slug, array $slugs): array
    {
        $columns = ['id', 'title_es', 'slug', 'slug_en', 'slug_pt'];

        $siblings = $modelClass::query()
            ->when($recordId !== null, fn ($q) => $q->whereKeyNot($recordId))
            ->get($columns);

        $out = [];

        foreach ($siblings as $sibling) {
            $siblingSlug = static::effectiveSlug(static::slugsOf($sibling), $locale);

            if ($siblingSlug === null || $siblingSlug !== $slug) {
                continue;
            }

            $out[] = new RouteConflict(
                RouteConflict::BLOCKING,
                $locale,
                static::pathFor($modelClass, $locale, $slug),
                static::describe($modelClass, $sibling),
                static::editUrlFor($modelClass, (int) $sibling->id),
                static::explainCollision($locale, $slugs, static::slugsOf($sibling)),
            );
        }

        return $out;
    }

    /**
     * ¿Hay una ruta escrita en código que atienda ese path antes que el
     * controlador del contenido?
     */
    protected static function hardcodedRouteConflicts(string $modelClass, string $locale, string $slug): array
    {
        $expected = static::ROUTE_NAMES[$modelClass] ?? null;

        if ($expected === null) {
            return [];
        }

        $path  = static::pathFor($modelClass, $locale, $slug);
        $route = static::routeClaiming($path);

        if ($route === null || $route->getName() === $expected) {
            return [];
        }

        $name = $route->getName() ?: $route->uri();

        return [new RouteConflict(
            RouteConflict::BLOCKING,
            $locale,
            $path,
            "una ruta fija del sitio ({$name})",
            null,
            'Esa dirección la atiende el código, no el CMS: el contenido quedaría inalcanzable. Elige otro slug.',
        )];
    }

    /**
     * Primera ruta registrada que responde a ese path. Replica el orden real
     * del router: gana la que se registró antes, y el fallback no cuenta.
     */
    protected static function routeClaiming(string $path): ?\Illuminate\Routing\Route
    {
        $request = Request::create($path, 'GET');

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if ($route->isFallback) {
                continue;
            }

            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if ($route->matches($request)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Choques en la raíz sin idioma (/{slug}), que atiende Route::fallback()
     * para las URLs heredadas de WordPress. No bloquean: la ficha sigue
     * teniendo su URL con prefijo de idioma. Pero el editor debe saber que esa
     * dirección vieja no va a llegar a su contenido.
     */
    protected static function legacyRootConflicts(string $modelClass, string $slug): array
    {
        $out    = [];
        $legacy = (array) config('legacy_redirects', []);

        if (in_array($slug, (array) ($legacy['gone'] ?? []), true)) {
            $out[] = new RouteConflict(
                RouteConflict::WARNING,
                static::BASE_LOCALE,
                "/{$slug}",
                'la lista de URLs retiradas (410) de la era WordPress',
                null,
                'La ficha funciona igual en su URL con idioma; solo la dirección vieja sin /es seguirá respondiendo "410 Gone".',
            );
        }

        if (isset($legacy['map'][$slug])) {
            $out[] = new RouteConflict(
                RouteConflict::WARNING,
                static::BASE_LOCALE,
                "/{$slug}",
                'una redirección heredada de WordPress (301 hacia '.$legacy['map'][$slug].')',
                null,
                'La dirección vieja sin /es seguirá yendo a ese otro destino, no a este contenido.',
            );
        }

        // El fallback busca primero en tours y después en el blog: si los dos
        // comparten slug, la URL de la raíz siempre cae en el tour.
        foreach ([Tour::class, BlogPost::class] as $otherClass) {
            if ($otherClass === $modelClass) {
                continue;
            }

            $other = $otherClass::query()->where('slug', $slug)->first(['id', 'title_es', 'slug']);

            if (! $other) {
                continue;
            }

            $winner = $modelClass === Tour::class ? $modelClass : $otherClass;

            $out[] = new RouteConflict(
                RouteConflict::WARNING,
                static::BASE_LOCALE,
                "/{$slug}",
                static::describe($otherClass, $other),
                static::editUrlFor($otherClass, (int) $other->id),
                'Las dos URLs con idioma funcionan. Lo único que se comparte es la dirección vieja sin /es, que siempre irá a '
                    .(static::ARTICLES[$winner] ?? 'el otro contenido').'.',
            );
        }

        return $out;
    }

    /**
     * El slug elegido era la URL vieja de otro contenido y hoy tiene un 301
     * activo. Reutilizarlo apaga ese 301.
     */
    protected static function redirectHistoryConflicts(string $modelClass, ?int $recordId, string $locale, string $slug): array
    {
        $ownerId = SlugRedirect::ownerIdFor((new $modelClass)->getMorphClass(), $locale, $slug);

        if ($ownerId === null || $ownerId === $recordId) {
            return [];
        }

        $owner = $modelClass::query()->find($ownerId, ['id', 'title_es', 'slug']);

        if (! $owner) {
            return [];
        }

        return [new RouteConflict(
            RouteConflict::WARNING,
            $locale,
            static::pathFor($modelClass, $locale, $slug),
            static::describe($modelClass, $owner).' (era su dirección anterior)',
            static::editUrlFor($modelClass, $ownerId),
            'Hoy esa URL redirige a ese contenido con un 301. Si la usas acá, el redirect se apaga y quien tenga el enlace viejo llegará a esta ficha.',
        )];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Presentación
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Explica POR QUÉ chocan, que casi nunca es "escribiste el mismo texto":
     * lo habitual es que uno de los dos esté cayendo al fallback español.
     */
    protected static function explainCollision(string $locale, array $mine, array $theirs): string
    {
        if ($locale === static::BASE_LOCALE) {
            return 'Dos contenidos no pueden compartir dirección: uno de los dos quedaría sin URL propia.';
        }

        $iFallBack    = static::normalize($mine[$locale] ?? null) === null;
        $theyFallBack = static::normalize($theirs[$locale] ?? null) === null;
        $idioma       = $locale === 'en' ? 'inglés' : 'portugués';

        if ($iFallBack && ! $theyFallBack) {
            return "Este contenido no tiene slug en {$idioma}, así que reutiliza el español y aterriza en la misma dirección que el otro. Ponle un slug propio en {$idioma} o cambia el español.";
        }

        if (! $iFallBack && $theyFallBack) {
            return "El otro contenido no tiene slug en {$idioma} y reutiliza su slug español, que es justo el que estás escribiendo acá.";
        }

        if ($iFallBack && $theyFallBack) {
            return "Ninguno de los dos tiene slug en {$idioma}: los dos caen al español y chocan ahí.";
        }

        return "Dos contenidos no pueden compartir dirección en {$idioma}.";
    }

    protected static function describe(string $modelClass, Model $record): string
    {
        $article = static::ARTICLES[$modelClass] ?? 'el contenido';
        $title   = trim((string) ($record->title_es ?? '')) ?: ('#'.$record->getKey());

        return "{$article} «{$title}»";
    }

    public static function editUrlFor(string $modelClass, int $id): ?string
    {
        $resource = match ($modelClass) {
            Tour::class     => \App\Filament\Resources\TourResource::class,
            BlogPost::class => \App\Filament\Resources\BlogPostResource::class,
            Page::class     => \App\Filament\Resources\PageResource::class,
            default         => null,
        };

        if ($resource === null) {
            return null;
        }

        try {
            return $resource::getUrl('edit', ['record' => $id]);
        } catch (\Throwable) {
            // Fuera de un panel (tests, consola) no hay contexto para generar
            // la URL. El conflicto se sigue reportando, sin enlace.
            return null;
        }
    }
}
