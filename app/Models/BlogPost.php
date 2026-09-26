<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Concerns\HasLocalizedSlug;

class BlogPost extends Model
{
    use HasLocalizedSlug;

    /**
     * Idiomas que este modelo puede servir. Español es obligatorio (columnas
     * NOT NULL, validado en BlogPostResource::form()); inglés y portugués son
     * opcionales — ver isAvailableIn().
     */
    public const AVAILABLE_LOCALES = ['es', 'en', 'pt'];

    /**
     * Mass-assignment guard: only id is protected.
     */
    protected $guarded = ['id'];

    /**
     * Attribute casts.
     */
    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'tags'         => 'array',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // Boot
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Auto-generate slug and reading_minutes before creating/updating.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $post): void {
            // Auto-slug from Spanish title when slug is empty
            if (empty($post->slug) && ! empty($post->title_es)) {
                $post->slug = Str::slug($post->title_es);
            }

            // Auto-calculate reading minutes from Spanish body word count
            if (! empty($post->body_es)) {
                $wordCount = str_word_count(strip_tags($post->body_es));
                $post->reading_minutes = (int) ceil($wordCount / 200);
            }
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scope: only posts that are published and whose publish date has passed.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function (Builder $q): void {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    /**
     * Scope: only posts that actually have content in $locale.
     *
     * Requisito de negocio (2026-09-24, revierte el fallback a español del
     * 19/08 y del hotfix de columnas nullable del mismo día): EN/PT siguen
     * siendo opcionales, pero si la traducción está vacía la nota YA NO debe
     * aparecer en ese idioma — nada de mostrar el español bajo /en o /pt.
     * "Existe" en un idioma cuando title_{locale} Y body_{locale} tienen
     * contenido real (no NULL, no '', no solo espacios). El excerpt NO es
     * parte del criterio a propósito: es solo ayuda para la tarjeta/meta
     * cuando existe, no define si la nota se publica en ese idioma.
     *
     * @see isAvailableIn() — misma regla, evaluada en PHP sobre una instancia
     *      ya cargada (para el controlador, tras resolver por slug).
     */
    public function scopeAvailableIn(Builder $query, string $locale): Builder
    {
        if (! in_array($locale, self::AVAILABLE_LOCALES, true)) {
            throw new \InvalidArgumentException("Locale no soportado: {$locale}");
        }

        $titleColumn = "title_{$locale}";
        $bodyColumn = "body_{$locale}";

        // whereRaw con TRIM() es necesario para detectar "solo espacios";
        // el Query Builder no tiene un helper para eso. $titleColumn/$bodyColumn
        // no son entrada de usuario: solo pueden ser title_es|title_en|title_pt
        // / body_es|body_en|body_pt, validado arriba contra AVAILABLE_LOCALES.
        return $query
            ->whereNotNull($titleColumn)
            ->whereRaw("TRIM({$titleColumn}) != ''")
            ->whereNotNull($bodyColumn)
            ->whereRaw("TRIM({$bodyColumn}) != ''");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Locale-aware accessors
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Whether this post has real content (title + body) in $locale.
     *
     * Misma regla que scopeAvailableIn(), pero en PHP sobre una instancia ya
     * cargada — el controlador la usa DESPUÉS de resolver el slug, porque
     * resolveForLocale() puede devolver un post por su slug español aunque no
     * tenga traducción a ese idioma.
     */
    public function isAvailableIn(string $locale): bool
    {
        if (! in_array($locale, self::AVAILABLE_LOCALES, true)) {
            return false;
        }

        return trim((string) $this->{"title_{$locale}"}) !== ''
            && trim((string) $this->{"body_{$locale}"}) !== '';
    }

    /**
     * Returns the title in the current app locale.
     *
     * Hotfix (2026-09-24): YA NO cae al español cuando la traducción está
     * vacía — antes de este cambio, `?: $this->title_es` disfrazaba una nota
     * sin traducir mostrando el contenido español bajo /en o /pt. El
     * controlador (BlogController::show()/index()) garantiza con
     * isAvailableIn()/availableIn() que esta propiedad solo se lee para
     * locales donde el post SÍ tiene contenido, así que el valor devuelto
     * aquí nunca debería estar realmente vacío en producción; sigue
     * devolviendo '' en vez de null para no romper Str::limit()/strip_tags()
     * si algún día se llama fuera de ese camino.
     */
    public function getTitleAttribute(): string
    {
        return (string) $this->{"title_" . app()->getLocale()};
    }

    /**
     * Returns the excerpt in the current app locale. No fallback to Spanish
     * (see getTitleAttribute()). A nota disponible sin excerpt propio
     * simplemente muestra '' — el layout ya cae a la descripción genérica del
     * sitio cuando esto y metaDescription quedan vacíos (layouts/app.blade.php).
     */
    public function getExcerptAttribute(): string
    {
        return (string) $this->{"excerpt_" . app()->getLocale()};
    }

    /**
     * Returns the body in the current app locale. No fallback to Spanish
     * (see getTitleAttribute()).
     */
    public function getBodyAttribute(): string
    {
        return (string) $this->{"body_" . app()->getLocale()};
    }

    /**
     * Returns the SEO meta title in the current locale.
     * Falls back to the localized title (NOT to Spanish anymore — see
     * getTitleAttribute()).
     */
    public function getMetaTitleAttribute(): string
    {
        $locale = app()->getLocale();

        return $this->{"meta_title_{$locale}"} ?: $this->title;
    }

    /**
     * Returns the SEO meta description in the current locale.
     * Falls back to the localized excerpt (NOT to Spanish anymore — see
     * getExcerptAttribute()).
     */
    public function getMetaDescriptionAttribute(): string
    {
        $locale = app()->getLocale();

        return $this->{"meta_description_{$locale}"} ?: $this->excerpt;
    }

    /**
     * Custom JSON-LD for the given (or current) locale, with fallback to
     * Spanish. Returns null when nothing is configured, in which case
     * blog/show.blade.php falls back to its auto-generated BlogPosting schema.
     */
    public function schemaJsonLd(?string $locale = null): ?string
    {
        $locale = $locale ?: app()->getLocale();

        $value = $this->{"schema_jsonld_{$locale}"} ?? null;

        if ($value === null || trim((string) $value) === '') {
            $value = $this->schema_jsonld_es ?? null;
        }

        return ($value !== null && trim((string) $value) !== '') ? $value : null;
    }
}
