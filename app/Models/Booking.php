<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
                $b->reference = 'LVT-'.strtoupper(Str::random(8));
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

    // ── Scopes usados por los filtros del admin (BookingResource) ──────────

    /**
     * Reservas con pago pendiente cuyo viaje ya está encima: es plata a
     * punto de perderse si no se cobra a tiempo.
     */
    public function scopePaymentAtRisk(Builder $query, int $withinDays = 3): Builder
    {
        // Cota INFERIOR obligatoria: sin ella entra toda reserva pendiente del
        // pasado (viaje ya ocurrido) y el filtro degenera en un duplicado de
        // "Pago pendiente". Lo que interesa es la ventana [hoy, hoy+N].
        return $query
            ->where('payment_status', 'pending')
            ->whereDate('travel_date', '>=', now()->toDateString())
            ->whereDate('travel_date', '<=', now()->addDays($withinDays)->toDateString());
    }

    /**
     * Pago pendiente y todavía no se le envió ningún recordatorio: nadie las
     * ha vuelto a contactar.
     */
    public function scopeWithoutPaymentReminder(Builder $query): Builder
    {
        return $query
            ->where('payment_status', 'pending')
            ->whereNull('payment_reminder_sent_at');
    }

    /**
     * Reservas de "tour personalizado / ninguno de los anteriores": no
     * tienen tour_id ni, por lo tanto, categoría.
     */
    public function scopeCustomTour(Builder $query): Builder
    {
        return $query->whereNull('tour_id');
    }

    public function scopeTravelingBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q, string $date) => $q->whereDate('travel_date', '>=', $date))
            ->when($to, fn (Builder $q, string $date) => $q->whereDate('travel_date', '<=', $date));
    }

    public function scopeBookedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q, string $date) => $q->whereDate('created_at', '>=', $date))
            ->when($to, fn (Builder $q, string $date) => $q->whereDate('created_at', '<=', $date));
    }

    /**
     * Reservas cuyo tour pertenece a alguna de las categorías dadas.
     * Las reservas de tour personalizado (tour_id null) quedan FUERA a
     * propósito: no tienen categoría, así que no pueden pertenecer a
     * ninguna. Usa scopeCustomTour() para verlas.
     */
    public function scopeForCategories(Builder $query, array $categoryIds): Builder
    {
        $categoryIds = array_filter($categoryIds);

        if (empty($categoryIds)) {
            return $query;
        }

        return $query->whereHas('tour', fn (Builder $q) => $q->whereIn('category_id', $categoryIds));
    }
}
