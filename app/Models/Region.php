<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Region extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $region) {
            if (empty($region->slug) && !empty($region->name_es)) {
                $region->slug = Str::slug($region->name_es);
            }
        });
    }

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $this->{"name_{$locale}"} ?: $this->name_es;
    }

    public function getDescriptionAttribute(): ?string
    {
        $locale = app()->getLocale();
        return $this->{"description_{$locale}"} ?: $this->description_es;
    }

    public function getEyebrowAttribute(): ?string
    {
        $locale = app()->getLocale();
        return $this->{"eyebrow_{$locale}"} ?: $this->eyebrow_es;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
