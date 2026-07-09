<?php

namespace App\Services;

use App\Models\AbandonedCart;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

/**
 * Persistencia y ciclo de vida del carrito abandonado.
 *
 * Flujo:
 *   1. El visitante escribe su correo en el checkout → capture() persiste el
 *      carrito + contacto en `abandoned_carts` (status = active).
 *   2. Si NO completa la reserva, el comando `carts:send-recovery` (cron)
 *      envía recordatorios (1h y 24h) con un link de recuperación.
 *   3. Si retoma vía link → restore() recarga los items en la sesión.
 *   4. Si completa una reserva → markConverted() cierra el carrito.
 */
class AbandonedCartService
{
    public function __construct(private readonly CartService $cart) {}

    /**
     * Crea o actualiza el carrito abandonado de la sesión actual con el
     * snapshot del carrito y los datos de contacto disponibles.
     *
     * @param  array{email?:string,name?:string,phone?:string}  $contact
     */
    public function capture(array $contact = []): ?AbandonedCart
    {
        $items = $this->cart->items();

        if ($items->isEmpty()) {
            return null;
        }

        $sessionId = Session::getId();
        $locale    = app()->getLocale();

        $email = isset($contact['email']) ? trim((string) $contact['email']) : null;
        $email = $email !== '' ? $email : null;

        $customerId = auth('customer')->id();
        if (! $customerId && $email) {
            $customerId = Customer::where('email', $email)->value('id');
        }

        $payload = [
            'customer_id'      => $customerId,
            'name'             => $contact['name']  ?? null,
            'phone'            => $contact['phone'] ?? null,
            'locale'           => $locale,
            'items'            => $items->values()->all(),
            'subtotal'         => $this->cart->subtotal(),
            'total'            => $this->cart->total(),
            'coupon_code'      => $this->cart->couponCode(),
            'status'           => 'active',
            'last_activity_at' => now(),
        ];

        // El email solo se sobrescribe si viene informado (no borrar uno ya guardado)
        if ($email) {
            $payload['email'] = $email;
        }

        try {
            $cart = AbandonedCart::firstOrNew(['session_id' => $sessionId]);

            // No re-abrir un carrito ya convertido en esta misma sesión
            if ($cart->exists && $cart->status === 'converted') {
                return $cart;
            }

            $cart->fill($payload);
            $cart->save();

            return $cart;
        } catch (\Throwable $e) {
            Log::warning('abandoned_cart.capture.failed', [
                'session' => $sessionId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Restaura los items de un carrito abandonado en la sesión actual.
     */
    public function restore(AbandonedCart $cart): void
    {
        $this->cart->replace($cart->items ?? []);
    }

    /**
     * Marca como convertido el/los carrito(s) activos que coincidan con la
     * sesión actual y/o el email del cliente que acaba de reservar.
     */
    public function markConverted(?string $sessionId = null, ?string $email = null): void
    {
        $sessionId ??= Session::getId();

        try {
            AbandonedCart::query()
                ->where('status', 'active')
                ->where(function ($q) use ($sessionId, $email) {
                    if ($sessionId) {
                        $q->where('session_id', $sessionId);
                    }
                    if ($email) {
                        $q->orWhere('email', $email);
                    }
                })
                ->update([
                    'status'       => 'converted',
                    'converted_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('abandoned_cart.mark_converted.failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
