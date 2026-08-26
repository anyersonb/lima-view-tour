<?php

namespace App\Models\Concerns;

use App\Models\SlugRedirect;
use App\Support\RouteRegistry;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Adds per-locale slug resolution to a model that has a base `slug` column
 * (Spanish, canonical) plus nullable `slug_en` / `slug_pt` columns.
 *
 * Rules (ESPASEO proposal, 2026-08-17; slug ES reopened 2026-08-25):
 *  - EN/PT resolve through their own column when present.
 *  - When the EN/PT column is empty, the Spanish slug is used as a fallback
 *    so the page is never a 404 for lack of a translation.
 *  - If a visitor requests the Spanish slug under a non-Spanish locale AND a
 *    translated slug exists for that locale, the caller should 301-redirect
 *    to the translated slug (see resolveForLocale()) to avoid duplicate URLs.
 *  - The Spanish slug USED to be immutable ("nunca se renombra") because a
 *    rename left the indexed URL in a 404. Since 2026-08-25 it is editable
 *    from the panel and this trait keeps the old address alive: every change
 *    of the effective slug in any locale is written to `slug_redirects` and
 *    resolveForLocale() answers the old URL with a 301 to the current one —
 *    el mismo mecanismo que wp_old_slug_redirect en WordPress.
 */
trait HasLocalizedSlug
{
    /**
     * Registra el slug anterior de cada idioma cuyo slug EFECTIVO cambió.
     *
     * Va en `updated` y no en `updating` a propósito: solo hay que dejar
     * historial si el UPDATE realmente se aplicó. Eloquent dispara `updated`
     * antes de llamar a syncOriginal(), así que getOriginal() todavía tiene
     * los valores previos.
     */
    public static function bootHasLocalizedSlug(): void
    {
        static::updated(function (Model $model): void {
            $model->recordSlugRedirects();
        });
    }

    protected function recordSlugRedirects(): void
    {
        $columns = ['es' => 'slug', 'en' => 'slug_en', 'pt' => 'slug_pt'];
        $loaded  = $this->getAttributes();

        // Sin el slug base no hay nada con qué comparar (select parcial raro,
        // o un modelo a medio hidratar).
        if (! array_key_exists('slug', $loaded)) {
            return;
        }

        // La instancia puede no traer slug_en/slug_pt: una factory que no los
        // declaró, o un ->get(['id', 'slug']). Leerlos como null inventaría
        // redirects, y descartar el registro entero se perdería los de EN/PT,
        // que son los que más se caen (dependen del fallback al español).
        //
        // Si una columna no estaba cargada tampoco pudo ir en el UPDATE, así
        // que su valor en BD es idéntico antes y después: se lee de ahí y
        // sirve para los dos lados de la comparación.
        $absent = array_values(array_filter(
            ['slug_en', 'slug_pt'],
            fn (string $column): bool => ! array_key_exists($column, $loaded)
        ));

        $unchanged = [];

        if ($absent !== []) {
            $fresh = static::query()
                ->whereKey($this->getKey())
                ->first(array_merge([$this->getKeyName()], $absent));

            foreach ($absent as $column) {
                $unchanged[$column] = $fresh?->{$column};
            }
        }

        $original = $this->getOriginal();

        $before = [];
        $after  = [];

        foreach ($columns as $locale => $column) {
            if (array_key_exists($column, $unchanged)) {
                $before[$locale] = $unchanged[$column];
                $after[$locale]  = $unchanged[$column];

                continue;
            }

            $before[$locale] = $original[$column] ?? null;
            $after[$locale]  = $this->{$column} ?? null;
        }

        foreach (RouteRegistry::LOCALES as $locale) {
            $old = RouteRegistry::effectiveSlug($before, $locale);
            $new = RouteRegistry::effectiveSlug($after, $locale);

            if ($old === null || $new === null || $old === $new) {
                continue;
            }

            SlugRedirect::remember($this, $locale, $old, $new);
        }
    }

    /** Historial de direcciones anteriores, para mostrarlo en el panel. */
    public function slugRedirects()
    {
        return $this->morphMany(SlugRedirect::class, 'redirectable');
    }

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
     * - Last resort: the slug history (`slug_redirects`). Una URL que ya no
     *   existe porque el editor renombró el contenido devuelve el registro
     *   vigente + su slug actual, para que el controlador emita el 301 en vez
     *   de un 404.
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
            return static::resolveRenamedSlug($locale, $slug, $query);
        }

        if ($column !== null) {
            $translated = $model->{$column};

            if (filled($translated) && $translated !== $slug) {
                return ['model' => $model, 'redirect_slug' => $translated];
            }
        }

        return ['model' => $model, 'redirect_slug' => null];
    }

    /**
     * Nadie responde a esa URL hoy. ¿La respondía alguien antes de un cambio
     * de slug hecho desde el panel?
     *
     * @return array{model: static|null, redirect_slug: string|null}
     */
    protected static function resolveRenamedSlug(string $locale, string $slug, Closure $query): array
    {
        $ownerId = SlugRedirect::ownerIdFor((new static)->getMorphClass(), $locale, $slug);

        if ($ownerId === null) {
            return ['model' => null, 'redirect_slug' => null];
        }

        // El scope sigue mandando: si el contenido se despublicó, la URL vieja
        // muere en 404 igual que la nueva. No se redirige a una ficha oculta.
        $model = $query()->whereKey($ownerId)->first();

        if (! $model) {
            return ['model' => null, 'redirect_slug' => null];
        }

        $current = $model->slugFor($locale);

        if ($current === $slug) {
            return ['model' => $model, 'redirect_slug' => null];
        }

        return ['model' => $model, 'redirect_slug' => $current];
    }
}
