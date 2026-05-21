<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Support\ImagePath;

class Tour extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'itinerary_es' => 'array',
        'itinerary_en' => 'array',
        'includes_es' => 'array',
        'includes_en' => 'array',
        'excludes_es' => 'array',
        'excludes_en' => 'array',
        'gallery' => 'array',
        'seo_keywords' => 'array',
        'is_featured' => 'boolean',
        'is_published' => 'boolean',
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

    public static function makeUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $count = static::where('slug', 'like', $slug . '%')->count();
        return $count ? $slug . '-' . ($count + 1) : $slug;
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
}
