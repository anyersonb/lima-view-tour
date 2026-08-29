<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Marcador de sesión: "PayPal ya cobró, pero la reserva no se pudo crear"
 * (ver CheckoutController::paypalCaptureOrder, catch(\Throwable)). Mientras
 * exista y no haya expirado por su propio TTL, se consulta desde TRES
 * puertas distintas — las tres alcanzables por el mismo cliente sin que
 * ninguna dependa de que las otras dos ya lo hicieran:
 *
 *   1) CheckoutController::paypalCreateOrder()  — no crear una orden nueva.
 *   2) CheckoutController::paypalCaptureOrder() — no capturar una orden
 *      distinta ya aprobada en OTRA pestaña (dos snapshots vivos a la vez
 *      son válidos: PAYPAL_SNAPSHOT_LIMIT = 10). Sin este chequeo acá, el
 *      cobro doble real ocurre: pestaña 1 captura A y falla al reservar
 *      (marcador puesto, snapshot A borrado); pestaña 2 sigue teniendo el
 *      snapshot B vivo y lo captura sin que nadie la detenga.
 *   3) CheckoutController::processPayment() (pay_later) — no crear una
 *      reserva "pendiente" sobre un carrito que ya se cobró por PayPal.
 *
 * Extraído a un servicio propio (en vez de vivir como métodos privados de
 * CheckoutController) porque CartController::index() también lo necesita,
 * de solo lectura, para pintar el aviso desde sesión y que sobreviva a un
 * F5 — una segunda copia de esta lógica en otro controller es exactamente
 * el tipo de duplicado que puede desincronizarse en un control de
 * seguridad.
 */
class PaymentLockService
{
    private const SESSION_KEY = 'payment_captured_pending';

    /**
     * Ventana propia del marcador, independiente de la duración de la
     * cookie de sesión: SESSION_LIFETIME se REINICIA con cada request
     * (actividad del visitante), así que un cliente que sigue navegando el
     * sitio podría quedar bloqueado más de 120 min de reloj aunque la
     * sesión nunca "expire". Este TTL se cuenta desde el momento del cobro,
     * pase lo que pase con la sesión. Se referencia `session.lifetime` para
     * no tener dos números mágicos que puedan desincronizarse.
     */
    public function ttlMinutes(): int
    {
        return (int) config('session.lifetime', 120);
    }

    /**
     * Deja el marcador. Se llama SOLO cuando PayPal ya confirmó el cobro
     * (captureId no nulo) y la creación de la reserva falló después.
     */
    public function store(string $orderId, string $captureId, array $snapshot, array $customer): void
    {
        session()->put(self::SESSION_KEY, [
            'order_id' => $orderId,
            'capture_id' => $captureId,
            'snapshot' => $snapshot,
            'customer' => [
                'customer_name' => $customer['customer_name'] ?? null,
                'customer_email' => $customer['customer_email'] ?? null,
                'customer_phone' => $customer['customer_phone'] ?? null,
            ],
            'locked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array{order_id: string, capture_id: string, snapshot: array, customer: array, locked_at: string}|null
     */
    public function current(): ?array
    {
        $lock = session()->get(self::SESSION_KEY);

        if (! $lock) {
            return null;
        }

        $lockedAt = Carbon::parse($lock['locked_at']);

        if ($lockedAt->diffInMinutes(now()) > $this->ttlMinutes()) {
            // Expiró por TTL propio: se limpia solo, sin esperar a que
            // caduque la sesión entera.
            $this->forget();

            return null;
        }

        return $lock;
    }

    public function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Texto único usado por las tres puertas (los dos 409/JSON y el render
     * desde sesión en el Blade del carrito): un solo lugar que arma el
     * mensaje evita que una de las tres se desincronice de las otras dos.
     */
    public function message(array $lock): string
    {
        return 'Ya registramos un pago tuyo que está en revisión manual. '
            .'Por seguridad no podemos iniciar un cobro nuevo. Te contactaremos '
            .'en breve; si tienes prisa, escríbenos con esta referencia: '
            .$lock['capture_id'];
    }

    /**
     * Payload JSON compartido por las respuestas 409 de paypalCreateOrder()
     * y paypalCaptureOrder(). Contrato (documentado también en
     * CheckoutController):
     *   HTTP 409
     *   { "error": "<texto>", "code": "payment_pending_manual_review", "reference": "<capture_id>" }
     */
    public function blockedResponsePayload(array $lock): array
    {
        return [
            'error' => $this->message($lock),
            'code' => 'payment_pending_manual_review',
            'reference' => $lock['capture_id'],
        ];
    }
}
