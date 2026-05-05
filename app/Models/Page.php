<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'blocks' => 'array',
        'is_published' => 'boolean',
        'show_in_sitemap' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $page) {
            if (empty($page->slug) && !empty($page->title_es)) {
                $page->slug = Str::slug($page->title_es);
            }
        });
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function getTitleAttribute(): string
    {
        $locale = app()->getLocale();
        return $this->{"title_{$locale}"} ?: $this->title_es;
    }

    public function getContentAttribute(): ?string
    {
        $locale = app()->getLocale();
        return $this->{"content_{$locale}"} ?: $this->content_es;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
