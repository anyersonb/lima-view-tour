<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tour;
use App\Services\CartService;
use App\Support\CheckoutInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Datos reales del checkout: resumen de cancelación, horarios de salida,
 * métodos de pago y checkout sin cuenta.
 */
class CheckoutHomeDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancellation_summary_hidden_when_empty_and_localized_with_es_fallback(): void
    {
        $this->assertNull(CheckoutInfo::build(collect(), 'es')['cancellation_summary']);

        Setting::set('legal_cancellation_summary_es', 'Cancela gratis hasta 24 h antes.');
        $this->assertSame('Cancela gratis hasta 24 h antes.', CheckoutInfo::build(collect(), 'es')['cancellation_summary']);
        $this->assertSame('Cancela gratis hasta 24 h antes.', CheckoutInfo::build(collect(), 'pt')['cancellation_summary']);

        Setting::set('legal_cancellation_summary_en', 'Free cancellation up to 24 h before.');
        $this->assertSame('Free cancellation up to 24 h before.', CheckoutInfo::build(collect(), 'en')['cancellation_summary']);
    }

    public function test_departure_times_only_for_tours_with_a_real_time(): void
    {
        $with = Tour::factory()->create(['departure_time' => '05:00']);
        $without = Tour::factory()->create(['departure_time' => null]);

        $times = CheckoutInfo::departureTimes(collect([
            ['tour_id' => $with->id], ['tour_id' => $without->id], ['tour_id' => $with->id],
        ]));

        $this->assertSame([$with->id], array_keys($times));
        $this->assertSame('05:00', $times[$with->id]['time']);
        $this->assertSame('5:00 AM', $times[$with->id]['label']);
        $this->assertSame([], CheckoutInfo::departureTimes(collect()));
    }

    public function test_payment_methods_follow_config(): void
    {
        config(['services.paypal.client_id' => 'abc']);
        config(['services.culqi.enabled' => false, 'services.culqi.public_key' => 'pk', 'services.culqi.secret_key' => 'sk']);
        $m = CheckoutInfo::paymentMethods();
        $this->assertTrue($m['paypal']);
        $this->assertFalse($m['culqi'], 'Culqi apagado por CULQI_ENABLED');

        config(['services.culqi.enabled' => true]);
        $this->assertTrue(CheckoutInfo::paymentMethods()['culqi']);

        config(['services.culqi.secret_key' => '']);
        $this->assertFalse(CheckoutInfo::paymentMethods()['culqi'], 'sin llaves no hay Culqi');

        config(['services.paypal.client_id' => '']);
        Setting::set('paypal_client_id', '');
        $this->assertFalse(CheckoutInfo::paymentMethods()['paypal']);
        $this->assertFalse(CheckoutInfo::paymentMethods()['any']);
    }

    public function test_checkout_composer_exposes_checkout_info(): void
    {
        $tour = Tour::factory()->create(['departure_time' => '07:30']);
        $view = view('checkout', ['items' => collect([['tour_id' => $tour->id]])]);
        app(\App\View\Composers\CheckoutComposer::class)->compose($view);

        $info = $view->getData()['checkoutInfo'];
        $this->assertTrue($info['guest_checkout']);
        $this->assertSame('07:30', $info['departure_times'][$tour->id]['time']);
        $this->assertArrayHasKey('refund_policy_url', $info);
    }

    public function test_guest_can_book_without_an_account(): void
    {
        Mail::fake();
        $tour = Tour::factory()->create(['price' => 150, 'is_published' => true]);
        app(CartService::class)->add($tour, 2, 0, now()->addDays(10)->format('Y-m-d'));

        // Sin sesión de cliente: ninguna ruta de checkout exige login.
        $this->assertGuest('customer');
        $this->post(route('checkout.process', ['locale' => 'es']), [
            'payment_timing' => 'later',
            'customer_name' => 'Invitado Prueba',
            'customer_email' => 'invitado@example.com',
            'customer_phone' => '987654321',
            'travel_date' => now()->addDays(15)->format('Y-m-d'),
            'accept_terms' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('bookings', ['customer_email' => 'invitado@example.com']);
        $this->assertDatabaseHas('customers', ['email' => 'invitado@example.com']);
    }
}
