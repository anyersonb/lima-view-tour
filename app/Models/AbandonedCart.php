<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AbandonedCart extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'items'            => 'array',
        'subtotal'         => 'decimal:2',
        'total'            => 'decimal:2',
        'reminders_sent'   => 'integer',
        'last_activity_at' => 'datetime',
        'last_reminder_at' => 'datetime',
        'converted_at'     => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $cart) {
            if (empty($cart->token)) {
                $cart->token = (string) Str::uuid();
            }
            if (empty($cart->last_activity_at)) {
                $cart->last_activity_at = now();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getItemsCountAttribute(): int
    {
        return collect($this->items ?? [])->sum(fn ($i) => (int) ($i['quantity'] ?? 1));
    }

    public function isRecoverable(): bool
    {
        return $this->status === 'active'
            && ! empty($this->email)
            && ! empty($this->items);
    }
}
