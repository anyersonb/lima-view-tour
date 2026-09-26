<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\PaymentLink;
use App\Models\Tour;
use App\Services\BookingNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Webhook PAYPAL (PAYMENT.CAPTURE.COMPLETED / DENIED / REFUNDED). Cubre la
 * verificación de firma fail-closed y la reconciliación sin duplicar
 * reservas, tanto para el checkout normal (Booking.payment_reference) como
 * para los links de pago (PaymentLink.paypal_capture_id).
 */
class PaymentLinkPaypalWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function fakeVerifySignature(string $status = 'SUCCESS'): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(
                ['verification_status' => $status], 200
            ),
        ]);
    }

    private function webhookHeaders(): array
    {
        return [
            'Paypal-Transmission-Id' => 'txn-1',
            'Paypal-Transmission-Time' => now()->toIso8601String(),
            'Paypal-Cert-Url' => 'https://api.sandbox.paypal.com/cert',
            'Paypal-Auth-Algo' => 'SHA256withRSA',
            'Paypal-Transmission-Sig' => 'signature',
        ];
    }

    private function postWebhook(array $event): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->webhookHeaders())
            ->postJson(route('webhooks.paypal'), $event);
    }

    // ─────────────────────────────────────────────────────────────
    //  Firma
    // ─────────────────────────────────────────────────────────────

    public function test_webhook_rejects_when_webhook_id_not_configured(): void
    {
        config(['services.paypal.webhook_id' => null]);
        $this->fakeVerifySignature();

        $response = $this->postWebhook(['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => ['id' => 'X']]);

        $response->assertStatus(400);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config(['services.paypal.webhook_id' => 'WH-TEST-1']);
        $this->fakeVerifySignature('FAILURE');

        $response = $this->postWebhook(['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => ['id' => 'X']]);

        $response->assertStatus(400);
    }

    public function test_webhook_rejects_missing_headers(): void
    {
        config(['services.paypal.webhook_id' => 'WH-TEST-1']);
        $this->fakeVerifySignature('SUCCESS');

        // Sin los headers Paypal-* — nunca llega a llamar a PayPal.
        $response = $this->postJson(route('webhooks.paypal'), [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'X'],
        ]);

        $response->assertStatus(400);
    }

    // ─────────────────────────────────────────────────────────────
    //  Reconciliación: checkout normal
    // ─────────────────────────────────────────────────────────────

    public function test_webhook_marks_existing_booking_paid_without_duplicating(): void
    {
        config(['services.paypal.webhook_id' => 'WH-TEST-1']);
        $this->fakeVerifySignature('SUCCESS');

        $tour = Tour::factory()->create(['price' => 100]);
        $booking = Booking::create([
            'tour_id' => $tour->id,
            'tour_title_snapshot' => $tour->title_es,
            'customer_name' => 'Ana',
            'customer_email' => 'ana@example.com',
            'customer_phone' => '987000001',
            'travel_date' => now()->addDays(10)->format('Y-m-d'),
            'adults' => 1,
            'children' => 0,
            'unit_price' => 100,
            'total_price' => 100,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'paypal',
            'payment_reference' => 'CAP-EXISTING',
            'locale' => 'es',
        ]);

        $event = ['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => ['id' => 'CAP-EXISTING']];

        $this->postWebhook($event)->assertOk();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);

        // Replay del mismo evento: sigue habiendo UNA sola reserva con esta
        // referencia, sin error ni duplicado.
        $this->postWebhook($event)->assertOk();

        $this->assertSame(1, Booking::where('payment_reference', 'CAP-EXISTING')->count());
    }

    public function test_webhook_marks_capture_denied(): void
    {
        config(['services.paypal.webhook_id' => 'WH-TEST-1']);
        $this->fakeVerifySignature('SUCCESS');

        $tour = Tour::factory()->create(['price' => 100]);
        $booking = Booking::create([
            'tour_id' => $tour->id,
            'tour_title_snapshot' => $tour->title_es,
            'customer_name' => 'Ana',
            'customer_email' => 'ana@example.com',
            'customer_phone' => '987000001',
            'travel_date' => now()->addDays(10)->format('Y-m-d'),
            'adults' => 1,
            'children' => 0,
            'unit_price' => 100,
            'total_price' => 100,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'paypal',
            'payment_reference' => 'CAP-DENIED',
            'locale' => 'es',
        ]);

        $this->postWebhook(['event_type' => 'PAYMENT.CAPTURE.DENIED', 'resource' => ['id' => 'CAP-DENIED']])
            ->assertOk();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'payment_status' => 'failed']);
    }

    // ─────────────────────────────────────────────────────────────
    //  Reconciliación: links de pago (capturado, reserva pendiente)
    // ─────────────────────────────────────────────────────────────

    public function test_webhook_reconciles_payment_link_captured_without_booking(): void
    {
        Mail::fake();
        config(['services.paypal.webhook_id' => 'WH-TEST-1']);
        $this->fakeVerifySignature('SUCCESS');

        $tour = Tour::factory()->create(['price' => 150]);

        // Simula EXACTAMENTE el estado que deja captureOrder() cuando el
        // cobro se confirmó pero la creación del Booking falló después
        // (ver PaymentLinkController::captureOrder(), catch genérico).
        $link = PaymentLink::factory()->create([
            'tour_id' => $tour->id,
            'amount' => 150.00,
            'adults' => 1,
            'children' => 0,
            'status' => 'paid',
            'paid_at' => now(),
            'paypal_order_id' => 'ORDER-X',
            'paypal_capture_id' => 'CAP-LINK-1',
            'booking_id' => null,
            // A-1: lo que de verdad pagó vive en buyer_*, nunca en
            // customer_* (eso es solo el prefill del admin).
            'buyer_name' => 'Carlos Ruiz',
            'buyer_email' => 'carlos@example.com',
            'buyer_phone' => '987654321',
        ]);

        $event = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'CAP-LINK-1', 'amount' => ['value' => '150.00', 'currency_code' => 'USD']],
        ];

        $this->postWebhook($event)->assertOk();

        $this->assertDatabaseHas('bookings', [
            'customer_email' => 'carlos@example.com',
            'payment_method' => 'payment_link',
            'payment_reference' => 'CAP-LINK-1',
            'payment_status' => 'paid',
        ]);

        $link->refresh();
        $this->assertNotNull($link->booking_id);

        // Replay: ahora el link YA tiene booking_id (el re-chequeo bajo
        // lock lo detecta ANTES de intentar crear nada), y el Booking ya
        // existe con esa referencia — no debe crearse una segunda reserva.
        $this->postWebhook($event)->assertOk();

        $this->assertSame(1, Booking::where('payment_reference', 'CAP-LINK-1')->count());
    }

    public function test_webhook_does_not_reconcile_link_without_customer_data(): void
    {
        config(['services.paypal.webhook_id' => 'WH-TEST-1']);
        $this->fakeVerifySignature('SUCCESS');

        $link = PaymentLink::factory()->create([
            'amount' => 150.00,
            'status' => 'paid',
            'paid_at' => now(),
            'paypal_capture_id' => 'CAP-NO-CUSTOMER',
            'booking_id' => null,
            'buyer_email' => null,
        ]);

        $event = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'CAP-NO-CUSTOMER', 'amount' => ['value' => '150.00', 'currency_code' => 'USD']],
        ];

        $this->postWebhook($event)->assertOk();

        $this->assertDatabaseMissing('bookings', ['payment_reference' => 'CAP-NO-CUSTOMER']);
        $link->refresh();
        $this->assertNull($link->booking_id);
    }

    // ─────────────────────────────────────────────────────────────
    //  M-4 — endurecimiento del webhook
    // ─────────────────────────────────────────────────────────────

    public function test_webhook_rejects_oversized_payload(): void
    {
        config(['services.paypal.webhook_id' => 'WH-TEST-1']);
        $this->fakeVerifySignature('SUCCESS');

        $huge = str_repeat('a', 70000); // > 64 KB

        $response = $this->withHeaders($this->webhookHeaders())
            ->call('POST', route('webhooks.paypal'), [], [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], $huge);

        $response->assertStatus(413);
    }

    public function test_webhook_rejects_untrusted_cert_url(): void
    {
        config(['services.paypal.webhook_id' => 'WH-TEST-1']);
        $this->fakeVerifySignature('SUCCESS');

        $headers = $this->webhookHeaders();
        // Host que NO es *.paypal.com: nunca debe llegar a llamarse a
        // PayPal con esto.
        $headers['Paypal-Cert-Url'] = 'https://evil.example.com/cert';

        $response = $this->withHeaders($headers)
            ->postJson(route('webhooks.paypal'), ['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => ['id' => 'X']]);

        $response->assertStatus(400);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'verify-webhook-signature'));
    }

    // ─────────────────────────────────────────────────────────────
    //  B-2 — importe/moneda deben coincidir para reconciliar
    // ─────────────────────────────────────────────────────────────

    public function test_webhook_does_not_reconcile_link_when_amount_does_not_match(): void
    {
        config(['services.paypal.webhook_id' => 'WH-TEST-1']);
        $this->fakeVerifySignature('SUCCESS');

        $link = PaymentLink::factory()->create([
            'amount' => 150.00,
            'status' => 'paid',
            'paid_at' => now(),
            'paypal_capture_id' => 'CAP-AMOUNT-MISMATCH',
            'booking_id' => null,
            'buyer_name' => 'Carlos Ruiz',
            'buyer_email' => 'carlos@example.com',
        ]);

        $event = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            // El evento reporta un importe distinto al del link.
            'resource' => ['id' => 'CAP-AMOUNT-MISMATCH', 'amount' => ['value' => '1.00', 'currency_code' => 'USD']],
        ];

        $this->postWebhook($event)->assertOk();

        $this->assertDatabaseMissing('bookings', ['payment_reference' => 'CAP-AMOUNT-MISMATCH']);
        $link->refresh();
        $this->assertNull($link->booking_id);
        $this->assertSame('paid', $link->status);
    }

    // ─────────────────────────────────────────────────────────────
    //  B-4 — reembolso y denegación reflejados en el link
    // ─────────────────────────────────────────────────────────────

    public function test_webhook_marks_link_refunded(): void
    {
        config(['services.paypal.webhook_id' => 'WH-TEST-1']);
        $this->fakeVerifySignature('SUCCESS');

        $link = PaymentLink::factory()->create([
            'status' => 'paid',
            'paid_at' => now(),
            'paypal_capture_id' => 'CAP-REFUND-1',
        ]);

        $event = [
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'id' => 'REFUND-1',
                'links' => [
                    ['rel' => 'up', 'href' => 'https://api-m.sandbox.paypal.com/v2/payments/captures/CAP-REFUND-1'],
                ],
            ],
        ];

        $this->postWebhook($event)->assertOk();

        $this->assertSame('refunded', $link->refresh()->status);
    }

    public function test_webhook_marks_link_denied(): void
    {
        config(['services.paypal.webhook_id' => 'WH-TEST-1']);
        $this->fakeVerifySignature('SUCCESS');

        $link = PaymentLink::factory()->create([
            'status' => 'pending',
            'paypal_capture_id' => 'CAP-DENIED-LINK',
        ]);

        $this->postWebhook(['event_type' => 'PAYMENT.CAPTURE.DENIED', 'resource' => ['id' => 'CAP-DENIED-LINK']])
            ->assertOk();

        $this->assertSame('denied', $link->refresh()->status);
    }

    // ─────────────────────────────────────────────────────────────
    //  N-1 — anti-duplicado por payment_reference, no solo booking_id
    // ─────────────────────────────────────────────────────────────

    public function test_webhook_reconciles_a_booking_matched_by_payment_reference_even_when_link_booking_id_is_null(): void
    {
        config(['services.paypal.webhook_id' => 'WH-TEST-1']);
        $this->fakeVerifySignature('SUCCESS');

        $tour = Tour::factory()->create(['price' => 150]);
        $link = PaymentLink::factory()->create([
            'tour_id' => $tour->id,
            'amount' => 150.00,
            'status' => 'paid',
            'paid_at' => now(),
            'paypal_capture_id' => 'CAP-ALREADY-BOOKED',
            'booking_id' => null,
            'buyer_name' => 'Carlos Ruiz',
            'buyer_email' => 'carlos@example.com',
        ]);

        // Ya existe una Booking con esta misma referencia (creada por el
        // flujo síncrono), pero el link todavía no quedó enlazado.
        $existingBooking = Booking::create([
            'tour_id' => $tour->id,
            'tour_title_snapshot' => $tour->title_es,
            'customer_name' => 'Carlos Ruiz',
            'customer_email' => 'carlos@example.com',
            'customer_phone' => null,
            'travel_date' => null,
            'adults' => 1,
            'children' => 0,
            'unit_price' => 150,
            'total_price' => 150,
            'currency' => 'USD',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'payment_link',
            'payment_reference' => 'CAP-ALREADY-BOOKED',
            'locale' => 'es',
        ]);

        $event = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'CAP-ALREADY-BOOKED', 'amount' => ['value' => '150.00', 'currency_code' => 'USD']],
        ];

        $this->postWebhook($event)->assertOk();

        $this->assertSame(1, Booking::where('payment_reference', 'CAP-ALREADY-BOOKED')->count());
        $this->assertSame($existingBooking->id, $link->refresh()->booking_id);
    }

    // ─────────────────────────────────────────────────────────────
    //  N-3 — el correo se envía DESPUÉS de soltar el lock
    // ─────────────────────────────────────────────────────────────

    public function test_webhook_sends_the_booking_notification_after_releasing_the_lock_not_inside_it(): void
    {
        config(['services.paypal.webhook_id' => 'WH-TEST-1']);
        $this->fakeVerifySignature('SUCCESS');

        // Espía: en vez de mandar el correo de verdad, registra el nivel de
        // transacción de BD en el que se le llamó. Si N-3 no estuviera
        // corregido, send() se llamaría DENTRO del DB::transaction() con
        // lockForUpdate() (nivel > 0); con el fix, se llama después de que
        // esa transacción ya terminó (nivel 0, salvo el nivel propio del
        // wrapper transaccional de RefreshDatabase en el test).
        $baseLevel = DB::transactionLevel();

        $spy = new class extends BookingNotifier
        {
            public ?int $levelWhenCalled = null;

            public function send(Collection $bookings, bool $paid, ?string $customerEmail = null): void
            {
                $this->levelWhenCalled = DB::transactionLevel();
            }
        };

        $this->app->instance(BookingNotifier::class, $spy);

        $tour = Tour::factory()->create(['price' => 150]);
        PaymentLink::factory()->create([
            'tour_id' => $tour->id,
            'amount' => 150.00,
            'status' => 'paid',
            'paid_at' => now(),
            'paypal_capture_id' => 'CAP-LOCK-CHECK',
            'booking_id' => null,
            'buyer_name' => 'Carlos Ruiz',
            'buyer_email' => 'carlos@example.com',
        ]);

        $event = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'CAP-LOCK-CHECK', 'amount' => ['value' => '150.00', 'currency_code' => 'USD']],
        ];

        $this->postWebhook($event)->assertOk();

        $this->assertNotNull($spy->levelWhenCalled, 'BookingNotifier::send() no se llamó.');
        // El lockForUpdate() del webhook abre UN nivel de transacción por
        // encima del que ya trae el test (RefreshDatabase). Si send() se
        // llamó DENTRO de ese lock, el nivel visto sería $baseLevel + 1; con
        // el fix, ya volvió a $baseLevel porque la transacción del webhook
        // ya cerró.
        $this->assertSame($baseLevel, $spy->levelWhenCalled);
    }

    // ─────────────────────────────────────────────────────────────
    //  N-5 — cabeceras con UTF-8 inválido, sin llamar a PayPal
    // ─────────────────────────────────────────────────────────────

    public function test_webhook_rejects_invalid_utf8_header_without_calling_paypal(): void
    {
        config(['services.paypal.webhook_id' => 'WH-TEST-1']);
        $this->fakeVerifySignature('SUCCESS');

        $headers = $this->webhookHeaders();
        // Byte inválido en UTF-8 (0xB1 solo, sin continuación) — hace que
        // json_encode() sin JSON_THROW_ON_ERROR devolviera `false` en
        // silencio.
        $headers['Paypal-Transmission-Id'] = "txn-\xB1-invalid";

        $response = $this->withHeaders($headers)
            ->postJson(route('webhooks.paypal'), ['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => ['id' => 'X']]);

        $response->assertStatus(400);
        // Ni el token OAuth ni la verificación de firma deben haberse
        // pedido — se rechaza ANTES de llamar a PayPal.
        Http::assertNothingSent();
    }
}
