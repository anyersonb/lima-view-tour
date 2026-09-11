<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Meta title/description editables desde el admin para las páginas que NO son
 * una entidad de base de datos (home, catálogo, catálogo por región, listado
 * del blog). Los tours, las páginas institucionales y los posts guardan su
 * meta en su propia fila; estas no tienen fila donde guardarla, así que viven
 * en `settings` con la clave `seo_page_{pagina}_{campo}_{idioma}`.
 *
 * Cascada: idioma actual → español → el texto por defecto de la vista
 * (lang/{idioma}/seo.php). Nunca devuelve string vacío: un campo en blanco en
 * el admin significa "usá el texto por defecto", no "publicá un title vacío".
 */
class PageSeo
{
    /** Claves de página, en el orden en que se muestran en el admin. */
    public const PAGES = [
        'home'        => 'Home',
        'tours'       => 'Catálogo de tours (/tours)',
        'tours_lima'  => 'Catálogo — Lima (/tours/categoria/lima)',
        'tours_ica'   => 'Catálogo — Ica (/tours/categoria/ica)',
        'tours_cusco' => 'Catálogo — Cusco (/tours/categoria/cusco)',
        'blog'        => 'Blog — listado (/blog)',
    ];

    public const LOCALES = ['es' => 'Español', 'en' => 'English', 'pt' => 'Português'];

    public static function title(string $page, ?string $fallback = null): ?string
    {
        return static::value($page, 'title') ?? $fallback;
    }

    public static function description(string $page, ?string $fallback = null): ?string
    {
        return static::value($page, 'description') ?? $fallback;
    }

    /**
     * Custom JSON-LD for a system page (home, catálogo, catálogo por región,
     * blog), by current locale with fallback to Spanish. Returns null (never
     * empty) when nothing is configured, in which case the view keeps
     * whatever automatic schema it already emits — see rule documented next
     * to each `@push('schema')` in home/tours/blog index views.
     *
     * No text fallback here (unlike title()/description()): an empty schema
     * field means "don't print anything additional", not "use a default
     * schema" — there is no sensible default JSON-LD to fall back to.
     */
    public static function schemaJsonLd(string $page): ?string
    {
        return static::value($page, 'schema');
    }

    /**
     * Clave de página del catálogo: sin categoría es el catálogo completo.
     * `$categoria` llega de la ruta (lima|ica|cusco).
     */
    public static function toursKey(?string $categoria = null): string
    {
        $key = $categoria ? 'tours_' . $categoria : 'tours';

        return array_key_exists($key, static::PAGES) ? $key : 'tours';
    }

    public static function settingKey(string $page, string $field, string $locale): string
    {
        return "seo_page_{$page}_{$field}_{$locale}";
    }

    protected static function value(string $page, string $field): ?string
    {
        $locale = app()->getLocale();

        foreach (array_unique([$locale, 'es']) as $candidate) {
            $value = Setting::get(static::settingKey($page, $field, $candidate));

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
