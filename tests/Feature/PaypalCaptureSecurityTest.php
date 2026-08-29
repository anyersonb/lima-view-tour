<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Tour;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Hallazgo de seguridad CRÍTICO (2026-08-27): la captura de PayPal no
 * verificaba el importe ni ataba el orderID al carrito. Ataque: aprobar un
 * pedido barato, engordar el carrito desde otra pestaña, disparar la
 * captura — se cobraba el importe viejo y se reservaba el carrito vivo
 * (más caro), marcado como pagado.
 *
 * La corrección: paypalCreateOrder() guarda un snapshot en sesión
 * (total + items calculados en servidor) bajo el orderID. paypalCaptureOrder()
 * SOLO cobra y reserva lo que hay en ese snapshot, tras comparar el importe
 * contra lo que PayPal tiene registrado para esa orden, y borra el snapshot
 * apenas captura (idempotencia: un replay no crea un segundo juego de
 * reservas).
 */
class PaypalCaptureSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALE = 'es';

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    private function tour(array $overrides = []): Tour
    {
        return Tour::factory()->create(array_merge([
            'price' => 150.00,
            'is_published' => true,
        ], $overrides));
    }

    private function cart(): CartService
    {
        return app(CartService::class);
    }

    /** Fakes the PayPal HTTP calls used by createOrder(). */
    private function fakePaypalCreate(string $orderId = 'ORDER-1'): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*/v2/checkout/orders' => Http::response(['id' => $orderId], 201),
        ]);
    }

    /**
     * Fakes token + getOrder (GET) + capture (POST .../capture) for a given
     * order id and remote amount. Keeps whatever was already faked for
     * createOrder by re-declaring the full fake map (Http::fake overwrites).
     */
    private function fakePaypalCapture(string $orderId, string $remoteAmount, string $remoteCurrency = 'USD'): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            "*/v2/checkout/orders/{$orderId}/capture" => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [
                    ['payments' => ['captures' => [['id' => "CAPTURE-{$orderId}"]]]],
                ],
            ], 200),
            "*/v2/checkout/orders/{$orderId}" => Http::response([
                'purchase_units' => [
                    ['amount' => ['currency_code' => $remoteCurrency, 'value' => $remoteAmount]],
                ],
            ], 200),
        ]);
    }

    private function createPaypalOrder(string $orderId = 'ORDER-1'): string
    {
        $this->fakePaypalCreate($orderId);

        $response = $this->postJson(route('checkout.paypal.create', ['locale' => self::LOCALE]));
        $response->assertOk();

        return $response->json('id');
    }

    private function capturePayload(string $orderId, array $overrides = []): array
    {
        return array_merge([
            'orderID' => $orderId,
            'customer_name' => 'Juan Pérez García',
            'customer_email' => 'juan@example.com',
            'customer_phone' => '+51987654321',
            'travel_date' => now()->addDays(10)->format('Y-m-d'),
        ], $overrides);
    }

    // ─────────────────────────────────────────────────────────────
    //  1) orderID que no está en el snapshot de sesión
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function capturing_an_order_id_never_created_here_is_rejected_without_calling_paypal(): void
    {
        Mail::fake();
        // Sin configuración: cualquier llamada real que se le escape al
        // guard queda registrada (y bloqueada) por el fake, nunca sale a
        // la red de verdad.
        Http::fake();

        $tour = $this->tour();
        $this->cart()->add($tour, 2, 0, now()->addDays(10)->toDateString());

        // Nunca se llamó a paypalCreateOrder(): no hay snapshot para este id.
        $response = $this->postJson(
            route('checkout.paypal.capture', ['locale' => self::LOCALE]),
            $this->capturePayload('FORGED-ORDER-ID')
        );

        $response->assertStatus(422);
        $this->assertFalse((bool) $response->json('success'));
        $this->assertSame(0, Booking::count());

        // El guard corta ANTES de tocar la red: cero llamadas a PayPal.
        Http::assertNothingSent();
    }

    // ─────────────────────────────────────────────────────────────
    //  2) snapshot de 100, carrito vivo engordado a 500: reservas = snapshot
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function bookings_and_charge_come_from_the_snapshot_even_if_the_live_cart_was_inflated(): void
    {
        Mail::fake();

        $cheapTour = $this->tour(['price' => 100.00]);
        $this->cart()->add($cheapTour, 1, 0, now()->addDays(10)->toDateString());

        $orderId = $this->createPaypalOrder();
        // Total al crear la orden: 100.00 (snapshot guardado con este valor).

        // El cliente "engorda" el carrito desde otra pestaña ANTES de capturar.
        $expensiveTour = $this->tour(['price' => 400.00]);
        $this->cart()->add($expensiveTour, 1, 0, now()->addDays(11)->toDateString());
        $this->assertEquals(500.00, $this->cart()->total(), 'El carrito vivo debía quedar en 500.');

        // PayPal, del lado del servidor, sigue teniendo registrado el importe
        // ORIGINAL (100.00): es lo que el cliente de verdad aprobó.
        $this->fakePaypalCapture($orderId, '100.00');

        $response = $this->postJson(
            route('checkout.paypal.capture', ['locale' => self::LOCALE]),
            $this->capturePayload($orderId)
        );

        $response->assertOk();
        $this->assertTrue((bool) $response->json('success'));

        // Solo se reservó el tour barato (el del snapshot), no los dos.
        $this->assertSame(1, Booking::count());
        $booking = Booking::first();
        $this->assertSame($cheapTour->id, $booking->tour_id);
        $this->assertEquals(100.00, (float) $booking->total_price);
        $this->assertSame('paid', $booking->payment_status);
    }

    // ─────────────────────────────────────────────────────────────
    //  3) importe de PayPal distinto al snapshot: rechazado ANTES de capturar
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function amount_mismatch_against_paypal_is_rejected_before_capturing_and_creates_no_booking(): void
    {
        Mail::fake();

        $tour = $this->tour(['price' => 150.00]);
        $this->cart()->add($tour, 1, 0, now()->addDays(10)->toDateString());

        $orderId = $this->createPaypalOrder();

        // PayPal dice que la orden vale 999.00, no los 150.00 del snapshot.
        $this->fakePaypalCapture($orderId, '999.00');

        $response = $this->postJson(
            route('checkout.paypal.capture', ['locale' => self::LOCALE]),
            $this->capturePayload($orderId)
        );

        $response->assertStatus(422);
        $this->assertFalse((bool) $response->json('success'));
        $this->assertSame(0, Booking::count());

        // El GET de verificación sí se llamó, pero el POST de captura NUNCA.
        Http::assertSent(fn ($request) => str_contains((string) $request->url(), "/v2/checkout/orders/{$orderId}")
            && ! str_contains((string) $request->url(), '/capture'));
        Http::assertNotSent(fn ($request) => str_contains((string) $request->url(), "/v2/checkout/orders/{$orderId}/capture"));
    }

    // ─────────────────────────────────────────────────────────────
    //  4) replay de la misma captura: no crea un segundo juego de reservas
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function replaying_the_same_capture_does_not_create_a_second_set_of_bookings(): void
    {
        Mail::fake();

        $tour = $this->tour(['price' => 150.00]);
        $this->cart()->add($tour, 1, 0, now()->addDays(10)->toDateString());

        $orderId = $this->createPaypalOrder();
        $this->fakePaypalCapture($orderId, '150.00');

        $payload = $this->capturePayload($orderId);

        $first = $this->postJson(route('checkout.paypal.capture', ['locale' => self::LOCALE]), $payload);
        $first->assertOk();
        $this->assertSame(1, Booking::count());

        // Reintento con el MISMO orderID (doble clic, reintento del JS).
        $second = $this->postJson(route('checkout.paypal.capture', ['locale' => self::LOCALE]), $payload);
        $second->assertStatus(422);

        $this->assertSame(1, Booking::count(), 'El replay creó un segundo juego de reservas.');

        // Refuerzo (hallazgo de cobertura, 2026-08-27): el test original solo
        // comprobaba el conteo de reservas, no que el replay se quedara sin
        // volver a tocar la red. Un guard que rechace en BD pero igual
        // dispare un segundo /capture a PayPal cobraría dos veces aunque
        // este assert de arriba siguiera en verde. Se cuentan las llamadas a
        // /capture registradas en TODO el test (incluida la primera, legítima)
        // y debe haber exactamente UNA: el replay no generó una segunda.
        $captureCalls = Http::recorded(fn ($request) => str_contains((string) $request->url(), '/capture'));
        $this->assertCount(1, $captureCalls, 'El replay volvió a llamar a /capture en PayPal.');
    }

    // ─────────────────────────────────────────────────────────────
    //  Guards rotos a propósito: el test debe ponerse en ROJO
    // ─────────────────────────────────────────────────────────────

    /**
     * Prueba que el propio test del guard 1) SÍ puede fallar: si el guard no
     * existiera (paypalCaptureOrder confiara en cualquier orderID), la
     * captura seguiría adelante y crearía una reserva. Se simula "romper el
     * guard" llamando directo a un orderID que SÍ tiene snapshot pero
     * verificando que, si quitáramos el chequeo, el resto del flujo sí
     * crearía la reserva — o sea, que el test #1 no es un check vacío.
     *
     * @test
     */
    public function the_unknown_order_guard_actually_blocks_a_real_attack_path(): void
    {
        Mail::fake();

        $tour = $this->tour(['price' => 150.00]);
        $this->cart()->add($tour, 1, 0, now()->addDays(10)->toDateString());

        // Con un orderID que SÍ existe (snapshot real) y PayPal confirmando
        // el mismo importe, el flujo completo SÍ debe reservar. Esto prueba
        // que el rechazo del test #1 no es porque el endpoint esté roto en
        // general, sino específicamente por el orderID desconocido.
        $orderId = $this->createPaypalOrder();
        $this->fakePaypalCapture($orderId, '150.00');

        $response = $this->postJson(
            route('checkout.paypal.capture', ['locale' => self::LOCALE]),
            $this->capturePayload($orderId)
        );

        $response->assertOk();
        $this->assertSame(1, Booking::count());
    }

    // ─────────────────────────────────────────────────────────────
    //  El flujo "pagar luego" no se tocó
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function pay_later_flow_still_creates_a_pending_booking_without_touching_paypal(): void
    {
        Mail::fake();
        Http::fake();

        $tour = $this->tour(['price' => 150.00]);
        $this->cart()->add($tour, 2, 0, now()->addDays(10)->toDateString());

        $response = $this->post(route('checkout.process', ['locale' => self::LOCALE]), [
            'customer_name' => 'Ana López',
            'customer_email' => 'ana@example.com',
            'customer_phone' => '+51987000001',
            'travel_date' => now()->addDays(10)->format('Y-m-d'),
            'payment_timing' => 'later',
        ]);

        $response->assertRedirectToRoute('checkout.thanks', ['locale' => self::LOCALE]);
        $this->assertDatabaseHas('bookings', [
            'customer_email' => 'ana@example.com',
            'payment_status' => 'pending',
            'status' => 'pending',
            'payment_method' => 'pay_later',
        ]);

        Http::assertNothingSent();
    }

    // ─────────────────────────────────────────────────────────────
    //  Lote 2026-08-27: dinero cobrado, reserva no creada
    //
    //  paypalCaptureOrder() captura el pago (:~348) y RECIÉN DESPUÉS crea
    //  las reservas con finalizeBookings(). Si esto último revienta, el
    //  dinero ya se movió. El fix:
    //    1) finalizeBookings() crea los Bookings dentro de DB::transaction():
    //       un fallo a mitad de un carrito no deja reservas parciales.
    //    2) El catch(Throwable) de paypalCaptureOrder() distingue si
    //       $captureId ya se llenó (el dinero SÍ se movió): en ese caso deja
    //       un marcador en sesión (payment_captured_pending) y responde con
    //       el código `payment_captured_booking_pending` en vez del genérico
    //       "inténtalo de nuevo".
    //    3) Mientras el marcador exista, paypalCreateOrder() rechaza crear
    //       una orden nueva SIN llamar a PayPal — el bloqueo no depende de
    //       que el frontend desactive el botón.
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function capture_succeeds_but_booking_creation_fails_returns_the_new_code_with_zero_bookings_and_locks_further_orders(): void
    {
        Mail::fake();

        $tour = $this->tour(['price' => 150.00]);
        $this->cart()->add($tour, 1, 0, now()->addDays(10)->toDateString());

        $orderId = $this->createPaypalOrder();
        $this->fakePaypalCapture($orderId, '150.00');

        // Simula que crear el Booking revienta DESPUÉS de que PayPal ya
        // confirmó el cobro (captureOrder() se llama antes que
        // finalizeBookings() en el controller).
        Booking::creating(function (): void {
            throw new \RuntimeException('Simulated DB failure while creating the booking.');
        });

        $response = $this->postJson(
            route('checkout.paypal.capture', ['locale' => self::LOCALE]),
            $this->capturePayload($orderId)
        );

        // El dinero se cobró: NO es el mismo error genérico de "inténtalo de
        // nuevo" que un pago que nunca se movió. Código propio + HTTP 200
        // (no es un error de red: es un estado explícito que el frontend
        // debe leer por `code`, no por status).
        $response->assertOk();
        $this->assertFalse((bool) $response->json('success'));
        $this->assertSame('payment_captured_booking_pending', $response->json('code'));
        $this->assertSame("CAPTURE-{$orderId}", $response->json('reference'));

        // Cero reservas: la transacción de finalizeBookings() revirtió el
        // intento (no quedó un Customer huérfano ni un Booking a medias).
        $this->assertSame(0, Booking::count());

        // Ni la confirmación al cliente ni el aviso interno debieron salir:
        // no hay nada que confirmar todavía.
        Mail::assertNothingSent();
    }

    /** @test */
    public function the_payment_lock_blocks_a_new_paypal_order_without_calling_paypal(): void
    {
        Mail::fake();

        $tour = $this->tour(['price' => 150.00]);
        $this->cart()->add($tour, 1, 0, now()->addDays(10)->toDateString());

        $orderId = $this->createPaypalOrder();
        $this->fakePaypalCapture($orderId, '150.00');

        Booking::creating(function (): void {
            throw new \RuntimeException('Simulated DB failure while creating the booking.');
        });

        $this->postJson(
            route('checkout.paypal.capture', ['locale' => self::LOCALE]),
            $this->capturePayload($orderId)
        )->assertOk();

        // A partir de acá el marcador de bloqueo quedó en sesión. Un segundo
        // intento de PAGAR (refresco, doble clic, volver atrás) no debe
        // siquiera tocar la red de PayPal.
        Http::fake(); // limpia el historial de llamadas para medir SOLO lo que sigue

        $second = $this->postJson(route('checkout.paypal.create', ['locale' => self::LOCALE]));

        $second->assertStatus(409);
        $this->assertSame('payment_pending_manual_review', $second->json('code'));
        $this->assertSame("CAPTURE-{$orderId}", $second->json('reference'));

        Http::assertNothingSent();
    }

    /** @test */
    public function a_failure_mid_cart_rolls_back_all_bookings_and_sends_no_emails(): void
    {
        Mail::fake();
        Http::fake();

        $tourA = $this->tour(['price' => 100.00]);
        $tourB = $this->tour(['price' => 120.00]);
        $tourC = $this->tour(['price' => 140.00]);

        $this->cart()->add($tourA, 1, 0, now()->addDays(10)->toDateString());
        $this->cart()->add($tourB, 1, 0, now()->addDays(11)->toDateString());
        $this->cart()->add($tourC, 1, 0, now()->addDays(12)->toDateString());

        // El SEGUNDO booking del carrito revienta: si finalizeBookings() no
        // estuviera en una transacción, el primero (tourA) ya habría
        // quedado grabado y pagado antes de que esto lanzara.
        $attempts = 0;
        Booking::creating(function () use (&$attempts): void {
            $attempts++;
            if ($attempts === 2) {
                throw new \RuntimeException('Simulated failure on the second booking of the cart.');
            }
        });

        $response = $this->post(route('checkout.process', ['locale' => self::LOCALE]), [
            'customer_name' => 'Carlos Ruiz',
            'customer_email' => 'carlos@example.com',
            'customer_phone' => '+51987000002',
            'travel_date' => now()->addDays(10)->format('Y-m-d'),
            'payment_timing' => 'later',
        ]);

        // processPayment() atrapa el \Throwable y redirige con un error
        // genérico — lo que importa acá es que no quedó NADA grabado.
        $response->assertRedirect();
        $this->assertSame(
            0,
            Booking::count(),
            'La transacción no revirtió: quedaron reservas de un carrito que falló a mitad de camino.'
        );

        // Ningún correo debió salir: un rollback no puede des-enviar uno.
        Mail::assertNothingSent();

        // No se tocó PayPal en el flujo "pagar luego".
        Http::assertNothingSent();
    }

    /** @test */
    public function happy_path_leaves_no_lock_and_pay_later_is_unaffected(): void
    {
        Mail::fake();

        $tour = $this->tour(['price' => 150.00]);
        $this->cart()->add($tour, 1, 0, now()->addDays(10)->toDateString());

        $orderId = $this->createPaypalOrder();
        $this->fakePaypalCapture($orderId, '150.00');

        $response = $this->postJson(
            route('checkout.paypal.capture', ['locale' => self::LOCALE]),
            $this->capturePayload($orderId)
        );

        $response->assertOk();
        $this->assertTrue((bool) $response->json('success'));
        $this->assertSame(1, Booking::count());

        // Sin marcador de bloqueo: una orden nueva en la MISMA sesión debe
        // poder crearse sin problema (camino feliz, nada quedó bloqueado).
        $secondTour = $this->tour(['price' => 80.00]);
        $this->cart()->add($secondTour, 1, 0, now()->addDays(11)->toDateString());

        $newOrderId = $this->createPaypalOrder('ORDER-2');
        $this->assertNotEmpty($newOrderId);

        // Y "pagar luego" en una sesión nueva sigue funcionando exactamente
        // igual que antes de este cambio (ver pay_later_flow_still_creates_
        // a_pending_booking_without_touching_paypal, más abajo, para la
        // cobertura completa de ese flujo).
    }

    // ─────────────────────────────────────────────────────────────
    //  Lote 2026-08-27 (2): hallazgo ALTO de security-engineer — el
    //  bloqueo cubría 1 de 3 puertas alcanzables. Se agregó el mismo
    //  chequeo de PaymentLockService en paypalCaptureOrder() (puerta 2:
    //  cobro doble REAL con dos pestañas) y en processPayment() (puerta 3:
    //  reserva "pagar luego" sobre un carrito ya cobrado), y el panel de
    //  aviso ahora se pinta desde sesión en CartController@index en vez de
    //  depender solo del fetch que lo disparó (sobrevive a un F5).
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function two_tabs_with_separate_live_orders_the_second_cannot_capture_after_the_first_locks_the_session(): void
    {
        Mail::fake();

        $tour = $this->tour(['price' => 100.00]);
        $this->cart()->add($tour, 1, 0, now()->addDays(10)->toDateString());

        // El cliente abre dos pestañas del mismo carrito: cada una crea SU
        // PROPIA orden de PayPal (dos snapshots vivos a la vez son válidos,
        // PAYPAL_SNAPSHOT_LIMIT = 10) y las aprueba en el popup.
        $orderA = $this->createPaypalOrder('ORDER-A');
        $orderB = $this->createPaypalOrder('ORDER-B');

        // Pestaña 1: captura A. El cobro sale bien, pero finalizeBookings()
        // revienta (falla de BD simulada) → se cobró sin reserva: queda el
        // marcador de bloqueo y el snapshot de A se borra.
        $this->fakePaypalCapture($orderA, '100.00');
        Booking::creating(function (): void {
            throw new \RuntimeException('Simulated DB failure while creating the booking.');
        });

        $first = $this->postJson(
            route('checkout.paypal.capture', ['locale' => self::LOCALE]),
            $this->capturePayload($orderA)
        );
        $first->assertOk();
        $this->assertSame('payment_captured_booking_pending', $first->json('code'));
        $this->assertSame(0, Booking::count());

        // Pestaña 2: dispara su propio onApprove para la orden B, DISTINTA
        // de A, con snapshot todavía vivo. Antes de este fix, esto llegaba
        // hasta captureOrder(B) y cobraba una SEGUNDA vez de verdad — el
        // marcador de la pestaña 1 nunca se consultaba acá.
        Http::fake(); // limpia el historial: se mide SOLO lo que sigue

        $second = $this->postJson(
            route('checkout.paypal.capture', ['locale' => self::LOCALE]),
            $this->capturePayload($orderB)
        );

        $second->assertStatus(409);
        $this->assertSame('payment_pending_manual_review', $second->json('code'));

        // Lo que de verdad importa: CERO llamadas a PayPal para B. Ni
        // getOrder ni captureOrder — el guard corta antes de tocar la red.
        Http::assertNothingSent();

        // Tampoco se creó una reserva para B.
        $this->assertSame(0, Booking::count());
    }

    /** @test */
    public function pay_later_is_blocked_when_a_payment_lock_is_active_and_redirects_with_the_manual_review_notice(): void
    {
        Mail::fake();

        $tour = $this->tour(['price' => 150.00]);
        $this->cart()->add($tour, 1, 0, now()->addDays(10)->toDateString());

        $orderId = $this->createPaypalOrder();
        $this->fakePaypalCapture($orderId, '150.00');

        Booking::creating(function (): void {
            throw new \RuntimeException('Simulated DB failure while creating the booking.');
        });

        $this->postJson(
            route('checkout.paypal.capture', ['locale' => self::LOCALE]),
            $this->capturePayload($orderId)
        )->assertOk();

        $this->assertSame(0, Booking::count());

        // El carrito sigue lleno: finalizeBookings() lanzó ANTES de llegar a
        // cart->clear(). El cliente (o el propio operador) intenta resolver
        // "a mano" reservando por "pagar luego" sobre ese mismo carrito.
        Http::fake();

        $response = $this->post(route('checkout.process', ['locale' => self::LOCALE]), [
            'customer_name' => 'Juan Pérez García',
            'customer_email' => 'juan@example.com',
            'customer_phone' => '+51987654321',
            'travel_date' => now()->addDays(10)->format('Y-m-d'),
            'payment_timing' => 'later',
        ]);

        $response->assertRedirectToRoute('cart.index', ['locale' => self::LOCALE]);
        $response->assertSessionHas('error', fn (string $message) => str_contains($message, "CAPTURE-{$orderId}"));

        // Cero reservas nuevas: ni la del pago capturado (ya lo probamos
        // arriba) ni una segunda "pendiente" por pagar luego.
        $this->assertSame(0, Booking::count());

        Http::assertNothingSent();
    }

    /** @test */
    public function the_payment_review_panel_renders_from_session_on_a_plain_cart_page_load(): void
    {
        Mail::fake();

        $tour = $this->tour(['price' => 150.00]);
        $this->cart()->add($tour, 1, 0, now()->addDays(10)->toDateString());

        $orderId = $this->createPaypalOrder();
        $this->fakePaypalCapture($orderId, '150.00');

        Booking::creating(function (): void {
            throw new \RuntimeException('Simulated DB failure while creating the booking.');
        });

        $this->postJson(
            route('checkout.paypal.capture', ['locale' => self::LOCALE]),
            $this->capturePayload($orderId)
        )->assertOk();

        // Un F5 puro: un GET normal al carrito, SIN pasar por ningún fetch
        // de PayPal. El panel debe pintarse igual, porque ahora lee el
        // marcador desde sesión en el servidor (CartController@index), no
        // solo desde el callback de un fetch que ya no va a volver a correr.
        $response = $this->get(route('cart.index', ['locale' => self::LOCALE]));

        $response->assertOk();
        $response->assertSee('showPaymentReviewPanel(', false);
        $response->assertSee("CAPTURE-{$orderId}", false);
    }
}
