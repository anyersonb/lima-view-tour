<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmed;
use App\Models\Booking;
use App\Models\Tour;
use App\Services\CartService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALE = 'es';

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    private function tour(array $overrides = []): Tour
    {
        return Tour::factory()->create(array_merge([
            'price'        => 150.00,
            'is_published' => true,
        ], $overrides));
    }

    private function cartService(): CartService
    {
        return app(CartService::class);
    }

    private function addTourToCart(Tour $tour): void
    {
        $this->cartService()->add($tour, 2, 1, now()->addDays(10)->format('Y-m-d'));
    }

    private function validPaymentPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name'  => 'Juan Pérez García',
            'customer_email' => 'juan@example.com',
            'customer_phone' => '987654321',
            'travel_date'    => now()->addDays(15)->format('Y-m-d'),
            'culqi_token'    => 'tkn_test_abc123',
        ], $overrides);
    }

    // ─────────────────────────────────────────────────────────────
    //  Tests
    // ─────────────────────────────────────────────────────────────

    public function test_payment_form_redirects_when_cart_empty(): void
    {
        $response = $this->get(route('checkout.pay', ['locale' => self::LOCALE]));

        $response->assertRedirectToRoute('cart.index', ['locale' => self::LOCALE]);
        $response->assertSessionHas('error');
    }

    public function test_payment_form_renders_with_items(): void
    {
        $tour = $this->tour();
        $this->addTourToCart($tour);

        $response = $this->get(route('checkout.pay', ['locale' => self::LOCALE]));

        $response->assertOk();
        $response->assertViewIs('checkout.payment');
        $response->assertViewHas('items');
        $response->assertViewHas('total');
        $response->assertViewHas('public_key');
        $response->assertViewHas('total_centavos');
    }

    public function test_process_payment_with_valid_token_creates_booking_and_charge(): void
    {
        Mail::fake();

        $tour = $this->tour();
        $this->addTourToCart($tour);

        // Mock Culqi HTTP response
        Http::fake([
            'api.culqi.com/v2/charges' => Http::response([
                'id'              => 'chr_test_abc123',
                'amount'          => 45000,
                'currency_code'   => 'USD',
                'object'          => 'charge',
                'outcome'         => ['type' => 'venta_exitosa'],
            ], 201),
        ]);

        $response = $this->post(
            route('checkout.process', ['locale' => self::LOCALE]),
            $this->validPaymentPayload()
        );

        $response->assertRedirectToRoute('checkout.thanks', ['locale' => self::LOCALE]);

        // Booking was created and marked paid
        $this->assertDatabaseHas('bookings', [
            'customer_email'    => 'juan@example.com',
            'payment_status'    => 'paid',
            'status'            => 'confirmed',
            'payment_reference' => 'chr_test_abc123',
            'payment_method'    => 'culqi',
        ]);
    }

    public function test_process_payment_with_failed_token_marks_booking_failed(): void
    {
        Mail::fake();

        $tour = $this->tour();
        $this->addTourToCart($tour);

        // Mock Culqi returning a 422 / error
        Http::fake([
            'api.culqi.com/v2/charges' => Http::response([
                'object'       => 'error',
                'type'         => 'card_error',
                'user_message' => 'La tarjeta fue rechazada.',
            ], 422),
        ]);

        $response = $this->post(
            route('checkout.process', ['locale' => self::LOCALE]),
            $this->validPaymentPayload()
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // At least one booking was created and marked failed
        $this->assertDatabaseHas('bookings', [
            'customer_email' => 'juan@example.com',
            'payment_status' => 'failed',
        ]);
    }

    public function test_validation_rejects_invalid_email_phone_date(): void
    {
        $tour = $this->tour();
        $this->addTourToCart($tour);

        $response = $this->post(
            route('checkout.process', ['locale' => self::LOCALE]),
            [
                'customer_name'  => 'Test User',
                'customer_email' => 'not-an-email',
                'customer_phone' => '12345',          // invalid format
                'travel_date'    => now()->subDay()->format('Y-m-d'), // past date
                'culqi_token'    => 'tkn_test',
            ]
        );

        $response->assertSessionHasErrors(['customer_email', 'customer_phone', 'travel_date']);
    }

    public function test_thanks_page_renders_after_successful_payment(): void
    {
        $tour = $this->tour();

        // Simulate session with last_bookings
        $booking = Booking::create([
            'tour_id'             => $tour->id,
            'tour_title_snapshot' => $tour->title_es,
            'customer_name'       => 'Ana López',
            'customer_email'      => 'ana@example.com',
            'customer_phone'      => '987000001',
            'travel_date'         => now()->addDays(10)->format('Y-m-d'),
            'adults'              => 2,
            'children'            => 0,
            'unit_price'          => 150.00,
            'total_price'         => 300.00,
            'currency'            => 'USD',
            'status'              => 'confirmed',
            'payment_status'      => 'paid',
            'payment_method'      => 'culqi',
            'payment_reference'   => 'chr_test_xxx',
            'locale'              => 'es',
        ]);

        $response = $this->withSession(['last_bookings' => [$booking->toArray()]])
            ->get(route('checkout.thanks', ['locale' => self::LOCALE]));

        $response->assertOk();
        $response->assertViewIs('checkout.thanks');
        $response->assertViewHas('bookings');
    }

    public function test_booking_email_is_queued_after_success(): void
    {
        Mail::fake();

        $tour = $this->tour();
        $this->addTourToCart($tour);

        Http::fake([
            'api.culqi.com/v2/charges' => Http::response([
                'id'            => 'chr_test_mail_check',
                'amount'        => 45000,
                'currency_code' => 'USD',
                'object'        => 'charge',
            ], 201),
        ]);

        $this->post(
            route('checkout.process', ['locale' => self::LOCALE]),
            $this->validPaymentPayload()
        );

        // BookingConfirmed implements ShouldQueue; with QUEUE_CONNECTION=sync
        // and Mail::fake() the mailable is captured as "queued"
        Mail::assertQueued(BookingConfirmed::class, function (BookingConfirmed $mail): bool {
            return $mail->toEmail === 'juan@example.com';
        });
    }
}
