<?php

namespace Tests\Feature;

use App\Models\PaymentLink;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentLinkLocaleTest extends TestCase
{
    use RefreshDatabase;

    private function link(array $o = []): PaymentLink
    {
        $tour = Tour::factory()->create(['price' => 150, 'is_published' => true]);

        return PaymentLink::factory()->create(array_merge(['tour_id' => $tour->id, 'amount' => 150, 'adults' => 1, 'children' => 0], $o));
    }

    private function page(PaymentLink $link, string $acceptLang, string $query = ''): \Illuminate\Testing\TestResponse
    {
        config(['services.paypal.client_id' => 'test-client']);

        return $this->withHeaders(['Accept-Language' => $acceptLang])->get('/pagar/'.$link->code.$query);
    }

    public function test_link_without_locale_follows_browser(): void
    {
        $link = $this->link();
        $this->page($link, 'es')->assertSee('locale=es_PE', false)->assertSee('<html lang="es"', false);
        $this->page($link, 'en')->assertSee('locale=en_US', false);
    }

    public function test_link_locale_wins_over_browser(): void
    {
        $link = $this->link(['locale' => 'en']);
        $this->page($link, 'es')
            ->assertSee('locale=en_US', false)
            ->assertSee('1 Adult', false)
            ->assertDontSee('1 Adults', false)
            ->assertSee('100% secure payment with PayPal');
    }

    public function test_query_lang_wins_over_link_locale_and_invalid_is_ignored(): void
    {
        $link = $this->link(['locale' => 'en']);
        $this->page($link, 'es', '?lang=pt')->assertSee('locale=pt_BR', false)->assertSee('Pagamento 100% seguro com PayPal');
        $this->page($link, 'es', '?lang=fr')->assertSee('locale=en_US', false);
    }

    public function test_js_messages_are_translated_and_post_urls_carry_the_shown_locale(): void
    {
        $link = $this->link(['locale' => 'pt']);
        $this->page($link, 'es')
            ->assertSee('Ocorreu um erro com o PayPal', false)
            ->assertSee('lang=pt', false)
            ->assertDontSee('Ocurrió un error con PayPal', false);
    }

    public function test_capture_uses_link_locale_for_booking_and_422_messages(): void
    {
        Mail::fake();
        $link = $this->link(['locale' => 'pt', 'paypal_order_id' => 'ORDER-1']);

        $this->withHeaders(['Accept-Language' => 'es'])
            ->postJson(route('payment-links.paypal.capture', ['code' => $link->code]), [
                'orderID' => 'ORDER-1', 'customer_name' => 'A', 'customer_email' => 'no-es-email',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.customer_email.0', 'O campo endereço de e-mail deve ser um endereço de e-mail válido.');

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 't'], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-1' => Http::response([
                'id' => 'ORDER-1', 'purchase_units' => [['amount' => ['currency_code' => 'USD', 'value' => '150.00']]],
            ], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-1/capture' => Http::response([
                'status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP-1']]]]],
            ], 200),
        ]);

        $this->withHeaders(['Accept-Language' => 'es'])
            ->postJson(route('payment-links.paypal.capture', ['code' => $link->code]), [
                'orderID' => 'ORDER-1', 'customer_name' => 'Ana', 'customer_email' => 'ana@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('redirect', route('checkout.thanks', ['locale' => 'pt']));

        $this->assertDatabaseHas('bookings', ['customer_email' => 'ana@example.com', 'locale' => 'pt']);
    }

    public function test_thanks_page_is_translated(): void
    {
        $this->withSession(['last_bookings' => [[
            'reference' => 'LV-1', 'tour_title_snapshot' => 'Tour', 'travel_date' => '2026-05-03',
            'adults' => 1, 'children' => 0, 'total_price' => 10, 'currency' => 'USD', 'payment_status' => 'paid',
        ]]])->get(route('checkout.thanks', ['locale' => 'en']))
            ->assertSee('Your booking details')
            ->assertSee('Confirmed')
            ->assertSee('1 person')
            ->assertSee('03 May 2026')
            ->assertDontSee('Detalle de tu reserva');
    }
}
