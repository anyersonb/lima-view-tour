<?php

namespace App\Models\Concerns;

/**
 * Per-locale meta title/description + optional custom JSON-LD, with fallback
 * to the Spanish value and (for meta title/description) to the legacy global
 * `seo_title` / `seo_description` columns that predate this feature.
 *
 * Used by Tour and Page. BlogPost keeps its own getMetaTitleAttribute() /
 * getMetaDescriptionAttribute() (it never had the legacy seo_title/
 * seo_description columns), but reuses schemaJsonLd() logic here for parity.
 */
trait HasLocalizedSeoMeta
{
    public function getMetaTitleAttribute(): ?string
    {
        $locale = app()->getLocale();

        return $this->{"meta_title_{$locale}"}
            ?: $this->meta_title_es
            ?: ($this->seo_title ?? null);
    }

    public function getMetaDescriptionAttribute(): ?string
    {
        $locale = app()->getLocale();

        return $this->{"meta_description_{$locale}"}
            ?: $this->meta_description_es
            ?: ($this->seo_description ?? null);
    }

    /**
     * Custom JSON-LD for the given (or current) locale. Returns null when
     * there is nothing configured, in which case the view should render its
     * own auto-generated schema instead — see rule documented in
     * resources/views/tours/show.blade.php and blog/show.blade.php.
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
