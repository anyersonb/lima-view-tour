<?php

namespace Tests\Feature;

use App\Exceptions\BalanceLinkException;
use App\Mail\BookingBalanceLink;
use App\Mail\BookingBalancePaid;
use App\Models\Booking;
use App\Models\PaymentLink;
use App\Models\Tour;
use App\Services\BalancePaymentService;
use App\Support\BookingCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Saldo de reservas con adelanto: link de pago generado desde el admin
 * (PaymentLink purpose=balance, ligado a la reserva), expiración 48 h antes
 * del tour y pago por PayPal o Culqi.
 *
 * Regresión clave: antes un link con booking_id se trataba como "reserva ya
 * creada" y captureOrder devolvía éxito SIN marcar el pedido como pagado.
 */
class BalancePaymentLinkTest extends TestCase
{
    use RefreshDatabase;

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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    /** Reserva con adelanto del 50% cobrado: total 200, pagado 100, saldo 100. */
    private function depositBooking(array $overrides = [], ?string $travelDate = null): Booking
    {
        $tour = Tour::factory()->create(['price' => 100.00, 'is_published' => true, 'departure_time' => '09:00']);

        return Booking::factory()->create(array_merge([
            'tour_id' => $tour->id,
            'tour_title_snapshot' => 'City Tour',
            'travel_date' => $travelDate ?? now()->addDays(10)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'unit_price' => 100,
            'total_price' => 200,
            'status' => 'confirmed',
            'payment_status' => 'partial',
            'payment_plan' => 'deposit',
            'payment_method' => 'culqi',
            'payment_reference' => 'chr_deposit_1',
            'amount_paid' => 100,
            'balance_due' => 100,
            'customer_name' => 'Ana Torres',
            'customer_email' => 'ana@example.com',
            'customer_phone' => '987654321',
            'locale' => 'es',
        ], $overrides));
    }

    private function service(): BalancePaymentService
    {
        return app(BalancePaymentService::class);
    }

