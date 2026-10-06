<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Tour;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Guards the conversion-event contract added on 2026-09-22:
 *   - a PAID booking renders the `purchase` payload on /checkout/gracias
 *     (reference, currency, total and items sourced from the server);
 *   - a pay-later booking renders `reserva_pagar_despues`, never `purchase`;
 *   - either payload renders EXACTLY ONCE — a second visit to the thanks
 *     page (refresh / back button) must not re-fire it, because
 *     CheckoutController@thanks consumes the `conversion_pending` session
 *     flag via session()->pull().
 */
class ConversionEventsTest extends TestCase
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

    // ─────────────────────────────────────────────────────────────
    //  Pay-later flow (real controller path, no external HTTP calls)
    // ─────────────────────────────────────────────────────────────

    public function test_pay_later_booking_renders_reserva_pagar_despues_not_purchase(): void
    {
        Mail::fake();

        $tour = $this->tour();
        app(CartService::class)->add($tour, 2, 0, now()->addDays(10)->format('Y-m-d'));

        $process = $this->post(route('checkout.process', ['locale' => self::LOCALE]), [
            'payment_timing' => 'later',
            'customer_name' => 'Juan Pérez',
            'customer_email' => 'juan.paylater@example.com',
            'customer_phone' => '987654321',
            'travel_date' => now()->addDays(10)->format('Y-m-d'),
            'accept_terms' => true,
        ]);
        $process->assertRedirectToRoute('checkout.thanks', ['locale' => self::LOCALE]);

        $thanks = $this->get(route('checkout.thanks', ['locale' => self::LOCALE]));
        $thanks->assertOk();

        $payload = $thanks->viewData('conversionPayload');
        $this->assertNotNull($payload, 'Expected a conversion payload on the first visit after a pay-later booking.');
        $this->assertSame('reserva_pagar_despues', $payload['event']);
        $this->assertSame('Lead', $payload['fb']);
        $this->assertSame(300.0, $payload['data']['value']); // 150 * 2 adults
        $this->assertSame('USD', $payload['data']['currency']);
        $this->assertNotEmpty($payload['data']['transaction_id']);
        $this->assertStringStartsWith('LVT-', $payload['data']['transaction_id']);

        $thanks->assertSee('reserva_pagar_despues');
        $thanks->assertDontSee('"purchase"', false);
    }

    public function test_pay_later_conversion_event_does_not_render_on_second_visit(): void
    {
        Mail::fake();

        $tour = $this->tour();
        app(CartService::class)->add($tour, 1, 0, now()->addDays(10)->format('Y-m-d'));

        $this->post(route('checkout.process', ['locale' => self::LOCALE]), [
            'payment_timing' => 'later',
            'customer_name' => 'Ana López',
            'customer_email' => 'ana.paylater@example.com',
            'customer_phone' => '987000002',
            'travel_date' => now()->addDays(10)->format('Y-m-d'),
            'accept_terms' => true,
        ])->assertRedirectToRoute('checkout.thanks', ['locale' => self::LOCALE]);

        $firstVisit = $this->get(route('checkout.thanks', ['locale' => self::LOCALE]));
        $this->assertNotNull($firstVisit->viewData('conversionPayload'), 'First visit must carry the conversion payload.');

        // Second visit (refresh / back button): bookings still render (that
        // list is NOT gated), but the conversion event must not fire again.
        $secondVisit = $this->get(route('checkout.thanks', ['locale' => self::LOCALE]));
        $secondVisit->assertOk();
        $this->assertNull($secondVisit->viewData('conversionPayload'), 'Second visit must not repeat the conversion event.');
        $secondVisit->assertViewHas('bookings');

        // window.lvtTrack itself is defined site-wide (layouts/app.blade.php)
        // and the WhatsApp delegation also calls it, so asserting the whole
        // page never contains "lvtTrack(" would be a check that can never
        // fail. What must be absent on a second visit is the specific
        // conversion-event payload block pushed by checkout/thanks.blade.php.
        $secondVisit->assertDontSee('reserva_pagar_despues');
        $secondVisit->assertDontSee('"purchase"', false);
    }

    // ─────────────────────────────────────────────────────────────
    //  Paid booking → purchase (session pre-seeded, same pattern as
    //  Tests\Feature\CheckoutTest::test_thanks_page_renders_after_successful_payment)
    // ─────────────────────────────────────────────────────────────

    public function test_paid_booking_renders_purchase_with_correct_reference_and_total(): void
    {
        $tour = $this->tour();

        $booking = Booking::create([
            'tour_id' => $tour->id,
            'tour_title_snapshot' => $tour->title_es,
            'customer_name' => 'Carla Ruiz',
            'customer_email' => 'carla@example.com',
            'customer_phone' => '987000003',
            'travel_date' => now()->addDays(12)->format('Y-m-d'),
            'adults' => 2,
            'children' => 1,
            'unit_price' => 150.00,
            'total_price' => 450.00,
            'currency' => 'USD',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'paypal',
            'payment_reference' => 'CAPTURE-XYZ',
            'locale' => 'es',
        ]);

        $response = $this->withSession([
            'last_bookings' => [$booking->toArray()],
            'conversion_pending' => true,
        ])->get(route('checkout.thanks', ['locale' => self::LOCALE]));

        $response->assertOk();

        $payload = $response->viewData('conversionPayload');
        $this->assertNotNull($payload);
        $this->assertSame('purchase', $payload['event']);
        $this->assertSame('Purchase', $payload['fb']);
        $this->assertSame($booking->reference, $payload['data']['transaction_id']);
        $this->assertSame(450.0, $payload['data']['value']);
        $this->assertSame('USD', $payload['data']['currency']);
        $this->assertCount(1, $payload['data']['items']);
        $this->assertSame(3, $payload['data']['items'][0]['quantity']); // 2 adults + 1 child

        $response->assertSee($booking->reference);
        $response->assertSee('"purchase"', false);
    }
}
