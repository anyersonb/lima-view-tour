<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'travel_date' => 'date',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'payment_reminder_sent_at' => 'datetime',
    ];

    public function hasDiscount(): bool
    {
        return $this->discount_type !== null && (float) $this->discount_amount > 0;
    }

    protected static function booted(): void
    {
        static::creating(function (self $b) {
            if (empty($b->reference)) {
                $b->reference = 'LVT-' . strtoupper(Str::random(8));
            }
        });
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getTotalPaxAttribute(): int
    {
        return (int) $this->adults + (int) $this->children;
    }
}
