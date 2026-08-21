<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AbandonedCart extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'items' => 'array',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'reminders_sent' => 'integer',
        'last_activity_at' => 'datetime',
        'last_reminder_at' => 'datetime',
        'converted_at' => 'datetime',
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

    /**
     * Teléfono a usar para contactar: el capturado en el checkout o, si no
     * hay, el del perfil del cliente registrado (customer_id). El carrito
     * no siempre repite un dato que el cliente ya dejó en su cuenta.
     */
    public function getEffectivePhoneAttribute(): ?string
    {
        return $this->phone ?: $this->customer?->phone;
    }

    /**
     * Teléfono normalizado a formato E.164 sin el "+" (solo dígitos), listo
     * para enlaces wa.me / tel:. Los checkouts capturan números peruanos:
     * a veces con el prefijo +51 ya puesto, a veces solo los 9 dígitos del
     * celular. Si no reconoce el formato, devuelve los dígitos tal cual
     * (podría ser un número extranjero) para no ocultar un dato real.
     */
    public function getWhatsappNumberAttribute(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->effective_phone);

        if ($digits === null || $digits === '') {
            return null;
        }

        // Ya viene con código de país peruano (51 + 9 dígitos de celular).
        if (Str::startsWith($digits, '51') && strlen($digits) === 11) {
            return $digits;
        }

        // Celular peruano de 9 dígitos sin código de país (empieza en 9).
        if (strlen($digits) === 9) {
            return '51'.$digits;
        }

        return $digits;
    }

    public function getWhatsappUrlAttribute(): ?string
    {
        return $this->whatsapp_number ? "https://wa.me/{$this->whatsapp_number}" : null;
    }

    public function getTelUrlAttribute(): ?string
    {
        return $this->whatsapp_number ? "tel:+{$this->whatsapp_number}" : null;
    }

    public function getMailtoUrlAttribute(): ?string
    {
        return $this->email ? "mailto:{$this->email}" : null;
    }

    /**
     * URL pública del carrito ("carrito.recuperar") para reenviar a mano
     * (WhatsApp, correo manual, etc.). Null si la ruta no existe (no debería
     * pasar en este proyecto, pero evita romper el panel si cambia routes/web.php).
     */
    public function getRecoveryUrlAttribute(): ?string
    {
        if (! $this->token || ! \Illuminate\Support\Facades\Route::has('cart.recover')) {
            return null;
        }

        return route('cart.recover', [
            'locale' => $this->locale ?: 'es',
            'token' => $this->token,
        ]);
    }
}
