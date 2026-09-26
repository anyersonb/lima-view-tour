<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Un link de pago "one-off" para cobrar un tour por PayPal sin pasar por el
 * carrito: el admin lo crea desde el panel para un cliente concreto (o para
 * un cobro genérico), y el visitante lo abre en /pagar/{code}.
 *
 * `code` es la única credencial: aleatorio, ≥32 chars (Str::random() usa
 * random_bytes), nunca secuencial ni derivado del id. `amount`/`adults`/
 * `children`/`travel_date` son la fuente de verdad que consumen
 * PaymentLinkController@createOrder/@captureOrder — el monto NUNCA sale del
 * request.
 */
class PaymentLink extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'travel_date' => 'date',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'single_use' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $link) {
            if (empty($link->code)) {
                $link->code = static::generateUniqueCode();
            }

            if (empty($link->status)) {
                $link->status = 'pending';
            }
        });

        // N-1 (docs/payment-links/SECURITY.md, decisión del coordinador
        // 2026-09-25): se eliminan los links de varios usos — TODOS los
        // links son de un solo uso, sin excepción. Forzado en el MODELO
        // (creating Y saving, no solo en el formulario) para que cubra
        // Duplicar, tinker, un comando futuro, o cualquier fila que llegue
        // con single_use=false por otra vía. La columna se conserva (no se
        // borra), simplemente deja de tener efecto: siempre vale true.
        static::saving(function (self $link) {
            $link->single_use = true;

            // N-4: si se edita el monto de un link que sigue 'pending', la
            // orden PayPal que ya tuviera guardada (si la hay) quedó con el
            // importe VIEJO — reutilizarla capturaría el monto incorrecto o
            // PayPal la rechazaría por amount_mismatch. Se limpia para que
            // el próximo createOrder() cree una nueva con el monto actual.
            if ($link->exists && $link->isDirty('amount') && $link->status === 'pending' && $link->paypal_order_id) {
                $link->paypal_order_id = null;
            }
        });

        // B-5 (docs/payment-links/SECURITY.md): borrar un link con un cobro
        // registrado borra el rastro del cobro y rompe la reconciliación
        // del webhook (queda en 'no_match' para siempre). El guardián vive
        // en el MODELO, no solo en el panel — cubre el borrado individual
        // (DeleteAction), el borrado en lote (PaymentLinkResource itera con
        // un foreach normal, así que este evento SÍ dispara por registro) y
        // cualquier otro camino (tinker, un comando futuro).
        //
        // Re-auditoría post-FIX-1 (B-5 PARCIAL): el guardián original solo
        // miraba status==='paid', así que un link reembolsado/rechazado (que
        // YA cobró y cambió de estado) o uno multiuso todavía 'pending'
        // tras su único pago (N-1, ya cerrado) seguían siendo borrables.
        // Ahora la condición es "¿hay un cobro real registrado?" —
        // paypal_capture_id no nulo — sin importar en qué status quedó
        // después.
        static::deleting(function (self $link) {
            if ($link->paypal_capture_id !== null) {
                Log::warning('payment_link.delete_blocked_captured', [
                    'id' => $link->id,
                    'code' => $link->maskedCode(),
                    'status' => $link->status,
                ]);

                return false;
            }
        });
    }

    /**
     * 40 chars alfanuméricos (Str::random() -> random_bytes), reintentando
     * en el remotísimo caso de colisión. Mismo patrón defensivo que
     * Tour::makeUniqueSlug(), pero sin base legible: acá lo que importa es
     * que NO sea adivinable, no que sea bonito.
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = Str::random(40);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /** ¿Ya venció por reloj, sin que nadie haya corrido el self-heal todavía? */
    public function isPastExpiry(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * ¿Se puede pagar AHORA MISMO? pending + no vencido. No mira single_use:
     * eso decide qué pasa DESPUÉS de pagar (ver decisión en el informe), no
     * si se puede intentar pagar.
     */
    public function isUsable(): bool
    {
        return $this->status === 'pending' && ! $this->isPastExpiry();
    }

    /**
     * Self-heal: si venció por reloj y seguía marcado 'pending', lo pasa a
     * 'expired' antes de que el llamador decida qué mostrar. Evita depender
     * de un scheduler solo para que la columna 'status' del panel no mienta
     * (ver pendiente en el informe: un comando programado la dejaría 100% al
     * día sin depender de que alguien visite el link o el panel).
     */
    public function checkAndExpire(): void
    {
        if ($this->status === 'pending' && $this->isPastExpiry()) {
            $this->forceFill(['status' => 'expired'])->save();
        }
    }

    /** URL pública absoluta, para el panel (columna copiable) y los correos. */
    public function getUrlAttribute(): string
    {
        return route('payment-links.show', ['code' => $this->code]);
    }

    /**
     * B-1 (seguridad): el código completo es la credencial que abre la
     * página pública del link (tour, monto, PII del prefill) — nunca debe
     * quedar completo en logs ni analítica. Se usa tanto en
     * PaymentLinkController como en WebhookController.
     */
    public static function maskCode(string $code): string
    {
        return substr($code, 0, 6).'…';
    }

    public function maskedCode(): string
    {
        return static::maskCode($this->code);
    }

    public function getTotalPaxAttribute(): int
    {
        return (int) $this->adults + (int) $this->children;
    }
}
