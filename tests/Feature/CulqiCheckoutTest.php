<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmed;
use App\Mail\BookingNotificationAdmin;
use App\Models\Booking;
use App\Models\Tour;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Cobro con tarjeta vía Culqi Checkout v4: el front manda el token y los
 * datos del cliente; el servidor recalcula el monto del carrito, crea el
 * cargo en api.culqi.com y, si se aprueba, las reservas pagadas.
 */
class CulqiCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALE = 'es';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.culqi.enabled' => true,
            'services.culqi.public_key' => 'pk_test_dummy',
            'services.culqi.secret_key' => 'sk_test_dummy',
            'services.culqi.api_url' => 'https://api.culqi.com/v2',
        ]);
    }

    private function cart(): CartService
    {
        return app(CartService::class);
    }

    private function addTour(float $price = 150.00): Tour
    {
        $tour = Tour::factory()->create(['price' => $price, 'is_published' => true]);
        $this->cart()->add($tour, 2, 1, now()->addDays(10)->format('Y-m-d'));

        return $tour;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'culqi_token' => 'tkn_test_abc123',
            'customer_name' => 'Juan Pérez García',
            'customer_email' => 'juan@example.com',
            'customer_phone' => '+51987654321',
            'travel_date' => now()->addDays(15)->format('Y-m-d'),
            'accept_terms' => '1',
        ], $overrides);
    }

    private function url(): string
    {
        return route('checkout.culqi.charge', ['locale' => self::LOCALE]);
    }

    private function fakeApproved(string $id = 'chr_test_ok1'): void
    {
        Http::fake([
            'api.culqi.com/v2/charges' => Http::response([
                'object' => 'charge',
                'id' => $id,
                'currency_code' => 'USD',
                'outcome' => ['type' => 'venta_exitosa', 'code' => 'AUT0000'],
            ], 201),
        ]);
    }

    public function test_approved_charge_creates_paid_bookings_sends_mails_and_clears_cart(): void
    {
        Mail::fake();
        $this->addTour(150.00);
        $this->fakeApproved('chr_test_ok1');

        $response = $this->postJson($this->url(), $this->payload());

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertStringContainsString('/checkout/gracias', $response->json('redirect'));

        $booking = Booking::where('payment_reference', 'chr_test_ok1')->first();
        $this->assertNotNull($booking);
        $this->assertSame('culqi', $booking->payment_method);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('juan@example.com', $booking->customer_email);

        Mail::assertSent(BookingConfirmed::class, fn ($m) => $m->hasTo('juan@example.com'));
        Mail::assertSent(BookingNotificationAdmin::class);
        $this->assertTrue($this->cart()->items()->isEmpty());
    }

    public function test_amount_is_recalculated_on_the_server_and_client_amount_is_ignored(): void
    {
        Mail::fake();
        // 2 adultos + 1 niño: el total real sale del carrito en servidor.
        $this->addTour(150.00);
        $expectedCents = (int) round($this->cart()->total() * 100);
        $this->fakeApproved();

        $this->postJson($this->url(), $this->payload([
            'amount' => 1,
            'total' => 0.01,
            'currency_code' => 'PEN',
        ]))->assertOk();

        Http::assertSent(function ($request) use ($expectedCents) {
            return str_contains($request->url(), '/charges')
                && $request['amount'] === $expectedCents
                && $request['currency_code'] === 'USD'
                && $request['source_id'] === 'tkn_test_abc123'
                && $request['email'] === 'juan@example.com';
        });
        $this->assertGreaterThan(100, $expectedCents);
    }

    public function test_declined_card_creates_no_booking_and_returns_localized_message(): void
    {
        Mail::fake();
        $this->addTour();
        Http::fake([
            'api.culqi.com/v2/charges' => Http::response([
                'object' => 'error',
                'type' => 'card_error',
                'charge_id' => 'chr_test_dec1',
                'code' => 'card_declined',
                'decline_code' => 'insufficient_funds',
                'merchant_message' => 'The card has insufficient funds. SECRET-INTERNAL',
                'user_message' => 'La tarjeta no tiene fondos suficientes.',
            ], 400),
        ]);

        $response = $this->postJson($this->url(), $this->payload());

        $response->assertStatus(402)->assertJson([
            'success' => false,
            'code' => 'card_declined',
            'message' => __('checkout_culqi.declined.insufficient_funds'),
        ]);
        $this->assertStringNotContainsString('SECRET-INTERNAL', $response->getContent());
        $this->assertSame(0, Booking::count());
        Mail::assertNothingSent();
        // El carrito sigue intacto para reintentar.
        $this->assertFalse($this->cart()->items()->isEmpty());
    }

    public function test_bank_denied_operation_type_is_also_a_card_decline_not_a_500(): void
    {
        $this->addTour();
        // Forma REAL que devuelve el sandbox de Culqi (tarjeta de fondos insuficientes).
        Http::fake([
            'api.culqi.com/v2/charges' => Http::response([
                'object' => 'error',
                'type' => 'operacion_denegada',
                'charge_id' => 'chr_test_xxx',
                'code' => 'DNGE0015',
                'decline_code' => 'insufficient_funds',
                'user_message' => 'Su tarjeta no tiene fondos suficientes.',
            ], 400),
        ]);

        $this->postJson($this->url(), $this->payload())
            ->assertStatus(402)
            ->assertJson(['code' => 'card_declined', 'message' => __('checkout_culqi.declined.insufficient_funds')]);
        $this->assertSame(0, Booking::count());
    }

    public function test_charge_that_is_not_approved_does_not_book(): void
    {
        $this->addTour();
        Http::fake([
            'api.culqi.com/v2/charges' => Http::response([
                'object' => 'charge',
                'id' => 'chr_test_rev1',
                'action_code' => 'REVIEW',
                'outcome' => ['type' => 'rechazada'],
            ], 201),
        ]);

        $this->postJson($this->url(), $this->payload())->assertStatus(402);
        $this->assertSame(0, Booking::count());
    }

    public function test_culqi_server_error_is_a_500_without_booking(): void
    {
        $this->addTour();
        Http::fake(['api.culqi.com/v2/charges' => Http::response(['object' => 'error', 'type' => 'api_error'], 500)]);

        $this->postJson($this->url(), $this->payload())->assertStatus(500)->assertJson(['success' => false]);
        $this->assertSame(0, Booking::count());
    }

    public function test_paid_but_booking_failure_locks_the_session_and_does_not_invite_retry(): void
    {
        $this->addTour();
        $this->fakeApproved('chr_test_lock1');
        // Fuerza el fallo de creación de reservas DESPUÉS del cobro.
        Booking::creating(function () {
            throw new \RuntimeException('boom');
        });

        $response = $this->postJson($this->url(), $this->payload());

        $response->assertOk()->assertJson([
            'success' => false,
            'code' => 'payment_captured_booking_pending',
            'reference' => 'chr_test_lock1',
        ]);

        // Segundo intento: bloqueado, sin llamar otra vez a Culqi.
        Http::fake();
        $this->postJson($this->url(), $this->payload(['culqi_token' => 'tkn_test_other']))
            ->assertStatus(409)
            ->assertJson(['code' => 'payment_pending_manual_review']);
        Http::assertNothingSent();
    }

    public function test_validation_requires_token_terms_and_valid_customer(): void
    {
        $this->addTour();
        Http::fake();

        $this->postJson($this->url(), $this->payload(['culqi_token' => '', 'accept_terms' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['culqi_token', 'accept_terms']);

        $this->postJson($this->url(), $this->payload(['customer_email' => 'nope', 'customer_phone' => '12']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_email', 'customer_phone']);

        $this->postJson($this->url(), $this->payload(['culqi_token' => 'tkn bad;<script>']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['culqi_token']);

        Http::assertNothingSent();
    }

    public function test_empty_cart_is_rejected_without_calling_culqi(): void
    {
        Http::fake();

        $this->postJson($this->url(), $this->payload())->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_without_keys_the_endpoint_is_unavailable(): void
    {
        config(['services.culqi.secret_key' => null]);
        $this->addTour();
        Http::fake();

        $this->postJson($this->url(), $this->payload())->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_cart_shows_culqi_block_only_when_public_key_is_configured(): void
    {
        $this->addTour();

        $with = $this->get(route('cart.index', ['locale' => self::LOCALE]));
        $with->assertOk();
        $with->assertSee('id="co-pay-culqi"', false);
        $with->assertSee('https://checkout.culqi.com/js/v4', false);

        config(['services.culqi.public_key' => null]);
        $without = $this->get(route('cart.index', ['locale' => self::LOCALE]));
        $without->assertOk();
        $without->assertDontSee('id="co-pay-culqi"', false);
        $without->assertDontSee('https://checkout.culqi.com/js/v4', false);
    }

    /**
     * Prod tiene llaves pk_test/sk_test en .env: sin CULQI_ENABLED=true no debe
     * aparecer el bloque ni cobrarse nada (evita "pagos" de sandbox de clientes reales).
     */
    public function test_with_keys_but_disabled_flag_nothing_is_shown_or_charged(): void
    {
        config(['services.culqi.enabled' => false]);
        $this->addTour();
        Http::fake();

        $page = $this->get(route('cart.index', ['locale' => self::LOCALE]));
        $page->assertOk();
        $page->assertDontSee('id="co-pay-culqi"', false);
        $page->assertDontSee('https://checkout.culqi.com/js/v4', false);

        $this->postJson($this->url(), $this->payload())->assertStatus(503);
        Http::assertNothingSent();
    }
}
