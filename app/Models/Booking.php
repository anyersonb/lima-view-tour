<?php

namespace App\Models;

use App\Support\BookingCalendar;
use App\Support\PaymentPlan;
use App\Support\TourLanguage;
use Illuminate\Support\Carbon;
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
        'same_day' => 'boolean',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'payment_reminder_sent_at' => 'datetime',
        'amount_paid' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'balance_due_at' => 'datetime',
        'balance_paid_at' => 'datetime',
        'balance_reminder_sent_at' => 'datetime',
    ];

    /**
     * Readable language of the tour ("Español", "English", "Português") or null.
     * Reads through getAttributes() so it is safe while bookings.tour_language
     * has not been migrated yet.
     */
    public function getTourLanguageLabelAttribute(): ?string
    {
        return TourLanguage::label($this->getAttributes()['tour_language'] ?? null);
    }

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

    // ── Pago por modos (full | deposit | later) ─────────────────────────────

    /**
     * Adelanto cobrado y saldo aún por cobrar: payment_status = partial con
     * saldo > 0 en una reserva no cancelada.
     */
    public function hasPendingBalance(): bool
    {
        return $this->payment_status === 'partial'
            && (float) $this->balance_due > 0
            && $this->status !== 'cancelled';
    }

    /**
     * payment_status que corresponde cuando una pasarela (webhook) confirma
     * el cobro de la compra ORIGINAL: con adelanto y saldo sin pagar es
     * 'partial', nunca 'paid'. Los webhooks de Culqi/PayPal lo usan para no
     * marcar como pagada una reserva que solo cobró el 50%.
     */
    public function gatewayConfirmedPaymentStatus(): string
    {
        return ($this->payment_plan ?? null) === 'deposit'
            && (float) ($this->balance_due ?? 0) > 0
            && ($this->balance_paid_at ?? null) === null
            ? 'partial'
            : 'paid';
    }

    /** Momento de salida del tour (hora de Lima) o null si la reserva no tiene fecha. */
    public function departureAt(): ?Carbon
    {
        if (! $this->travel_date) {
            return null;
        }

        return BookingCalendar::departureAt($this->travel_date->toDateString(), $this->tour, $this->departure_slot);
    }

    /**
     * Horario de salida de ESTA reserva, 'HH:MM': el elegido por el cliente si
     * existe; si no, el del tour (o el de config). Los correos, el admin, los
     * links de pago y los recordatorios leen esto, no tour->departure_time.
     */
    public function departureTime(): string
    {
        return BookingCalendar::departureTime($this->tour, $this->departure_slot);
    }

    /** Horario en el idioma indicado ("2:30 p. m." / "2:30 PM" / "14:30"). */
    public function departureTimeLabel(?string $locale = null): string
    {
        return BookingCalendar::timeLabel($this->departureTime(), $locale);
    }

    /** ¿Se reservó el mismo día de la salida? (marca `same_day`, fijada al crear). */
    public function isSameDay(): bool
    {
        return (bool) ($this->same_day ?? false);
    }

    /**
     * Límite para cobrar el saldo = vencimiento del link de pago del saldo:
     * 48 h antes de la salida. Null si no hay fecha de viaje.
     */
    public function balanceDeadline(): ?Carbon
    {
        return $this->departureAt()?->copy()->subHours(PaymentPlan::BALANCE_LINK_EXPIRES_HOURS_BEFORE);
    }

    /**
     * Regla de cancelación del adelanto: reembolsable con MÁS de 24 h de
     * antelación a la salida; no reembolsable con 24 h o menos.
     */
    public function isDepositRefundable(?Carbon $now = null): bool
    {
        $departure = $this->departureAt();

        if ($departure === null) {
            return false;
        }

        $now ??= Carbon::now(BookingCalendar::timezone());

        return $departure->greaterThan($now->copy()->addHours(PaymentPlan::REFUND_HOURS));
    }

    /** Reservas con adelanto cobrado y saldo pendiente (base de recordatorios y panel). */
    public function scopeWithPendingBalance(Builder $query): Builder
    {
        return $query
            ->where('payment_status', 'partial')
            ->where('balance_due', '>', 0)
            ->where('status', '!=', 'cancelled');
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
