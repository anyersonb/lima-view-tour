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
 * Hallazgo 2026-08-27: PayPal rechazó una captura con 422
 * INSTRUMENT_DECLINED (tarjeta rechazada por el banco) y el checkout
 * respondía HTTP 500 con el mismo mensaje genérico que un fallo de
 * servidor ("no pudo completarse, contáctanos"). El comprador perdía la
 * venta porque el mensaje no le decía que probara otra tarjeta.
 *
 * El fix: PayPalService::captureOrder() detecta details[0].issue ===
 * INSTRUMENT_DECLINED en el 422 y lanza PayPalCardDeclinedException;
 * CheckoutController la atrapa ANTES del catch genérico y responde
 * 402 con code=card_declined y el mensaje traducido de lang/*\/booking.php.
 * Cualquier otro fallo de captureOrder() (otro issue, otro status) debe
 * seguir cayendo en el catch genérico sin capture_id: 500 y el mensaje de
 * siempre.
 */
class PaypalCardDeclinedTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALE = 'es';

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

    private function fakePaypalCreate(string $orderId = 'ORDER-1'): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*/v2/checkout/orders' => Http::response(['id' => $orderId], 201),
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

    /**
     * getOrder() (GET, para el chequeo de importe ANTES de capturar) sigue
     * respondiendo 200 con el importe correcto: lo que falla es el POST
     * .../capture, con el 422 + issue que PayPal de verdad manda para una
     * tarjeta rechazada por el banco.
     */
    private function fakePaypalCaptureFailure(string $orderId, string $remoteAmount, ?string $issue, int $status, string $remoteCurrency = 'USD'): void
    {
        $body = $issue === null
            ? ['name' => 'INTERNAL_SERVER_ERROR']
            : [
                'name' => 'UNPROCESSABLE_ENTITY',
                'details' => [
                    ['issue' => $issue, 'description' => 'Capture rejected.'],
                ],
            ];

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            "*/v2/checkout/orders/{$orderId}/capture" => Http::response($body, $status),
            "*/v2/checkout/orders/{$orderId}" => Http::response([
                'purchase_units' => [
                    ['amount' => ['currency_code' => $remoteCurrency, 'value' => $remoteAmount]],
                ],
            ], 200),
        ]);
    }

    /** @test */
    public function a_422_instrument_declined_returns_the_card_declined_code_without_charging_or_booking(): void
    {
        Mail::fake();

        $tour = $this->tour(['price' => 150.00]);
        $this->cart()->add($tour, 1, 0, now()->addDays(10)->toDateString());

        $orderId = $this->createPaypalOrder();
        $this->fakePaypalCaptureFailure($orderId, '150.00', 'INSTRUMENT_DECLINED', 422);

        $response = $this->postJson(
            route('checkout.paypal.capture', ['locale' => self::LOCALE]),
            $this->capturePayload($orderId)
        );

        // 402 Payment Required: el banco rechazó el instrumento, no es un
        // error de servidor (500) ni una validación de la solicitud (422,
        // ya usado arriba en el mismo endpoint para fecha bloqueada / importe
        // que no cuadra).
        $response->assertStatus(402);
        $this->assertFalse((bool) $response->json('success'));
        $this->assertSame('card_declined', $response->json('code'));
        $this->assertSame(__('booking.card_declined'), $response->json('message'));

        // No se movió dinero ni se creó una reserva: el 422 de PayPal llegó
        // ANTES de que existiera capture_id.
        $this->assertSame(0, Booking::count());
        Mail::assertNothingSent();
    }

    /**
     * Contraprueba de que el fix separó los casos y no le cambió el mensaje
     * a "todo lo que falle en captureOrder()": otro fallo (otro issue, u
     * otro status sin cuerpo de PayPal reconocible) debe seguir cayendo en
     * el catch genérico de siempre — 500, sin "code", mensaje genérico.
     */
    /** @test */
    public function a_different_capture_failure_still_returns_the_generic_500_not_card_declined(): void
    {
        Mail::fake();

        $tour = $this->tour(['price' => 150.00]);
        $this->cart()->add($tour, 1, 0, now()->addDays(10)->toDateString());

        $orderId = $this->createPaypalOrder();
        // Sin "details[0].issue" reconocible (caída genérica de PayPal /
        // nuestra), NO es INSTRUMENT_DECLINED.
        $this->fakePaypalCaptureFailure($orderId, '150.00', null, 500);

        $response = $this->postJson(
            route('checkout.paypal.capture', ['locale' => self::LOCALE]),
            $this->capturePayload($orderId)
        );

        $response->assertStatus(500);
        $this->assertFalse((bool) $response->json('success'));
        $this->assertNull($response->json('code'));
        $this->assertSame(
            'El pago no pudo completarse. Por favor inténtalo de nuevo o contáctanos.',
            $response->json('message')
        );

        $this->assertSame(0, Booking::count());
        Mail::assertNothingSent();
    }

    /**
     * Cierra el punto ciego de la contraprueba anterior (detectado por
     * security-engineer, 2026-08-30/31): esa mueve DOS variables a la vez
     * (status 500 Y un cuerpo sin "details"), así que detecta un guard
     * ensanchado a "cualquier fallo de captureOrder()" pero NO detectaría uno
     * ensanchado a "cualquier 422 con details" — un ensanchamiento que
     * seguiría pasando esa contraprueba porque nunca prueba un 422 real con
     * detalles reales que no sea INSTRUMENT_DECLINED.
     *
     * Acá el status SÍ es 422 y SÍ hay "details[0].issue" (como el real), pero
     * es un issue vecino de PayPal (ORDER_ALREADY_CAPTURED: la orden ya se
     * capturó antes, no un rechazo del banco). Debe seguir cayendo en el
     * catch genérico de siempre.
     */
    /** @test */
    public function a_422_with_a_different_issue_still_returns_the_generic_500_not_card_declined(): void
    {
        Mail::fake();

        $tour = $this->tour(['price' => 150.00]);
        $this->cart()->add($tour, 1, 0, now()->addDays(10)->toDateString());

        $orderId = $this->createPaypalOrder();
        $this->fakePaypalCaptureFailure($orderId, '150.00', 'ORDER_ALREADY_CAPTURED', 422);

        $response = $this->postJson(
            route('checkout.paypal.capture', ['locale' => self::LOCALE]),
            $this->capturePayload($orderId)
        );

        $response->assertStatus(500);
        $this->assertFalse((bool) $response->json('success'));
        $this->assertNull($response->json('code'));
        $this->assertSame(
            'El pago no pudo completarse. Por favor inténtalo de nuevo o contáctanos.',
            $response->json('message')
        );

        $this->assertSame(0, Booking::count());
        Mail::assertNothingSent();
    }
}
