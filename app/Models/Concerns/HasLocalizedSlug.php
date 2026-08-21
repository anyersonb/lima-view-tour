<?php

namespace App\Models\Concerns;

use Closure;
use Illuminate\Support\Str;

/**
 * Adds per-locale slug resolution to a model that has a base `slug` column
 * (Spanish, canonical) plus nullable `slug_en` / `slug_pt` columns.
 *
 * Rules (ESPASEO proposal, 2026-08-17):
 *  - Spanish (`es`) always resolves through the original `slug` column.
 *    It is never renamed — production links and legacy redirects depend on it.
 *  - EN/PT resolve through their own column when present.
 *  - When the EN/PT column is empty, the Spanish slug is used as a fallback
 *    so the page is never a 404 for lack of a translation.
 *  - If a visitor requests the Spanish slug under a non-Spanish locale AND a
 *    translated slug exists for that locale, the caller should 301-redirect
 *    to the translated slug (see resolveForLocale()) to avoid duplicate URLs.
 */
trait HasLocalizedSlug
{
    protected static function localizedSlugColumn(string $locale): ?string
    {
        return match ($locale) {
            'en' => 'slug_en',
            'pt' => 'slug_pt',
            default => null,
        };
    }

    /**
     * Slug to use for building a URL in the given locale.
     */
    public function slugFor(string $locale): string
    {
        $column = static::localizedSlugColumn($locale);

        if ($column === null) {
            return $this->slug;
        }

        return $this->{$column} ?: $this->slug;
    }

    /**
     * Sanitizes a slug the same way the rest of the project does: lowercase,
     * hyphenated, no accents/special characters (via Str::slug), capped to
     * the 60-character limit validated in the Filament forms.
     */
    public static function sanitizeSlugValue(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Str::limit(Str::slug($value), 60, '');
    }

    /**
     * Resolves a record for a given locale + requested slug.
     *
     * - Tries the locale's own slug column first (canonical hit, no redirect).
     * - Falls back to the Spanish `slug` column (covers untranslated content
     *   and legacy links).
     * - If the record found via the Spanish slug DOES have a distinct
     *   translated slug for this locale, flags it so the caller can issue a
     *   301 to the canonical translated URL instead of serving duplicate
     *   content under two URLs.
     *
     * @param  Closure|null  $scope  Optional query scope, e.g. fn ($q) => $q->published()
     * @return array{model: static|null, redirect_slug: string|null}
     */
    public static function resolveForLocale(string $locale, string $slug, ?Closure $scope = null): array
    {
        $query = fn () => $scope ? $scope(static::query()) : static::query();

        $column = static::localizedSlugColumn($locale);

        if ($column !== null) {
            $model = $query()->where($column, $slug)->first();

            if ($model) {
                return ['model' => $model, 'redirect_slug' => null];
            }
        }

        $model = $query()->where('slug', $slug)->first();

        if (! $model) {
            return ['model' => null, 'redirect_slug' => null];
        }

        if ($column !== null) {
            $translated = $model->{$column};

            if (filled($translated) && $translated !== $slug) {
                return ['model' => $model, 'redirect_slug' => $translated];
            }
        }

        return ['model' => $model, 'redirect_slug' => null];
    }
}
