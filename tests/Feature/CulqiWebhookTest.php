<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CulqiWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'test_webhook_secret_for_phpunit';

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        // Override Culqi config with test secret for all webhook tests
        config(['services.culqi.webhook_secret' => $this->webhookSecret]);
    }

    private function makeBooking(array $overrides = []): Booking
    {
        $tour = Tour::factory()->create(['price' => 100.00]);

        return Booking::create(array_merge([
            'tour_id'             => $tour->id,
            'tour_title_snapshot' => $tour->title_es,
            'customer_name'       => 'Test User',
            'customer_email'      => 'test@example.com',
            'customer_phone'      => '987654321',
            'travel_date'         => now()->addDays(5)->format('Y-m-d'),
            'adults'              => 1,
            'children'            => 0,
            'unit_price'          => 100.00,
            'total_price'         => 100.00,
            'currency'            => 'USD',
            'status'              => 'pending',
            'payment_status'      => 'pending',
            'payment_method'      => 'culqi',
            'payment_reference'   => null,
            'locale'              => 'es',
        ], $overrides));
    }

    private function buildPayload(string $type, string $chargeId): string
    {
        return json_encode([
            'type'   => $type,
            'data'   => ['id' => $chargeId],
            'object' => 'event',
        ]);
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->webhookSecret);
    }

    private function postWebhook(string $payload, string $signature): \Illuminate\Testing\TestResponse
    {
        return $this->call(
            'POST',
            route('webhooks.culqi'),
            [],
            [],
            [],
            [
                'HTTP_X-Culqi-Signature' => $signature,
                'CONTENT_TYPE'           => 'application/json',
            ],
            $payload
        );
    }

    // ─────────────────────────────────────────────────────────────
    //  Tests
    // ─────────────────────────────────────────────────────────────

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = $this->buildPayload('charge.succeeded', 'chr_test_bad');

        $response = $this->postWebhook($payload, 'invalid_signature_xyz');

        $response->assertStatus(400);
    }

    public function test_webhook_charge_succeeded_updates_booking(): void
    {
        $chargeId = 'chr_test_succeeded_001';

        $booking = $this->makeBooking([
            'payment_reference' => $chargeId,
            'payment_status'    => 'pending',
            'status'            => 'pending',
        ]);

        $payload  = $this->buildPayload('charge.succeeded', $chargeId);
        $response = $this->postWebhook($payload, $this->sign($payload));

        $response->assertOk();
        $response->assertJson(['received' => true]);

        $booking->refresh();
        $this->assertEquals('paid', $booking->payment_status);
        $this->assertEquals('confirmed', $booking->status);
    }

    public function test_webhook_charge_failed_updates_booking(): void
    {
        $chargeId = 'chr_test_failed_001';

        $booking = $this->makeBooking([
            'payment_reference' => $chargeId,
            'payment_status'    => 'pending',
            'status'            => 'pending',
        ]);

        $payload  = $this->buildPayload('charge.failed', $chargeId);
        $response = $this->postWebhook($payload, $this->sign($payload));

        $response->assertOk();
        $response->assertJson(['received' => true]);

        $booking->refresh();
        $this->assertEquals('failed', $booking->payment_status);
    }

    public function test_webhook_is_idempotent(): void
    {
        $chargeId = 'chr_test_idempotent_001';

        $booking = $this->makeBooking([
            'payment_reference' => $chargeId,
            'payment_status'    => 'paid',   // already processed
            'status'            => 'confirmed',
        ]);

        $payload = $this->buildPayload('charge.succeeded', $chargeId);

        // Send the same event twice
        $this->postWebhook($payload, $this->sign($payload))->assertOk();
        $this->postWebhook($payload, $this->sign($payload))->assertOk();

        // Should still be paid/confirmed — no state regression
        $booking->refresh();
        $this->assertEquals('paid', $booking->payment_status);
        $this->assertEquals('confirmed', $booking->status);
    }
}
