<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Datos de PAGO que el front del checkout necesita pintar, todos calculados en
 * servidor (nunca armados a mano en JS): montos por modo, métodos disponibles,
 * endpoints y los payloads de GA4 (dataLayer).
 *
 * Los usan CartController::index() (variable de vista `$checkout`) y
 * CheckoutController::summary() (JSON para refrescar tras editar el carrito).
 */
class CheckoutPayment
{
    /**
     * @param  Collection<int, array<string, mixed>>  $items  ítems del carrito
     * @return array<string, mixed>
     */
    public static function build(Collection $items, float $subtotal, float $discount, float $total, string $locale): array
    {
        $amounts = PaymentPlan::amounts($total);
        $methods = CheckoutInfo::paymentMethods();
        // Salida en menos de 48 h (mismo día o mañana cercano): solo 100% ahora.
        $fullOnly = PaymentPlan::mustPayInFull($items);

        if (! $methods['any']) {
            // Sin Culqi y con PayPal oculto: el checkout no puede cobrar. Se avisa al cliente
            // (mensaje + WhatsApp en la vista) y se deja constancia para el equipo.
            \Illuminate\Support\Facades\Log::warning('checkout.no_payment_method_available', [
                'culqi_enabled' => (bool) config('services.culqi.enabled'),
                'paypal_visible' => \App\Services\PayPalService::availableForCustomers(),
            ]);
        }

        return [
            'currency' => 'USD',
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'total' => round($total, 2),
            'total_cents' => PaymentPlan::toCents($total),
            // Modo preseleccionado en el diseño aprobado: 50% ahora (100% si sale en < 48 h).
            'default_mode' => $fullOnly ? PaymentPlan::FULL : PaymentPlan::DEPOSIT,
            'full_only' => $fullOnly,
            'allowed_modes' => $fullOnly ? [PaymentPlan::FULL] : PaymentPlan::MODES,
            'full_only_notice' => __('checkout_payment.full_only_notice'),
            'deposit_percent' => PaymentPlan::DEPOSIT_PERCENT,
            // Por modo: mode, total, due (cobro ahora), balance (saldo), *_cents.
            // Culqi Checkout v4 y PayPal cobran SIEMPRE `due`; el servidor lo recalcula.
            'modes' => $amounts,
            'methods' => $methods,
            // Culqi es el método principal; PayPal el secundario.
            'method_order' => ['culqi', 'paypal'],
            'culqi_public_key' => $methods['culqi'] ? config('services.culqi.public_key') : null,
            'refund_policy' => [
                'refundable_hours' => PaymentPlan::REFUND_HOURS,
                'balance_link_expires_hours_before' => PaymentPlan::BALANCE_LINK_EXPIRES_HOURS_BEFORE,
                'balance_reminder_days_before' => PaymentPlan::BALANCE_REMINDER_DAYS_BEFORE,
            ],
            'endpoints' => [
                'summary' => route('checkout.summary', ['locale' => $locale]),
                'process_later' => route('checkout.process', ['locale' => $locale]),
                'culqi_charge' => route('checkout.culqi.charge', ['locale' => $locale]),
                'paypal_create' => route('checkout.paypal.create', ['locale' => $locale]),
                'paypal_capture' => route('checkout.paypal.capture', ['locale' => $locale]),
                'thanks' => route('checkout.thanks', ['locale' => $locale]),
            ],
            'analytics' => self::analytics($items, $total, $amounts),
        ];
    }

    /**
     * Payloads listos para window.lvtTrack() / dataLayer.push().
     *
     *  - begin_checkout: al abrir el checkout (value = total del carrito).
     *  - add_payment_info: al elegir método de pago. El front completa
     *    `payment_type` (culqi|paypal) y `payment_mode` (full|deposit|later) y
     *    puede leer `value_by_mode[mode]` = lo que se cobraría AHORA.
     *  - purchase: lo emite la página de gracias (CheckoutController::thanks)
     *    con value = monto REALMENTE cobrado, no el total de la reserva.
     *
     * @param  array<string, array<string, mixed>>  $amounts
     * @return array<string, mixed>
     */
    public static function analytics(Collection $items, float $total, array $amounts): array
    {
        $ecommerceItems = $items->map(fn ($it) => [
            'item_id' => (string) ($it['tour_id'] ?? ''),
            'item_name' => $it['title_snapshot'] ?? '',
            'price' => (float) ($it['unit_price'] ?? 0),
            'quantity' => (int) ($it['adults'] ?? 0) + (int) ($it['children'] ?? 0),
        ])->values()->all();

        return [
            'begin_checkout' => [
                'currency' => 'USD',
                'value' => round($total, 2),
                'items' => $ecommerceItems,
            ],
            'add_payment_info' => [
                'currency' => 'USD',
                'value' => round($total, 2),
                'items' => $ecommerceItems,
                'payment_type_values' => ['culqi', 'paypal'],
                'payment_mode_values' => PaymentPlan::MODES,
                'value_by_mode' => array_map(fn ($a) => $a['due'], $amounts),
            ],
        ];
    }
}