    private function fakePaypalCreate(string $orderId = 'ORDER-BAL'): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*/v2/checkout/orders' => Http::response(['id' => $orderId], 201),
        ]);
    }

    private function fakePaypalCapture(string $orderId, float $amount, string $captureId = 'CAPTURE-BAL'): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            "*/v2/checkout/orders/{$orderId}/capture" => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => $captureId]]]]],
            ], 200),
            "*/v2/checkout/orders/{$orderId}" => Http::response([
                'id' => $orderId,
                'purchase_units' => [['amount' => ['currency_code' => 'USD', 'value' => number_format($amount, 2, '.', '')]]],
            ], 200),
        ]);
    }

    private function createOrderFor(PaymentLink $link, string $orderId = 'ORDER-BAL'): \Illuminate\Testing\TestResponse
    {
        $this->fakePaypalCreate($orderId);

        return $this->postJson(route('payment-links.paypal.create', ['code' => $link->code]));
    }

    private function capture(PaymentLink $link, string $orderId = 'ORDER-BAL', array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('payment-links.paypal.capture', ['code' => $link->code]), array_merge([
            'orderID' => $orderId,
        ], $body));
    }

    // ─────────────────────────────────────────────────────────────
    //  Generación del link
    // ─────────────────────────────────────────────────────────────

    public function test_admin_generates_a_balance_link_tied_to_the_booking_and_emails_it(): void
    {
        Mail::fake();
        $booking = $this->depositBooking();

        $link = $this->service()->createLink($booking, null, true);

        $this->assertSame('balance', $link->purpose);
        $this->assertSame($booking->id, $link->booking_id);
        $this->assertSame($booking->tour_id, $link->tour_id);
        $this->assertSame('pending', $link->status);
        $this->assertEquals(100.00, (float) $link->amount);
        $this->assertSame('es', $link->locale);
        $this->assertSame(40, strlen($link->code));

        // Vence 48 h antes de la salida del tour.
        $expected = BookingCalendar::departureAt($booking->travel_date->toDateString(), $booking->tour)->subHours(48);
        $this->assertSame($expected->getTimestamp(), $link->expires_at->getTimestamp());

        Mail::assertSent(BookingBalanceLink::class, function (BookingBalanceLink $m) use ($link) {
            return $m->hasTo('ana@example.com') && $m->link->is($link) && ! $m->isReminder;
        });
    }

    public function test_balance_link_email_renders_the_link_amount_deadline_and_refund_rule(): void
    {
        $booking = $this->depositBooking();
        $link = $this->service()->createLink($booking, null, false);

        $html = (new BookingBalanceLink($booking->fresh(), $link))->render();

        $this->assertStringContainsString($link->url, $html);
        $this->assertStringContainsString('US$100.00', $html);
        $this->assertStringContainsString('más de 24 horas', $html);
        $this->assertStringContainsString($booking->reference, $html);
    }

    public function test_generating_twice_reuses_the_open_link(): void
    {
        Mail::fake();
        $booking = $this->depositBooking();

        $a = $this->service()->createLink($booking, null, false);
        $b = $this->service()->createLink($booking, null, false);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, PaymentLink::where('booking_id', $booking->id)->count());
    }

    public function test_a_changed_balance_cancels_the_old_link_and_creates_a_new_one(): void
    {
        $booking = $this->depositBooking();
        $old = $this->service()->createLink($booking, null, false);

        $booking->update(['balance_due' => 80]);
        $new = $this->service()->createLink($booking->fresh(), null, false);

        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('cancelled', $old->fresh()->status);
        $this->assertEquals(80.00, (float) $new->amount);
    }

    public function test_cannot_generate_a_link_less_than_48_hours_before_the_tour(): void
    {
        // Ahora: 10-dic 12:00 Lima; tour 11-dic 09:00 => límite (9-dic 09:00) ya pasó.
        Carbon::setTestNow(Carbon::parse('2026-12-10 12:00:00', 'America/Lima'));
        $booking = $this->depositBooking([], '2026-12-11');

        $this->expectException(BalanceLinkException::class);
        $this->expectExceptionMessage('menos de 48 horas');

        $this->service()->createLink($booking, null, false);
    }

    public function test_cannot_generate_a_link_for_a_booking_without_pending_balance(): void
    {
        $paid = $this->depositBooking(['payment_status' => 'paid', 'balance_due' => 0, 'amount_paid' => 200]);

        $this->expectException(BalanceLinkException::class);
        $this->service()->createLink($paid, null, false);
    }

    public function test_cannot_generate_a_link_for_a_cancelled_booking(): void
    {
        $cancelled = $this->depositBooking(['status' => 'cancelled']);

        $this->expectException(BalanceLinkException::class);
        $this->service()->createLink($cancelled, null, false);
    }

    // ─────────────────────────────────────────────────────────────
    //  Expiración
    // ─────────────────────────────────────────────────────────────

    public function test_link_expires_48_hours_before_the_tour(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-01 10:00:00', 'America/Lima'));
        $booking = $this->depositBooking([], '2026-12-10'); // salida 10-dic 09:00 => vence 8-dic 09:00
        $link = $this->service()->createLink($booking, null, false);

        // 7-dic 20:00: todavía vigente.
        Carbon::setTestNow(Carbon::parse('2026-12-07 20:00:00', 'America/Lima'));
        $this->assertTrue($link->fresh()->isUsable());
        $this->fakePaypalCreate();
        $this->postJson(route('payment-links.paypal.create', ['code' => $link->code]))->assertOk();

        // 8-dic 09:01: venció (48 h antes del tour).
        Carbon::setTestNow(Carbon::parse('2026-12-08 09:01:00', 'America/Lima'));
        $this->assertFalse($link->fresh()->isUsable());
        $this->postJson(route('payment-links.paypal.create', ['code' => $link->code]))->assertStatus(422);

        $this->get(route('payment-links.show', ['code' => $link->code]))
            ->assertOk()
            ->assertDontSee('paypal-buttons', false);
        $this->assertSame('expired', $link->fresh()->status);
    }

    // ─────────────────────────────────────────────────────────────
    //  Pago por PayPal (regresión del bug de booking_id)
    // ─────────────────────────────────────────────────────────────

    public function test_paying_a_balance_link_with_paypal_marks_the_existing_booking_paid_without_creating_another(): void
    {
        Mail::fake();
        $booking = $this->depositBooking();
        $link = $this->service()->createLink($booking, null, false);
        $this->assertNotNull($link->booking_id, 'el link está ligado a la reserva (el caso que antes devolvía éxito sin cobrar)');

        $this->createOrderFor($link)->assertOk();

        // PayPal cobra exactamente el saldo (100.00), no el total de la reserva.
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v2/checkout/orders') && $r['purchase_units'][0]['amount']['value'] === '100.00');

        $this->fakePaypalCapture('ORDER-BAL', 100.00, 'CAPTURE-BAL-1');
        $response = $this->capture($link);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertStringContainsString('/checkout/gracias', $response->json('redirect'));

        $booking->refresh();
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('confirmed', $booking->status);
        $this->assertEquals(200.00, (float) $booking->amount_paid);
        $this->assertEquals(0.00, (float) $booking->balance_due);
        $this->assertNotNull($booking->balance_paid_at);
        $this->assertSame('paypal', $booking->balance_payment_method);
        $this->assertSame('CAPTURE-BAL-1', $booking->balance_payment_reference);
        // La referencia del cobro ORIGINAL no se pisa.
        $this->assertSame('chr_deposit_1', $booking->payment_reference);

        $this->assertSame(1, Booking::count(), 'pagar el saldo NO crea una reserva nueva');
        $this->assertSame('paid', $link->fresh()->status);
        $this->assertSame('CAPTURE-BAL-1', $link->fresh()->paypal_capture_id);

        Mail::assertSent(BookingBalancePaid::class, fn ($m) => $m->hasTo('ana@example.com'));

        // GA4: evento propio con lo cobrado ahora (no un segundo `purchase`).
        $payload = $this->get(route('checkout.thanks', ['locale' => 'es']))->viewData('conversionPayload');
        $this->assertSame('balance_payment', $payload['event']);
        $this->assertEquals(100.00, $payload['data']['value']);
    }

    public function test_balance_link_does_not_require_the_buyer_form_and_replay_is_idempotent(): void
    {
        Mail::fake();
        $booking = $this->depositBooking();
        $link = $this->service()->createLink($booking, null, false);
        $this->createOrderFor($link)->assertOk();

        $this->fakePaypalCapture('ORDER-BAL', 100.00);
        $this->capture($link)->assertOk()->assertJson(['success' => true]);

        // Replay (doble clic / reintento de red): mismo resultado, sin doble registro ni segundo correo.
        $this->capture($link, 'ORDER-BAL', ['customer_email' => 'ana@example.com'])->assertOk()->assertJson(['success' => true]);

        $this->assertEquals(200.00, (float) $booking->fresh()->amount_paid);
        $this->assertSame(1, Booking::count());
        Mail::assertSent(BookingBalancePaid::class, 1);
    }

    public function test_replay_with_another_persons_email_does_not_load_the_booking_into_the_session(): void
    {
        Mail::fake();
        $booking = $this->depositBooking();
        $link = $this->service()->createLink($booking, null, false);
        $this->createOrderFor($link)->assertOk();
        $this->fakePaypalCapture('ORDER-BAL', 100.00);
        $this->capture($link)->assertOk();

        $this->flushSession();
        $this->capture($link, 'ORDER-BAL', ['customer_email' => 'intruso@example.com'])->assertOk();

        $this->assertNull(session('last_bookings'));
    }

    public function test_paypal_amount_must_match_the_balance_link(): void
    {
        Mail::fake();
        $booking = $this->depositBooking();
        $link = $this->service()->createLink($booking, null, false);
        $this->createOrderFor($link)->assertOk();

        // PayPal tiene una orden por 1.00: no se captura.
        $this->fakePaypalCapture('ORDER-BAL', 1.00);
        $this->capture($link)->assertStatus(422);

        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/capture'));
        $this->assertSame('partial', $booking->fresh()->payment_status);
        $this->assertSame('pending', $link->fresh()->status);
    }

    public function test_link_stops_charging_when_the_booking_was_already_paid_another_way(): void
    {
        Mail::fake();
        $booking = $this->depositBooking();
        $link = $this->service()->createLink($booking, null, false);

        // El admin cobra en efectivo y lo marca pagado: el link queda anulado.
        $this->assertTrue($this->service()->markPaidManually($booking, 'cash', 'recibo-77'));
        $this->assertSame('cancelled', $link->fresh()->status);
        $this->assertSame('paid', $booking->fresh()->payment_status);

        $this->fakePaypalCreate();
        $this->postJson(route('payment-links.paypal.create', ['code' => $link->code]))->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_link_does_not_charge_an_outdated_amount_if_the_booking_balance_changed(): void
    {
        $booking = $this->depositBooking();
        $link = $this->service()->createLink($booking, null, false);

        $booking->update(['balance_due' => 60]); // el admin ajustó el saldo

        $this->fakePaypalCreate();
        $this->postJson(route('payment-links.paypal.create', ['code' => $link->code]))->assertStatus(422);
        Http::assertNothingSent();

        $page = $this->get(route('payment-links.show', ['code' => $link->code]))->assertOk();
        $this->assertFalse($page->viewData('balancePayable'));
        $page->assertDontSee('paypal-buttons', false);
    }

    public function test_public_page_exposes_balance_data_for_the_front(): void
    {
        $booking = $this->depositBooking();
        $link = $this->service()->createLink($booking, null, false);

        $page = $this->get(route('payment-links.show', ['code' => $link->code]))->assertOk();

        $this->assertTrue($page->viewData('isBalance'));
        $this->assertTrue($booking->is($page->viewData('booking')));
        $this->assertTrue($page->viewData('balancePayable'));
        $this->assertTrue($page->viewData('culqiEnabled'));
        $this->assertSame('pk_test_dummy', $page->viewData('culqiPublicKey'));
        $this->assertStringContainsString('/culqi/charge', $page->viewData('culqiChargeUrl'));
    }

    // ─────────────────────────────────────────────────────────────
    //  Pago por Culqi (tarjeta)
    // ─────────────────────────────────────────────────────────────

    private function fakeCulqiApproved(string $id = 'chr_balance_ok'): void
    {
        Http::fake([
            'api.culqi.com/v2/charges' => Http::response([
                'object' => 'charge',
                'id' => $id,
                'outcome' => ['type' => 'venta_exitosa'],
            ], 201),
        ]);
    }

    private function culqiPay(PaymentLink $link, array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('payment-links.culqi.charge', ['code' => $link->code]), array_merge([
            'culqi_token' => 'tkn_test_balance',
        ], $body));
    }

    public function test_balance_can_be_paid_by_card_with_culqi_for_exactly_the_balance(): void
    {
        Mail::fake();
        $booking = $this->depositBooking();
        $link = $this->service()->createLink($booking, null, false);
        $this->fakeCulqiApproved('chr_bal_1');

        $this->culqiPay($link, ['amount' => 1])->assertOk()->assertJson(['success' => true]);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/charges')
            && $r['amount'] === 10000
            && $r['currency_code'] === 'USD'
            && $r['email'] === 'ana@example.com'
            && $r['metadata']['kind'] === 'balance');

        $booking->refresh();
        $this->assertSame('paid', $booking->payment_status);
        $this->assertEquals(200.00, (float) $booking->amount_paid);
        $this->assertSame('culqi', $booking->balance_payment_method);
        $this->assertSame('chr_bal_1', $booking->balance_payment_reference);
        $this->assertSame('paid', $link->fresh()->status);
        $this->assertSame('chr_bal_1', $link->fresh()->culqi_charge_id);
        $this->assertSame(1, Booking::count());
        Mail::assertSent(BookingBalancePaid::class);
    }

    public function test_culqi_balance_charge_cannot_be_repeated_on_a_paid_link(): void
    {
        Mail::fake();
        $booking = $this->depositBooking();
        $link = $this->service()->createLink($booking, null, false);
        $this->fakeCulqiApproved();
        $this->culqiPay($link)->assertOk();

        $this->culqiPay($link)->assertStatus(422);

        Http::assertSentCount(1);
    }

    public function test_culqi_balance_declined_card_leaves_everything_untouched(): void
    {
        Mail::fake();
        $booking = $this->depositBooking();
        $link = $this->service()->createLink($booking, null, false);
        Http::fake([
            'api.culqi.com/v2/charges' => Http::response([
                'object' => 'error', 'type' => 'card_error', 'code' => 'card_declined',
                'decline_code' => 'insufficient_funds', 'user_message' => 'sin fondos',
            ], 400),
        ]);

        $this->culqiPay($link)->assertStatus(402)->assertJson(['code' => 'card_declined']);

        $this->assertSame('partial', $booking->fresh()->payment_status);
        $this->assertSame('pending', $link->fresh()->status);
        $this->assertNull($link->fresh()->culqi_charge_id);
    }

    public function test_culqi_balance_endpoint_only_serves_balance_links(): void
    {
        $standalone = PaymentLink::factory()->create(['tour_id' => Tour::factory()->create()->id]);
        Http::fake();

        $this->culqiPay($standalone)->assertStatus(404);
        Http::assertNothingSent();
    }

    public function test_culqi_balance_is_unavailable_when_culqi_is_disabled(): void
    {
        config(['services.culqi.enabled' => false]);
        $booking = $this->depositBooking();
        $link = $this->service()->createLink($booking, null, false);
        Http::fake();

        $this->culqiPay($link)->assertStatus(503);
        Http::assertNothingSent();
    }

    // ─────────────────────────────────────────────────────────────
    //  Liquidación manual y de bajo nivel
    // ─────────────────────────────────────────────────────────────

    public function test_settle_is_idempotent(): void
    {
        $booking = $this->depositBooking();

        $this->assertTrue($this->service()->settle($booking, 'paypal', 'CAP-1', 100.00));
        $this->assertFalse($this->service()->settle($booking->fresh(), 'paypal', 'CAP-1', 100.00));

        $this->assertEquals(200.00, (float) $booking->fresh()->amount_paid);
    }

    public function test_partial_manual_payment_keeps_the_booking_partial_with_a_smaller_balance(): void
    {
        $booking = $this->depositBooking();

        $this->service()->settle($booking, 'cash', 'recibo-1', 40.00);

        $b = $booking->fresh();
        $this->assertSame('partial', $b->payment_status);
        $this->assertEquals(140.00, (float) $b->amount_paid);
        $this->assertEquals(60.00, (float) $b->balance_due);
        $this->assertNull($b->balance_paid_at);
    }

    public function test_deposit_refund_rule_is_24_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-10 08:00:00', 'America/Lima'));
        $tour = Tour::factory()->create(['departure_time' => '09:00']);

        // Salida mañana 09:00 => faltan 25 h => reembolsable.
        $over = Booking::factory()->create(['tour_id' => $tour->id, 'travel_date' => '2026-12-11']);
        // Salida hoy 09:00 => falta 1 h => no reembolsable.
        $under = Booking::factory()->create(['tour_id' => $tour->id, 'travel_date' => '2026-12-10']);
        // Exactamente 24 h (mañana 08:00 si la salida fuera a las 08:00) => no reembolsable (hay que tener MÁS de 24 h).
        $edgeTour = Tour::factory()->create(['departure_time' => '08:00']);
        $edge = Booking::factory()->create(['tour_id' => $edgeTour->id, 'travel_date' => '2026-12-11']);

        $this->assertTrue($over->isDepositRefundable());
        $this->assertFalse($under->isDepositRefundable());
        $this->assertFalse($edge->isDepositRefundable());
    }
}
