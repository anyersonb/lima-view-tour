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

    // ──────────────────────────────────────────────────────────────────────────
    // Locale-aware accessors
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Returns the title in the current app locale, falling back to Spanish.
     */
    // NOTE (2026-08-19 fix): title_en/excerpt_en/body_en (and _pt) are NOT
    // NULL columns without a DB default (see migration
    // 2026_06_29_000010_create_blog_posts_table.php). A post saved without a
    // translation stores '' there, never NULL. `??` only falls back on NULL,
    // so it never fired and a solo-ES post rendered the empty string (or the
    // generic site title, for meta_title/description) under /en or /pt
    // instead of falling back to the Spanish content. `?:` treats '' as
    // falsy too, so the Spanish fallback actually triggers. Applied to all
    // five locale-aware accessors below — they share the exact same columns
    // and the exact same bug, not just the three CRO happened to check
    // (title, meta title, meta description).
    public function getTitleAttribute(): string
    {
        return $this->{"title_" . app()->getLocale()} ?: $this->title_es ?: '';
    }

    /**
     * Returns the excerpt in the current app locale, falling back to Spanish.
     */
    public function getExcerptAttribute(): string
    {
        return $this->{"excerpt_" . app()->getLocale()} ?: $this->excerpt_es ?: '';
    }

    /**
     * Returns the body in the current app locale, falling back to Spanish.
     */
    public function getBodyAttribute(): string
    {
        return $this->{"body_" . app()->getLocale()} ?: $this->body_es ?: '';
    }

    /**
     * Returns the SEO meta title in the current locale.
     * Falls back to meta_title_es, then to the localized title.
     */
    public function getMetaTitleAttribute(): string
    {
        $locale = app()->getLocale();

        return $this->{"meta_title_{$locale}"}
            ?: $this->meta_title_es
            ?: $this->title;
    }

    /**
     * Returns the SEO meta description in the current locale.
     * Falls back to meta_description_es, then to the localized excerpt.
     */
    public function getMetaDescriptionAttribute(): string
    {
        $locale = app()->getLocale();

        return $this->{"meta_description_{$locale}"}
            ?: $this->meta_description_es
            ?: $this->excerpt;
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
