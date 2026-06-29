<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offer extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'valid_until' => 'datetime',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', now());
            });
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

    public function getCtaLabelAttribute(): string
    {
        $locale = app()->getLocale();
        return $this->{"cta_label_{$locale}"} ?: $this->cta_label_es;
    }
}
