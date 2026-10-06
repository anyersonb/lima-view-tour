<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Hallazgos #1 y #2 del informe de seguridad 2026-09-30: el `payer` que se
 * manda a PayPal es solo comodidad. Se sanea a los límites de Orders v2, y si
 * PayPal lo rechaza se reintenta una vez sin él; nunca un 500 por esto, y
 * nunca PII del payer en los logs.
 */
class PaypalPayerSanitizationTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALE = 'es';

    private function addToCart(): void
    {
        $tour = Tour::factory()->create(['price' => 150.00, 'is_published' => true]);
        app(CartService::class)->add($tour, 2, 0, now()->addDays(10)->toDateString());
    }

    private function postCreate(array $overrides = [])
    {
        return $this->postJson(route('checkout.paypal.create', ['locale' => self::LOCALE]), array_merge([
            'customer_name' => 'Juan Pérez García',
            'customer_email' => 'juan@example.com',
            'customer_phone' => '+51987654321',
            'accept_terms' => true,
        ], $overrides));
    }

    /** Payer enviado en la primera llamada a /v2/checkout/orders. */
    private function sentOrderPayloads(): array
    {
        return Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/v2/checkout/orders'))
            ->map(fn ($pair) => $pair[0]->data())
            ->values()
            ->all();
    }

    private function fakeOk(): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*/v2/checkout/orders' => Http::response(['id' => 'ORDER-OK'], 201),
        ]);
    }

    public function test_oversized_or_invalid_subfields_are_truncated_or_omitted_and_order_is_created(): void
    {
        $this->fakeOk();
        $this->addToCart();

        $response = $this->postCreate([
            'customer_name' => 'Ana '.str_repeat('B', 200),
            'customer_email' => 'a@b',
            'customer_phone' => '+519876543210123', // 15 dígitos
        ]);

        $response->assertOk()->assertJson(['id' => 'ORDER-OK']);

        $payloads = $this->sentOrderPayloads();
        $this->assertCount(1, $payloads);
        $payer = $payloads[0]['payer'];

        $this->assertSame('Ana', $payer['name']['given_name']);
        $this->assertSame(140, mb_strlen($payer['name']['surname']));
        $this->assertArrayNotHasKey('email_address', $payer);
        $this->assertArrayNotHasKey('phone', $payer);
    }

    public function test_sixteen_digit_or_formatted_phone_does_not_block_create_order(): void
    {
        // B1: a phone the payer cannot carry (16 digits, spaces) must be
        // omitted from the payer, never turn into a 422/500.
        foreach (['+5198765432101234', '+51 987 654 321 ext 1234'] as $phone) {
            $this->fakeOk();
            $this->addToCart();

            $this->postCreate(['customer_phone' => $phone])
                ->assertOk()
                ->assertJson(['id' => 'ORDER-OK']);

            $payers = array_map(fn ($p) => $p['payer'] ?? [], $this->sentOrderPayloads());
            $this->assertArrayNotHasKey('phone', end($payers));
        }
    }

    public function test_capture_with_invalid_phone_returns_translatable_field_error(): void
    {
        foreach (['es', 'en', 'pt'] as $locale) {
            $response = $this->postJson(route('checkout.paypal.capture', ['locale' => $locale]), [
                'orderID' => 'X',
                'customer_name' => 'Ana',
                'customer_email' => 'a@example.com',
                'customer_phone' => '+5198765432101234',
                'travel_date' => now()->addDays(10)->format('Y-m-d'),
            ]);

            $response->assertStatus(422)->assertJsonValidationErrors('customer_phone');
            app()->setLocale($locale);
            $this->assertSame(
                __('checkout_paypal.phone_invalid'),
                $response->json('errors.customer_phone.0')
            );
        }
    }

    public function test_long_single_word_name_is_truncated_to_140_in_both_subfields(): void
    {
        $this->fakeOk();
        $this->addToCart();

        $this->postCreate(['customer_name' => str_repeat('Á', 200)])->assertOk();

        $payer = $this->sentOrderPayloads()[0]['payer'];
        $this->assertSame(140, mb_strlen($payer['name']['given_name']));
        $this->assertSame(140, mb_strlen($payer['name']['surname']));
    }

    public function test_valid_payer_is_sent_intact(): void
    {
        $this->fakeOk();
        $this->addToCart();

        $this->postCreate()->assertOk();

        $payer = $this->sentOrderPayloads()[0]['payer'];
        $this->assertSame('Juan', $payer['name']['given_name']);
        $this->assertSame('Pérez García', $payer['name']['surname']);
        $this->assertSame('juan@example.com', $payer['email_address']);
        $this->assertSame('51987654321', $payer['phone']['phone_number']['national_number']);
    }

    public function test_paypal_payer_rejection_retries_once_without_payer_and_succeeds(): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*/v2/checkout/orders' => Http::sequence()
                ->push([
                    'name' => 'UNPROCESSABLE_ENTITY',
                    'debug_id' => 'dbg123',
                    'details' => [['field' => '/payer/email_address', 'issue' => 'INVALID_PARAMETER_SYNTAX', 'value' => 'juan@example.com']],
                ], 422)
                ->push(['id' => 'ORDER-RETRY'], 201),
        ]);
        $this->addToCart();

        $this->postCreate()->assertOk()->assertJson(['id' => 'ORDER-RETRY']);

        $payloads = $this->sentOrderPayloads();
        $this->assertCount(2, $payloads);
        $this->assertArrayHasKey('payer', $payloads[0]);
        $this->assertArrayNotHasKey('payer', $payloads[1]);
        // El importe no cambia entre intentos: sigue saliendo del servidor.
        $this->assertSame($payloads[0]['purchase_units'], $payloads[1]['purchase_units']);
        $this->assertSame('300.00', $payloads[1]['purchase_units'][0]['amount']['value']);
        $this->assertSame('NO_SHIPPING', $payloads[1]['application_context']['shipping_preference']);
    }

    public function test_retry_happens_only_once(): void
    {
        $reject = [
            'name' => 'UNPROCESSABLE_ENTITY',
            'details' => [['field' => '/payer/name/surname', 'issue' => 'INVALID_STRING_LENGTH']],
        ];
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*/v2/checkout/orders' => Http::response($reject, 422),
        ]);
        $this->addToCart();

        $this->postCreate()->assertStatus(500);

        $this->assertCount(2, $this->sentOrderPayloads());
    }

    public function test_non_payer_4xx_is_not_retried(): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*/v2/checkout/orders' => Http::response([
                'name' => 'UNPROCESSABLE_ENTITY',
                'details' => [['field' => '/purchase_units/@reference_id=default/amount/value', 'issue' => 'INVALID_PARAMETER_VALUE']],
            ], 422),
        ]);
        $this->addToCart();

        $this->postCreate()->assertStatus(500);

        $this->assertCount(1, $this->sentOrderPayloads());
    }

    public function test_paypal_error_logs_contain_no_payer_pii(): void
    {
        $logged = [];
        Log::listen(function (MessageLogged $e) use (&$logged): void {
            $logged[] = $e->message.' '.json_encode($e->context);
        });

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*/v2/checkout/orders' => Http::response([
                'name' => 'UNPROCESSABLE_ENTITY',
                'debug_id' => 'dbg999',
                'message' => 'Echo juan@example.com 51987654321',
                'details' => [
                    ['field' => '/payer/email_address', 'issue' => 'INVALID_PARAMETER_SYNTAX', 'value' => 'juan@example.com'],
                    ['field' => '/payer/phone/phone_number/national_number', 'issue' => 'INVALID_STRING_LENGTH', 'value' => '51987654321', 'description' => 'Juan Pérez'],
                ],
            ], 422),
        ]);
        $this->addToCart();

        $this->postCreate()->assertStatus(500);

        $all = implode("\n", $logged);
        // Control positivo: sí se registró el fallo con ids y códigos.
        $this->assertStringContainsString('paypal.create_order.failed', $all);
        $this->assertStringContainsString('dbg999', $all);
        $this->assertStringContainsString('INVALID_PARAMETER_SYNTAX', $all);
        // Y ninguna PII.
        $this->assertStringNotContainsString('juan@example.com', $all);
        $this->assertStringNotContainsString('51987654321', $all);
        $this->assertStringNotContainsString('Pérez', $all);
        $this->assertStringNotContainsString('Pérez', $all);
    }

    public function test_capture_and_get_order_error_logs_contain_no_payer_pii(): void
    {
        $logged = [];
        Log::listen(function (MessageLogged $e) use (&$logged): void {
            $logged[] = $e->message.' '.json_encode($e->context);
        });

        $body = [
            'name' => 'UNPROCESSABLE_ENTITY',
            'debug_id' => 'dbgCAP',
            'message' => 'Echo juan@example.com',
            'payer' => ['email_address' => 'juan@example.com', 'name' => ['given_name' => 'Juan', 'surname' => 'Zuluaga']],
            'details' => [['issue' => 'PAYER_ACTION_REQUIRED', 'value' => 'juan@example.com', 'description' => 'Zuluaga']],
        ];
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*/v2/checkout/orders/ORD-X/capture' => Http::response($body, 422),
            '*/v2/checkout/orders/ORD-X' => Http::response($body, 404),
        ]);

        $svc = app(\App\Services\PayPalService::class);
        foreach ([fn () => $svc->captureOrder('ORD-X'), fn () => $svc->getOrder('ORD-X')] as $call) {
            try {
                $call();
                $this->fail('Expected exception');
            } catch (\RuntimeException $e) {
                $logged[] = $e->getMessage();
            }
        }

        $all = implode("\n", $logged);
        $this->assertStringContainsString('paypal.capture_order.failed', $all);
        $this->assertStringContainsString('paypal.get_order.failed', $all);
        $this->assertStringContainsString('dbgCAP', $all);
        $this->assertStringContainsString('PAYER_ACTION_REQUIRED', $all);
        $this->assertStringNotContainsString('juan@example.com', $all);
        $this->assertStringNotContainsString('Zuluaga', $all);
        $this->assertStringNotContainsString('Juan', $all);
    }
}
