<?php

namespace Tests\Feature;

use App\Mail\BookingBalanceLink;
use App\Models\Booking;
use App\Models\PaymentLink;
use App\Models\Tour;
use App\Services\BalancePaymentService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * - Webhooks (Culqi y PayPal) con reservas de adelanto: el cobro del 50% NUNCA
 *   las marca 'paid'; el cobro del saldo (link) sí las liquida (red de
 *   seguridad si el flujo síncrono falló a medias).
 * - Firma HMAC del webhook de Culqi (X-Culqi-Signature) y formatos de evento.
 * - Recordatorio automático del saldo (comando programado, 3 días antes).
 */
class BalanceWebhooksAndReminderTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'whsec_test_balance';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.culqi.webhook_secret' => $this->secret]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function booking(array $overrides = [], ?string $travelDate = null): Booking
    {
        $tour = Tour::factory()->create(['price' => 100.00, 'is_published' => true, 'departure_time' => '09:00']);

        return Booking::factory()->create(array_merge([
            'tour_id' => $tour->id,
            'travel_date' => $travelDate ?? now()->addDays(10)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'unit_price' => 100,
            'total_price' => 200,
            'status' => 'confirmed',
            'payment_status' => 'partial',
            'payment_plan' => 'deposit',
            'payment_method' => 'culqi',
            'payment_reference' => 'chr_dep',
            'amount_paid' => 100,
            'balance_due' => 100,
            'customer_email' => 'ana@example.com',
            'locale' => 'es',
        ], $overrides));
    }

    // ─────────────────────────────────────────────────────────────
    //  Culqi webhook: firma
    // ─────────────────────────────────────────────────────────────

    private function culqiPost(string $payload, ?string $signature): \Illuminate\Testing\TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($signature !== null) {
            $server['HTTP_X-Culqi-Signature'] = $signature;
        }

        return $this->call('POST', route('webhooks.culqi'), [], [], [], $server, $payload);
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }

    public function test_culqi_signature_hmac_sha256_hex_of_the_raw_body_is_accepted(): void
    {
        $payload = json_encode(['type' => 'charge.succeeded', 'data' => ['id' => 'chr_unknown']]);

        $this->culqiPost($payload, $this->sign($payload))->assertOk()->assertJson(['received' => true]);
    }

    public function test_culqi_rejects_wrong_missing_or_tampered_signatures(): void
    {
        $payload = json_encode(['type' => 'charge.succeeded', 'data' => ['id' => 'chr_x']]);

        $this->culqiPost($payload, 'deadbeef')->assertStatus(400);
        $this->culqiPost($payload, null)->assertStatus(400);
        // Firma válida de OTRO cuerpo => rechazada.
        $this->culqiPost($payload, $this->sign($payload.' '))->assertStatus(400);
        // base64 en vez de hex (otra convención de HMAC) => rechazada.
        $this->culqiPost($payload, base64_encode(hash_hmac('sha256', $payload, $this->secret, true)))->assertStatus(400);
    }

    public function test_culqi_fails_closed_when_no_webhook_secret_is_configured(): void
    {
        config(['services.culqi.webhook_secret' => null]);
        $payload = json_encode(['type' => 'charge.succeeded', 'data' => ['id' => 'chr_x']]);

        $this->culqiPost($payload, hash_hmac('sha256', $payload, ''))->assertStatus(400);
    }

    public function test_culqi_accepts_official_event_names_and_data_as_a_json_string(): void
    {
        $booking = $this->booking(['payment_status' => 'pending', 'status' => 'pending', 'payment_plan' => 'full', 'amount_paid' => 0, 'balance_due' => 0, 'payment_reference' => 'chr_official']);

        // Nombre de evento de Culqi + `data` serializado como string.
        $payload = json_encode([
            'object' => 'event',
            'type' => 'charge.creation.succeeded',
            'data' => json_encode(['object' => 'charge', 'id' => 'chr_official']),
        ]);

        $this->culqiPost($payload, $this->sign($payload))->assertOk();

        $this->assertSame('paid', $booking->fresh()->payment_status);
    }

    // ─────────────────────────────────────────────────────────────
    //  Webhooks con adelanto
    // ─────────────────────────────────────────────────────────────

    public function test_culqi_success_webhook_keeps_a_deposit_booking_partial_never_paid(): void
    {
        $booking = $this->booking(['payment_status' => 'pending', 'status' => 'pending', 'payment_reference' => 'chr_dep']);

        $payload = json_encode(['type' => 'charge.succeeded', 'data' => ['id' => 'chr_dep']]);
        $this->culqiPost($payload, $this->sign($payload))->assertOk();

        $this->assertSame('partial', $booking->fresh()->payment_status);
        $this->assertSame('confirmed', $booking->fresh()->status);

        // Y es idempotente: un segundo evento no la toca.
        $this->culqiPost($payload, $this->sign($payload))->assertOk();
        $this->assertSame('partial', $booking->fresh()->payment_status);
    }

    public function test_culqi_success_webhook_does_not_downgrade_a_booking_whose_balance_is_already_paid(): void
    {
        $booking = $this->booking(['payment_status' => 'paid', 'balance_due' => 0, 'amount_paid' => 200, 'balance_paid_at' => now(), 'payment_reference' => 'chr_dep']);

        $payload = json_encode(['type' => 'charge.succeeded', 'data' => ['id' => 'chr_dep']]);
        $this->culqiPost($payload, $this->sign($payload))->assertOk();

        $this->assertSame('paid', $booking->fresh()->payment_status);
    }

    public function test_culqi_webhook_settles_the_balance_when_the_sync_flow_did_not_finish(): void
    {
        Mail::fake();
        $booking = $this->booking();
        $link = app(BalancePaymentService::class)->createLink($booking, null, false);
        // Cobrado en Culqi pero el flujo síncrono no alcanzó a liquidar la reserva.
        $link->forceFill(['culqi_charge_id' => 'chr_bal_wh', 'status' => 'paid', 'paid_at' => now()])->save();

        $payload = json_encode(['type' => 'charge.succeeded', 'data' => ['id' => 'chr_bal_wh']]);
        $this->culqiPost($payload, $this->sign($payload))->assertOk();

        $b = $booking->fresh();
        $this->assertSame('paid', $b->payment_status);
        $this->assertSame('culqi', $b->balance_payment_method);
        $this->assertEquals(200.00, (float) $b->amount_paid);
    }

    // ─────────────────────────────────────────────────────────────
    //  PayPal webhook
    // ─────────────────────────────────────────────────────────────

    private function paypalWebhook(array $event): \Illuminate\Testing\TestResponse
    {
        config(['services.paypal.webhook_id' => 'WH-1']);
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS'], 200),
        ]);

        return $this->withHeaders([
            'Paypal-Transmission-Id' => 'txn-1',
            'Paypal-Transmission-Time' => now()->toIso8601String(),
            'Paypal-Cert-Url' => 'https://api.sandbox.paypal.com/cert',
            'Paypal-Auth-Algo' => 'SHA256withRSA',
            'Paypal-Transmission-Sig' => 'sig',
        ])->postJson(route('webhooks.paypal'), $event);
    }

    public function test_paypal_completed_webhook_keeps_a_deposit_booking_partial(): void
    {
        $booking = $this->booking(['payment_status' => 'pending', 'status' => 'pending', 'payment_method' => 'paypal', 'payment_reference' => 'CAP-DEP']);

        $this->paypalWebhook([
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'CAP-DEP', 'amount' => ['value' => '100.00', 'currency_code' => 'USD']],
        ])->assertOk();

        $this->assertSame('partial', $booking->fresh()->payment_status);
    }

    public function test_paypal_completed_webhook_settles_a_balance_link_capture(): void
    {
        Mail::fake();
        $booking = $this->booking();
        $link = app(BalancePaymentService::class)->createLink($booking, null, false);
        $link->forceFill(['paypal_order_id' => 'ORD-W', 'paypal_capture_id' => 'CAP-BAL-W', 'status' => 'paid', 'paid_at' => now()])->save();

        $this->paypalWebhook([
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAP-BAL-W',
                'amount' => ['value' => '100.00', 'currency_code' => 'USD'],
                'supplementary_data' => ['related_ids' => ['order_id' => 'ORD-W']],
            ],
        ])->assertOk();

        $b = $booking->fresh();
        $this->assertSame('paid', $b->payment_status);
        $this->assertSame('paypal', $b->balance_payment_method);
        $this->assertSame('CAP-BAL-W', $b->balance_payment_reference);
        $this->assertSame(1, Booking::count(), 'el webhook de un link de saldo no crea reservas');
    }

    public function test_paypal_webhook_with_a_different_amount_does_not_settle_the_balance(): void
    {
        $booking = $this->booking();
        $link = app(BalancePaymentService::class)->createLink($booking, null, false);
        $link->forceFill(['paypal_capture_id' => 'CAP-BAD', 'status' => 'paid', 'paid_at' => now()])->save();

        $this->paypalWebhook([
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'CAP-BAD', 'amount' => ['value' => '1.00', 'currency_code' => 'USD']],
        ])->assertOk();

        $this->assertSame('partial', $booking->fresh()->payment_status);
    }

    // ─────────────────────────────────────────────────────────────
    //  Recordatorio automático (3 días antes) y expiración
    // ─────────────────────────────────────────────────────────────

    public function test_reminder_command_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains($e->command, 'bookings:send-balance-reminders'));

        $this->assertCount(1, $events);
        $this->assertSame('30 13 * * *', $events->first()->expression);
    }

    public function test_reminder_sends_the_balance_link_three_days_before_the_tour_only_once(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-12-01 10:00:00', 'America/Lima'));
        $booking = $this->booking([], '2026-12-04'); // en 3 días

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        Mail::assertSent(BookingBalanceLink::class, function (BookingBalanceLink $m) use ($booking) {
            return $m->hasTo('ana@example.com') && $m->isReminder && $m->booking->is($booking);
        });

        $link = PaymentLink::where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame('balance', $link->purpose);
        $this->assertEquals(100.00, (float) $link->amount);
        $this->assertNotNull($booking->fresh()->balance_reminder_sent_at);

        // Segunda corrida: no repite.
        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();
        Mail::assertSent(BookingBalanceLink::class, 1);
        $this->assertSame(1, PaymentLink::where('booking_id', $booking->id)->count());
    }

    public function test_reminder_ignores_far_tours_paid_and_cancelled_bookings(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-12-01 10:00:00', 'America/Lima'));

        $this->booking([], '2026-12-15');                                                                   // lejos
        $this->booking(['payment_status' => 'paid', 'balance_due' => 0, 'amount_paid' => 200], '2026-12-03'); // ya pagada
        $this->booking(['status' => 'cancelled'], '2026-12-03');                                            // cancelada
        $this->booking(['payment_status' => 'pending', 'payment_plan' => 'later', 'status' => 'pending', 'amount_paid' => 0, 'balance_due' => 200], '2026-12-03'); // pagar luego

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(0, PaymentLink::count());
    }

    public function test_reminder_skips_when_the_balance_link_can_no_longer_be_generated(): void
    {
        Mail::fake();
        // Tour mañana 09:00 y ahora 12:00 de hoy: ya pasó el límite de 48 h.
        Carbon::setTestNow(Carbon::parse('2026-12-10 12:00:00', 'America/Lima'));
        $booking = $this->booking([], '2026-12-11');

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(0, PaymentLink::count());
        // Reclamado: no se reintenta cada día (el equipo cobra a mano).
        $this->assertNotNull($booking->fresh()->balance_reminder_sent_at);
    }

    public function test_reminder_reuses_an_existing_open_link(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-12-01 10:00:00', 'America/Lima'));
        $booking = $this->booking([], '2026-12-04');
        $existing = app(BalancePaymentService::class)->createLink($booking, null, false);

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        $this->assertSame(1, PaymentLink::where('booking_id', $booking->id)->count());
        Mail::assertSent(BookingBalanceLink::class, fn (BookingBalanceLink $m) => $m->link->is($existing));
    }

    public function test_reminder_command_marks_stale_balance_links_as_expired(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-12-01 10:00:00', 'America/Lima'));
        $booking = $this->booking([], '2026-12-10');
        $link = app(BalancePaymentService::class)->createLink($booking, null, false);
        $this->assertSame('pending', $link->status);

        // 9-dic: pasó el límite (8-dic 09:00).
        Carbon::setTestNow(Carbon::parse('2026-12-09 10:00:00', 'America/Lima'));
        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        $this->assertSame('expired', $link->fresh()->status);
    }

    public function test_dry_run_sends_and_changes_nothing(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-12-01 10:00:00', 'America/Lima'));
        $booking = $this->booking([], '2026-12-04');

        $this->artisan('bookings:send-balance-reminders --dry')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNull($booking->fresh()->balance_reminder_sent_at);
        $this->assertSame(0, PaymentLink::count());
    }
}
