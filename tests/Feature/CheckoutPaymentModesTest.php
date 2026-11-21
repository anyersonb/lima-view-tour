<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Tour;
use App\Services\BookingCreationService;
use App\Services\CartService;
use App\Support\BookingCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Checkout por modos (decisión del cliente, 2026-11-21): full (100% ahora),
 * deposit (50% REAL ahora, resto después) y later (sin cobro). El front solo
 * manda el MODO; el monto cobrado sale siempre del carrito en servidor.
 */
class CheckoutPaymentModesTest extends TestCase
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

    /** 2 adultos + 1 niño a $150 = $450 de total. */
    private function addTour(float $price = 150.00): Tour
    {
        $tour = Tour::factory()->create(['price' => $price, 'is_published' => true]);
        $this->cart()->add($tour, 2, 1, now()->addDays(10)->format('Y-m-d'));

        return $tour;
    }

    private function culqiPayload(array $overrides = []): array
    {
        return array_merge([
            'culqi_token' => 'tkn_test_abc123',
            'customer_name' => 'Juan Pérez García',
            'customer_email' => 'juan@example.com',
            'customer_phone' => '+51987654321',
            'accept_terms' => '1',
        ], $overrides);
    }

    private function culqiUrl(): string
    {
        return route('checkout.culqi.charge', ['locale' => self::LOCALE]);
    }

    private function fakeCulqiApproved(string $id = 'chr_test_ok1'): void
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

    // ─────────────────────────────────────────────────────────────
    //  Culqi: 100% / 50%
    // ─────────────────────────────────────────────────────────────

    public function test_culqi_full_charges_the_whole_total_and_marks_the_booking_paid(): void
    {
        Mail::fake();
        $this->addTour();
        $this->fakeCulqiApproved('chr_full');

        $this->postJson($this->culqiUrl(), $this->culqiPayload(['payment_mode' => 'full']))
            ->assertOk()->assertJson(['success' => true]);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/charges')
            && $r['amount'] === 45000
            && $r['metadata']['payment_mode'] === 'full'
            && $r['metadata']['charged_now'] === '450.00'
            && $r['metadata']['balance'] === '0.00');

        $b = Booking::where('payment_reference', 'chr_full')->firstOrFail();
        $this->assertSame('paid', $b->payment_status);
        $this->assertSame('confirmed', $b->status);
        $this->assertSame('full', $b->payment_plan);
        $this->assertEquals(450.00, (float) $b->amount_paid);
        $this->assertEquals(0.00, (float) $b->balance_due);
        $this->assertNull($b->balance_due_at);
    }

    public function test_culqi_without_payment_mode_behaves_as_full_for_the_previous_front(): void
    {
        Mail::fake();
        $this->addTour();
        $this->fakeCulqiApproved('chr_legacy');

        $this->postJson($this->culqiUrl(), $this->culqiPayload())->assertOk();

        Http::assertSent(fn ($r) => str_contains($r->url(), '/charges') && $r['amount'] === 45000);
        $this->assertSame('paid', Booking::where('payment_reference', 'chr_legacy')->value('payment_status'));
    }

    public function test_culqi_deposit_really_charges_half_and_leaves_the_booking_partial(): void
    {
        Mail::fake();
        $tour = $this->addTour();
        $this->fakeCulqiApproved('chr_dep');

        $this->postJson($this->culqiUrl(), $this->culqiPayload(['payment_mode' => 'deposit']))
            ->assertOk()->assertJson(['success' => true]);

        // Culqi recibe la MITAD del total (22500 centavos), no el total.
        Http::assertSent(fn ($r) => str_contains($r->url(), '/charges')
            && $r['amount'] === 22500
            && $r['metadata']['payment_mode'] === 'deposit'
            && $r['metadata']['order_total'] === '450.00'
            && $r['metadata']['charged_now'] === '225.00'
            && $r['metadata']['balance'] === '225.00');

        $b = Booking::where('payment_reference', 'chr_dep')->firstOrFail();
        $this->assertSame('partial', $b->payment_status, 'El 50% no puede marcar el pedido como pagado.');
        $this->assertSame('confirmed', $b->status);
        $this->assertSame('deposit', $b->payment_plan);
        $this->assertSame('culqi', $b->payment_method);
        $this->assertEquals(225.00, (float) $b->amount_paid);
        $this->assertEquals(225.00, (float) $b->balance_due);
        $this->assertEquals(450.00, (float) $b->total_price);

        // balance_due_at = salida del tour - 48 h (instante absoluto).
        $expected = BookingCalendar::departureAt(now()->addDays(10)->format('Y-m-d'), $tour)->subHours(48);
        $this->assertSame($expected->getTimestamp(), $b->balance_due_at->getTimestamp());
        $this->assertTrue($b->hasPendingBalance());
    }

    public function test_culqi_deposit_with_two_tours_splits_paid_and_balance_per_booking(): void
    {
        Mail::fake();
        $a = Tour::factory()->create(['price' => 100.00, 'is_published' => true]);
        $c = Tour::factory()->create(['price' => 50.00, 'is_published' => true]);
        $this->cart()->add($a, 1, 0, now()->addDays(10)->format('Y-m-d'));  // 100
        $this->cart()->add($c, 1, 0, now()->addDays(12)->format('Y-m-d'));  // 50 => total 150, adelanto 75
        $this->fakeCulqiApproved('chr_multi');

        $this->postJson($this->culqiUrl(), $this->culqiPayload(['payment_mode' => 'deposit']))->assertOk();

        Http::assertSent(fn ($r) => str_contains($r->url(), '/charges') && $r['amount'] === 7500);

        $bookings = Booking::where('payment_reference', 'chr_multi')->orderBy('id')->get();
        $this->assertCount(2, $bookings);
        $this->assertEquals(75.00, (float) $bookings->sum('amount_paid'));
        $this->assertEquals(75.00, (float) $bookings->sum('balance_due'));
        $this->assertEquals([50.00, 25.00], $bookings->map(fn ($b) => (float) $b->amount_paid)->all());
        $this->assertSame(['partial', 'partial'], $bookings->pluck('payment_status')->all());
    }

    public function test_culqi_ignores_any_amount_sent_by_the_client(): void
    {
        Mail::fake();
        $this->addTour();
        $this->fakeCulqiApproved();

        $this->postJson($this->culqiUrl(), $this->culqiPayload([
            'payment_mode' => 'deposit',
            'amount' => 1,
            'due' => 0.01,
            'total' => 0.01,
            'currency_code' => 'PEN',
        ]))->assertOk();

        Http::assertSent(fn ($r) => str_contains($r->url(), '/charges')
            && $r['amount'] === 22500
            && $r['currency_code'] === 'USD');
    }

    public function test_culqi_rejects_later_and_garbage_modes_without_charging(): void
    {
        Mail::fake();
        $this->addTour();
        Http::fake();

        $this->postJson($this->culqiUrl(), $this->culqiPayload(['payment_mode' => 'later']))
            ->assertStatus(422)->assertJsonValidationErrors('payment_mode');
        $this->postJson($this->culqiUrl(), $this->culqiPayload(['payment_mode' => 'half']))
            ->assertStatus(422)->assertJsonValidationErrors('payment_mode');

        Http::assertNothingSent();
        $this->assertSame(0, Booking::count());
    }

    public function test_culqi_does_not_require_a_single_travel_date_field(): void
    {
        Mail::fake();
        $this->addTour(); // la fecha vive en el ítem del carrito
        $this->fakeCulqiApproved();

        // Sin travel_date en el body: el servidor usa la del carrito.
        $payload = $this->culqiPayload(['payment_mode' => 'full']);
        $this->assertArrayNotHasKey('travel_date', $payload);

        $this->postJson($this->culqiUrl(), $payload)->assertOk()->assertJson(['success' => true]);
    }

    public function test_culqi_paid_but_booking_fails_stores_charged_amount_and_mode_in_the_payment_lock(): void
    {
        Mail::fake();
        $this->addTour();
        $this->fakeCulqiApproved('chr_lock');

        $this->mock(BookingCreationService::class, function ($mock) {
            $mock->shouldReceive('createBookings')->andThrow(new \RuntimeException('db down'));
        });

        $this->postJson($this->culqiUrl(), $this->culqiPayload(['payment_mode' => 'deposit']))
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 'payment_captured_booking_pending', 'reference' => 'chr_lock']);

        $lock = session('payment_captured_pending');
        $this->assertNotNull($lock);
        $this->assertSame('deposit', $lock['payment_mode']);
        $this->assertEquals(225.00, (float) $lock['amount_charged']);
        $this->assertEquals(450.00, (float) $lock['snapshot']['total']);
        $this->assertEquals(225.00, (float) $lock['snapshot']['balance']);
        $this->assertSame(0, Booking::count());
    }

    // ─────────────────────────────────────────────────────────────
    //  PayPal: 100% / 50%
    // ─────────────────────────────────────────────────────────────

    private function fakePaypalCreate(string $orderId): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*/v2/checkout/orders' => Http::response(['id' => $orderId], 201),
        ]);
    }

    private function fakePaypalCapture(string $orderId, string $remoteAmount): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            "*/v2/checkout/orders/{$orderId}/capture" => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => "CAPTURE-{$orderId}"]]]]],
            ], 200),
            "*/v2/checkout/orders/{$orderId}" => Http::response([
                'purchase_units' => [['amount' => ['currency_code' => 'USD', 'value' => $remoteAmount]]],
            ], 200),
        ]);
    }

    private function paypalCreate(string $orderId, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        $this->fakePaypalCreate($orderId);

        return $this->postJson(route('checkout.paypal.create', ['locale' => self::LOCALE]), array_merge([
            'customer_name' => 'Juan Pérez García',
            'customer_email' => 'juan@example.com',
            'customer_phone' => '+51987654321',
            'accept_terms' => true,
        ], $overrides));
    }

    private function paypalCapture(string $orderId): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('checkout.paypal.capture', ['locale' => self::LOCALE]), [
            'orderID' => $orderId,
            'customer_name' => 'Juan Pérez García',
            'customer_email' => 'juan@example.com',
            'customer_phone' => '+51987654321',
        ]);
    }

    public function test_paypal_full_creates_an_order_for_the_whole_total(): void
    {
        Mail::fake();
        $this->addTour();

        $this->paypalCreate('ORDER-FULL', ['payment_mode' => 'full'])->assertOk()->assertJson(['id' => 'ORDER-FULL']);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v2/checkout/orders')
            && $r['purchase_units'][0]['amount']['value'] === '450.00');

        $snap = session('paypal_orders')['ORDER-FULL'];
        $this->assertSame('full', $snap['mode']);
        $this->assertEquals(450.00, (float) $snap['due']);
        $this->assertEquals(0.00, (float) $snap['balance']);

        $this->fakePaypalCapture('ORDER-FULL', '450.00');
        $this->paypalCapture('ORDER-FULL')->assertOk()->assertJson(['success' => true]);

        $b = Booking::where('payment_reference', 'CAPTURE-ORDER-FULL')->firstOrFail();
        $this->assertSame('paid', $b->payment_status);
        $this->assertSame('full', $b->payment_plan);
    }

    public function test_paypal_deposit_charges_half_and_the_capture_leaves_the_booking_partial(): void
    {
        Mail::fake();
        $this->addTour();

        $this->paypalCreate('ORDER-DEP', ['payment_mode' => 'deposit', 'amount' => 1, 'total' => 1])
            ->assertOk()->assertJson(['id' => 'ORDER-DEP']);

        // PayPal recibe la mitad aunque el cliente mande otro monto.
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v2/checkout/orders')
            && $r['purchase_units'][0]['amount']['value'] === '225.00');

        $snap = session('paypal_orders')['ORDER-DEP'];
        $this->assertSame('deposit', $snap['mode']);
        $this->assertEquals(450.00, (float) $snap['total']);
        $this->assertEquals(225.00, (float) $snap['due']);
        $this->assertEquals(225.00, (float) $snap['balance']);

        $this->fakePaypalCapture('ORDER-DEP', '225.00');
        $this->paypalCapture('ORDER-DEP')->assertOk()->assertJson(['success' => true]);

        $b = Booking::where('payment_reference', 'CAPTURE-ORDER-DEP')->firstOrFail();
        $this->assertSame('partial', $b->payment_status);
        $this->assertSame('confirmed', $b->status);
        $this->assertSame('deposit', $b->payment_plan);
        $this->assertEquals(225.00, (float) $b->amount_paid);
        $this->assertEquals(225.00, (float) $b->balance_due);
        $this->assertNotNull($b->balance_due_at);
    }

    public function test_paypal_deposit_capture_rejects_an_order_whose_remote_amount_is_the_full_total(): void
    {
        Mail::fake();
        $this->addTour();
        $this->paypalCreate('ORDER-TAMPER', ['payment_mode' => 'deposit'])->assertOk();

        // PayPal tiene registrados 450.00 pero el snapshot espera 225.00 => no se captura.
        $this->fakePaypalCapture('ORDER-TAMPER', '450.00');
        $this->paypalCapture('ORDER-TAMPER')->assertStatus(422);

        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/capture'));
        $this->assertSame(0, Booking::count());
    }

    public function test_paypal_rejects_later_mode(): void
    {
        $this->addTour();
        Http::fake();

        $this->paypalCreate('ORDER-LATER', ['payment_mode' => 'later'])
            ->assertStatus(422)->assertJsonValidationErrors('payment_mode');
    }

    // ─────────────────────────────────────────────────────────────
    //  Later (sin cobro)
    // ─────────────────────────────────────────────────────────────

    public function test_later_mode_books_without_charging(): void
    {
        Mail::fake();
        $this->addTour();
        Http::fake();

        $this->post(route('checkout.process', ['locale' => self::LOCALE]), [
            'payment_mode' => 'later',
            'customer_name' => 'Juan Pérez García',
            'customer_email' => 'juan@example.com',
            'customer_phone' => '+51987654321',
            'accept_terms' => '1',
        ])->assertRedirectToRoute('checkout.thanks', ['locale' => self::LOCALE]);

        Http::assertNothingSent();

        $b = Booking::firstOrFail();
        $this->assertSame('pending', $b->payment_status);
        $this->assertSame('pending', $b->status);
        $this->assertSame('later', $b->payment_plan);
        $this->assertSame('pay_later', $b->payment_method);
        $this->assertEquals(0.00, (float) $b->amount_paid);
        $this->assertEquals(450.00, (float) $b->balance_due);
        $this->assertNull($b->balance_due_at);
    }

    public function test_process_route_refuses_a_charging_mode(): void
    {
        Mail::fake();
        $this->addTour();

        $this->post(route('checkout.process', ['locale' => self::LOCALE]), [
            'payment_mode' => 'deposit',
            'customer_name' => 'Juan Pérez García',
            'customer_email' => 'juan@example.com',
            'customer_phone' => '+51987654321',
            'accept_terms' => '1',
        ])->assertSessionHas('error');

        $this->assertSame(0, Booking::count());
    }

    // ─────────────────────────────────────────────────────────────
    //  Datos para el front + GA4
    // ─────────────────────────────────────────────────────────────

    public function test_summary_endpoint_exposes_amounts_per_mode_and_ga4_payloads(): void
    {
        $this->addTour();

        $json = $this->getJson(route('checkout.summary', ['locale' => self::LOCALE]))
            ->assertOk()
            ->json();

        $this->assertEquals(450.00, $json['total']);
        $this->assertSame('deposit', $json['default_mode']);
        $this->assertSame(45000, $json['modes']['full']['due_cents']);
        $this->assertSame(22500, $json['modes']['deposit']['due_cents']);
        $this->assertEquals(225.00, $json['modes']['deposit']['balance']);
        $this->assertSame(0, $json['modes']['later']['due_cents']);
        $this->assertEquals(450.00, $json['analytics']['begin_checkout']['value']);
        $this->assertSame(['culqi', 'paypal'], $json['analytics']['add_payment_info']['payment_type_values']);
        $this->assertSame(['full', 'deposit', 'later'], $json['analytics']['add_payment_info']['payment_mode_values']);
        $this->assertEquals(225.00, $json['analytics']['add_payment_info']['value_by_mode']['deposit']);
        $this->assertTrue($json['methods']['culqi']);
    }

    public function test_cart_page_exposes_the_checkout_variable_to_the_view(): void
    {
        $this->addTour();

        $response = $this->get(route('cart.index', ['locale' => self::LOCALE]))->assertOk();
        $checkout = $response->viewData('checkout');

        $this->assertSame(22500, $checkout['modes']['deposit']['due_cents']);
        $this->assertStringContainsString('/checkout/culqi/charge', $checkout['endpoints']['culqi_charge']);
        $this->assertSame('pk_test_dummy', $checkout['culqi_public_key']);
    }

    public function test_purchase_event_value_is_the_amount_actually_charged(): void
    {
        Mail::fake();
        $this->addTour();
        $this->fakeCulqiApproved('chr_ga4');

        $this->postJson($this->culqiUrl(), $this->culqiPayload(['payment_mode' => 'deposit']))->assertOk();

        $thanks = $this->get(route('checkout.thanks', ['locale' => self::LOCALE]))->assertOk();
        $payload = $thanks->viewData('conversionPayload');

        $this->assertSame('purchase', $payload['event']);
        $this->assertEquals(225.00, $payload['data']['value'], 'purchase.value = lo realmente cobrado (50%), no el total.');
        $this->assertSame('culqi', $payload['data']['payment_type']);
        $this->assertSame('deposit', $payload['data']['payment_mode']);
        $this->assertEquals(225.00, $payload['data']['balance_pending']);
        $this->assertSame('Purchase', $payload['fb']);
    }

    public function test_purchase_event_for_full_payment_uses_the_total(): void
    {
        Mail::fake();
        $this->addTour();
        $this->fakeCulqiApproved('chr_ga4_full');

        $this->postJson($this->culqiUrl(), $this->culqiPayload(['payment_mode' => 'full']))->assertOk();

        $payload = $this->get(route('checkout.thanks', ['locale' => self::LOCALE]))->viewData('conversionPayload');

        $this->assertSame('purchase', $payload['event']);
        $this->assertEquals(450.00, $payload['data']['value']);
        $this->assertSame('full', $payload['data']['payment_mode']);
    }
}
