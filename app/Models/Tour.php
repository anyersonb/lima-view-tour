<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Support\ImagePath;
use App\Models\Concerns\HasLocalizedSeoMeta;
use App\Models\Concerns\HasLocalizedSlug;

class Tour extends Model
{
    use HasFactory, SoftDeletes, HasLocalizedSlug, HasLocalizedSeoMeta;

    protected $guarded = ['id'];

    protected $casts = [
        'itinerary_es' => 'array',
        'itinerary_en' => 'array',
        'itinerary_pt' => 'array',
        'faqs_es' => 'array',
        'faqs_en' => 'array',
        'faqs_pt' => 'array',
        'includes_es' => 'array',
        'includes_en' => 'array',
        'includes_pt' => 'array',
        'excludes_es' => 'array',
        'excludes_en' => 'array',
        'excludes_pt' => 'array',
        'gallery' => 'array',
        'comparison' => 'array',
        'seo_keywords' => 'array',
        'is_featured' => 'boolean',
        'is_published' => 'boolean',
        'show_best_seller' => 'boolean',
        'show_offer_badge' => 'boolean',
        'price' => 'decimal:2',
        'price_before' => 'decimal:2',
        'rating' => 'decimal:1',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $tour) {
            if (empty($tour->slug) && !empty($tour->title_es)) {
                $tour->slug = static::makeUniqueSlug($tour->title_es);
            }
        });
    }

    /**
     * Slug libre a partir del título.
     *
     * Contaba con `like 'slug%'`, que también cuenta los slugs que SIEMPLEMENTE
     * empiezan igual: un título nuevo cuyo slug era prefijo de otro ya
     * publicado salía con "-2" pegado sin que existiera ningún duplicado. Así
     * quedó en producción "...huacachina-islas-ballestas-en-paracas-2".
     * Ahora solo desempata cuando el slug EXACTO ya está tomado, y sigue
     * subiendo hasta encontrar uno libre (el "-2" fijo podía chocar de nuevo).
     */
    public static function makeUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'tour';
        $slug = $base;
        $n = 1;

        while (
            static::withTrashed()
                ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base . '-' . (++$n);
        }

        return $slug;
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderByDesc('created_at');
    }

    public function getTitleAttribute(): string
    {
        $locale = app()->getLocale();
        return $this->{"title_{$locale}"} ?: $this->title_es;
    }

    public function getDescriptionAttribute(): ?string
    {
        $locale = app()->getLocale();
        return $this->{"description_{$locale}"} ?: $this->description_es;
    }

    public function getCoverUrlAttribute(): string
    {
        return ImagePath::url($this->cover_image) ?? asset('assets/banners/banner-hero.jpg');
    }

    /**
     * URL absoluta de la imagen Open Graph subida por tour (panel Filament,
     * campo "seo_image", disco "public", tours/seo/). Null si el editor no
     * cargó una: layouts/app.blade.php debe caer entonces al og:image global
     * (Setting seo_og_image), no a un valor inventado aquí.
     *
     * Mismo mecanismo que cover_url/gallery_urls (ImagePath::url), que ya
     * resuelve la ruta relativa guardada en BD a URL absoluta vía
     * Storage::disk('public')->url() y es idempotente si el valor ya viene
     * con http(s).
     */
    public function getSeoImageUrlAttribute(): ?string
    {
        return ImagePath::url($this->seo_image);
    }

    public function getGalleryUrlsAttribute(): array
    {
        $g = $this->gallery ?? [];
        if (! is_array($g) || count($g) === 0) {
            return [$this->cover_url];
        }
        return array_values(array_filter(array_map(fn ($p) => ImagePath::url($p), $g)));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Devuelve el contenido del bloque comparativo ya resuelto para el idioma
     * actual (con fallback a español), o null si el bloque no está activo o
     * no tiene ni una columna con ítems. Pensado para consumirse en el Blade.
     */
    public function comparisonData(?string $locale = null): ?array
    {
        $c = $this->comparison;
        if (! is_array($c) || empty($c['enabled'])) {
            return null;
        }

        $locale = $locale ?: app()->getLocale();
        // Resuelve una clave localizada con fallback: <key>_<locale> → <key>_es
        $t = function (string $key, $default = '') use ($c, $locale) {
            $val = $c["{$key}_{$locale}"] ?? null;
            if ($val === null || $val === '' || $val === []) {
                $val = $c["{$key}_es"] ?? $default;
            }
            return $val;
        };

        $conv = $t('conv', []);
        $prem = $t('prem', []);
        $conv = is_array($conv) ? array_values(array_filter($conv, fn ($i) => trim((string) $i) !== '')) : [];
        $prem = is_array($prem) ? array_values(array_filter($prem, fn ($i) => trim((string) $i) !== '')) : [];

        // Sin ítems en ninguna columna no vale la pena renderizar
        if (empty($conv) && empty($prem)) {
            return null;
        }

        return [
            'color'      => in_array(($c['color'] ?? 'teal'), ['teal', 'orange'], true) ? $c['color'] : 'teal',
            'badge'      => $t('badge'),
            'title'      => $t('title'),
            'title_hl'   => $t('title_hl'),
            'intro'      => $t('intro'),
            'conv_title' => $t('conv_title'),
            'prem_title' => $t('prem_title'),
            'conv'       => $conv,
            'prem'       => $prem,
            'footer'     => $t('footer'),
            'image'      => $this->galleryUrls[0] ?? $this->cover_url,
        ];
    }
}
