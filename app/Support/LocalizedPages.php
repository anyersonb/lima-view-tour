<?php

namespace App\Support;

/**
 * Slug + URL por idioma de las páginas institucionales (Nosotros, Contacto,
 * Reseñas, Términos, Privacidad). Fuente de verdad: config/localized_pages.php
 * — ahí está documentado por qué es config y no BD.
 *
 * Se usa en tres lugares:
 *  1. routes/web.php — para el patrón de la ruta (acepta los 3 slugs).
 *  2. CanonicalLocalizedPage — 301 del slug de otro idioma al del idioma activo.
 *  3. Vistas y SitemapController — para GENERAR enlaces internos ya
 *     traducidos, sin depender del 301 (un enlace interno que pasa por un
 *     redirect es un salto que Google se come de gusto, pero no gratis).
 */
class LocalizedPages
{
    /** Idioma cuyo slug es el canónico histórico y nunca se renombra. */
    public const BASE_LOCALE = 'es';

    /**
     * Mapa completo, tal cual está en config.
     *
     * @return array<string, array<string, string>>
     */
    public static function all(): array
    {
        return config('localized_pages', []);
    }

    /**
     * Nombres de ruta declarados (about, contact, reviews, legal.terms…).
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(static::all());
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, static::all());
    }

    /**
     * Slugs de una página por idioma.
     *
     * @return array<string, string>
     */
    public static function slugs(string $key): array
    {
        return static::all()[$key] ?? [];
    }

    /**
     * Slug de la página en un idioma. Cae al slug español si el idioma no está
     * declarado, para no generar nunca una URL vacía.
     */
    public static function slugFor(string $key, ?string $locale = null): string
    {
        $slugs = static::slugs($key);
        $locale = $locale ?: app()->getLocale();

        return $slugs[$locale] ?? ($slugs[static::BASE_LOCALE] ?? '');
    }

    /**
     * URL absoluta ya traducida, ej. url('/en/about-us').
     */
    public static function url(string $key, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        return url('/'.$locale.'/'.static::slugFor($key, $locale));
    }

    /**
     * Path (sin dominio) ya traducido, ej. '/en/about-us'. Útil en tests y en
     * el sitemap, que arma las URLs con su propia base.
     */
    public static function path(string $key, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        return '/'.$locale.'/'.static::slugFor($key, $locale);
    }

    /**
     * Las 3 URLs reales de una página, para hreflang y canonical.
     *
     * @return array<string, string>
     */
    public static function alternates(string $key): array
    {
        $out = [];

        foreach (array_keys(static::slugs($key)) as $locale) {
            $out[$locale] = static::url($key, $locale);
        }

        return $out;
    }

    /**
     * Igual que pattern(), pero SIN el slug del idioma base: es el patrón de la
     * ruta parametrizada, porque el slug español ya tiene su propia ruta con el
     * nombre histórico (ver el bloque de páginas institucionales en
     * routes/web.php). Si el slug español entrara acá, las dos rutas competirían
     * por la misma URL.
     */
    public static function patternExcludingBase(string $key): string
    {
        $slugs = static::slugs($key);
        unset($slugs[static::BASE_LOCALE]);
        $slugs = array_values(array_unique($slugs));

        return implode('|', array_map('preg_quote', $slugs));
    }

    /**
     * Patrón para ->where() de la ruta: acepta el slug de los 3 idiomas, así
     * /en/nosotros entra a la ruta (y se va con un 301 al slug inglés) en vez
     * de morir en un 404 que perdería el enlace.
     */
    public static function pattern(string $key): string
    {
        $slugs = array_values(array_unique(static::slugs($key)));

        return implode('|', array_map('preg_quote', $slugs));
    }
}
