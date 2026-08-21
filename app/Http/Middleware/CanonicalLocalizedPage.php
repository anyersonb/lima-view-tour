<?php

namespace App\Http\Middleware;

use App\Support\LocalizedPages;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Canonicaliza la URL de las páginas institucionales (propuesta ESPASEO v3).
 *
 * La ruta acepta el slug de los 3 idiomas (ver LocalizedPages::pattern) para
 * no romper enlaces ya publicados o indexados. Este middleware se encarga de
 * que solo UNA de esas URLs devuelva 200 por idioma:
 *
 *   /en/nosotros   → 301 → /en/about-us     (slug español bajo locale inglés:
 *                                            el caso que ESPASEO encontró
 *                                            indexado en Search Console)
 *   /es/about-us   → 301 → /es/nosotros     (al revés, mismo criterio)
 *   /en/about-us   → 200                    (canónica del idioma)
 *
 * Además comparte $localizedAlternates con las 3 URLs REALES, que es lo que
 * lee el layout para hreflang/canonical. Sin esto el layout asume
 * "mismo path, otro prefijo de idioma" y publicaría hreflang a URLs que
 * ahora responden 301.
 */
class CanonicalLocalizedPage
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        // Cada página institucional tiene dos rutas: la del slug español, con el
        // nombre histórico, y la traducida, con el sufijo .localized. Las dos
        // apuntan a la misma clave de config/localized_pages.php.
        $key = Str::beforeLast((string) $route?->getName(), '.localized');

        if (! $key || ! LocalizedPages::has($key)) {
            return $next($request);
        }

        // El idioma se toma del parámetro de ruta y no de app()->getLocale()
        // para no depender del orden en que corran los middlewares del grupo.
        $locale = (string) $route->parameter('locale', LocalizedPages::BASE_LOCALE);
        $expected = LocalizedPages::slugFor($key, $locale);
        // El nombre del parámetro cambia por página (aboutSlug, contactSlug…):
        // RouteCollection indexa por URI, así que cinco rutas con el mismo
        // '/{pageSlug}' se sobrescriben entre ellas y solo sobrevive la última.
        // El segundo segmento de la URL siempre es el slug: /{locale}/{slug}.
        $requested = (string) $request->segment(2);

        if ($expected !== '' && $requested !== $expected) {
            // Query string cruda: getQueryString() de Symfony reordena los
            // parámetros alfabéticamente y eso ensucia el rastro de una
            // campaña cuando alguien la depura. Fallback por si el SAPI no
            // expone QUERY_STRING.
            $query = (string) ($request->server('QUERY_STRING') ?: $request->getQueryString());

            return redirect()->to(
                LocalizedPages::url($key, $locale).($query ? '?'.$query : ''),
                301
            );
        }

        view()->share('localizedAlternates', LocalizedPages::alternates($key));

        return $next($request);
    }
}
