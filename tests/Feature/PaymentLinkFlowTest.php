<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\PaymentLink;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentLinkFlowTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    private function tour(array $overrides = []): Tour
    {
        return Tour::factory()->create(array_merge(['price' => 150.00, 'is_published' => true], $overrides));
    }

    private function link(array $overrides = []): PaymentLink
    {
        return PaymentLink::factory()->create(array_merge([
            'amount' => 150.00,
            'adults' => 1,
            'children' => 0,
        ], $overrides));
    }

    private function fakePaypal(array $extra = []): void
    {
        Http::fake(array_merge([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response(['id' => 'ORDER-1'], 201),
        ], $extra));
    }

    private function fakeGetOrder(string $orderId, float $amount, string $currency = 'USD'): void
    {
        // Http::fake() reemplaza cualquier fake anterior (no se acumulan) —
        // por eso el token OAuth va SIEMPRE incluido acá también: sin él, el
        // primer accessToken() de captureOrder() intenta una llamada HTTP
        // real y revienta con un error de red/SSL, no con el 500 que
        // parecería un bug de negocio.
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            "api-m.sandbox.paypal.com/v2/checkout/orders/{$orderId}" => Http::response([
                'id' => $orderId,
                'purchase_units' => [['amount' => ['currency_code' => $currency, 'value' => number_format($amount, 2, '.', '')]]],
            ], 200),
            "api-m.sandbox.paypal.com/v2/checkout/orders/{$orderId}/capture" => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAPTURE-1']]]]],
            ], 200),
        ]);
    }

    private function validCaptureCall(PaymentLink $link, string $orderId = 'ORDER-1'): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('payment-links.paypal.capture', ['code' => $link->code]), [
            'orderID' => $orderId,
            'customer_name' => 'Juan Pérez',
            'customer_email' => 'juan@example.com',
            'customer_phone' => '+51987654321',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  show()
    // ─────────────────────────────────────────────────────────────

    public function test_show_renders_paypal_button_for_usable_link(): void
    {
        $link = $this->link(['tour_id' => $this->tour()->id]);

        $response = $this->get(route('payment-links.show', ['code' => $link->code]));

        $response->assertOk();
        $response->assertSee('paypal-buttons', false);
        $response->assertSee('noindex,nofollow', false);
    }

    public function test_show_hides_button_for_unknown_code(): void
    {
        $response = $this->get(route('payment-links.show', ['code' => 'does-not-exist']));

        // Item 9: código inexistente → 404 REAL, no un 200 con mensaje
        // amable (rompe semántica HTTP para monitoreo/crawlers).
        $response->assertNotFound();
        $response->assertDontSee('id="paypal-buttons"', false);
    }

    public function test_show_sets_x_robots_tag_header_and_single_meta_tag(): void
    {
        // Item 7: X-Robots-Tag en la cabecera (no solo el <meta>), y UN
        // SOLO <meta name="robots"> — antes el layout imprimía su
        // "index,follow" por defecto Y esta vista agregaba OTRO
        // "noindex,nofollow" con @push('head'), dejando dos etiquetas
        // conflictivas en el mismo HTML.
        $link = $this->link(['tour_id' => $this->tour()->id]);

        $response = $this->get(route('payment-links.show', ['code' => $link->code]));

        $response->assertOk();
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertSame(1, substr_count($response->getContent(), '<meta name="robots"'));
        $response->assertDontSee('index,follow', false);
    }

    public function test_show_hides_button_for_paid_link(): void
    {
        $link = $this->link(['status' => 'paid', 'paid_at' => now()]);

        $response = $this->get(route('payment-links.show', ['code' => $link->code]));

        $response->assertOk();
        $response->assertDontSee('id="paypal-buttons"', false);
    }

    public function test_show_hides_button_for_expired_link(): void
    {
        $link = $this->link(['expires_at' => now()->subDay()]);

        $response = $this->get(route('payment-links.show', ['code' => $link->code]));

        $response->assertOk();
        $response->assertDontSee('id="paypal-buttons"', false);

        // Self-heal: la visita debe dejarlo marcado 'expired' en BD.
        $this->assertDatabaseHas('payment_links', ['id' => $link->id, 'status' => 'expired']);
    }

    public function test_show_hides_button_for_cancelled_link(): void
    {
        $link = $this->link(['status' => 'cancelled']);

        $response = $this->get(route('payment-links.show', ['code' => $link->code]));

        $response->assertOk();
        $response->assertDontSee('id="paypal-buttons"', false);
    }

    // ─────────────────────────────────────────────────────────────
    //  createOrder() — el monto SIEMPRE sale del link, nunca del request
    // ─────────────────────────────────────────────────────────────

    public function test_create_order_uses_link_amount_and_ignores_request_amount(): void
    {
        $link = $this->link(['amount' => 275.50]);
        $this->fakePaypal();

        $response = $this->postJson(route('payment-links.paypal.create', ['code' => $link->code]), [
            // Manipulación: intenta que el servidor cobre 1 dólar.
            'amount' => 1,
        ]);

        $response->assertOk();
        $response->assertJson(['id' => 'ORDER-1']);

        // Debe existir EXACTAMENTE una request de creación de orden, y su
        // importe debe ser el del link (275.50) — nunca el "1" manipulado
        // en el body del request a nuestro propio endpoint.
        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api-m.sandbox.paypal.com/v2/checkout/orders') {
                return false;
            }

            return ($request->data()['purchase_units'][0]['amount']['value'] ?? null) === '275.50';
        });

        $this->assertDatabaseHas('payment_links', ['id' => $link->id, 'paypal_order_id' => 'ORDER-1']);
    }

    public function test_create_order_rejects_paid_link(): void
    {
        $link = $this->link(['status' => 'paid', 'paid_at' => now()]);
        $this->fakePaypal();

        $response = $this->postJson(route('payment-links.paypal.create', ['code' => $link->code]), []);

        $response->assertStatus(422);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/v2/checkout/orders') && ! str_contains($request->url(), '/capture'));
    }

    public function test_create_order_rejects_unknown_code(): void
    {
        $response = $this->postJson(route('payment-links.paypal.create', ['code' => 'nope']), []);

        $response->assertStatus(404);
    }

    public function test_create_order_reuses_a_live_previous_order_instead_of_overwriting_it(): void
    {
        // B-6: si ya hay una orden CREATED/APPROVED viva para este link, un
        // segundo POST a este mismo endpoint (otro poseedor del link, o una
        // recarga de página) no debe pisarla — invalidaría el botón de
        // PayPal que el comprador legítimo ya tiene abierto.
        $link = $this->link(['paypal_order_id' => 'ORDER-OLD']);

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-OLD' => Http::response([
                'id' => 'ORDER-OLD',
                'status' => 'APPROVED',
                'purchase_units' => [['amount' => ['currency_code' => 'USD', 'value' => '150.00']]],
            ], 200),
        ]);

        $response = $this->postJson(route('payment-links.paypal.create', ['code' => $link->code]), []);

        $response->assertOk();
        $response->assertJson(['id' => 'ORDER-OLD']);
        Http::assertNotSent(fn ($request) => $request->url() === 'https://api-m.sandbox.paypal.com/v2/checkout/orders');
    }

    // ─────────────────────────────────────────────────────────────
    //  captureOrder() — reserva creada, idempotencia, rechazos
    // ─────────────────────────────────────────────────────────────

    public function test_capture_creates_booking_with_payment_link_contract(): void
    {
        Mail::fake();

        $tour = $this->tour();
        $link = $this->link(['tour_id' => $tour->id, 'amount' => 150.00, 'paypal_order_id' => 'ORDER-1']);

        $this->fakeGetOrder('ORDER-1', 150.00);

        $response = $this->validCaptureCall($link);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('bookings', [
            'customer_email' => 'juan@example.com',
            'tour_id' => $tour->id,
            'payment_status' => 'paid',
            'status' => 'confirmed',
            'payment_method' => 'payment_link',
            'payment_reference' => 'CAPTURE-1',
            'total_price' => 150.00,
        ]);

        $link->refresh();
        $this->assertSame('paid', $link->status);
        $this->assertSame('CAPTURE-1', $link->paypal_capture_id);
        $this->assertNotNull($link->booking_id);
    }

    public function test_capture_with_manipulated_amount_is_rejected(): void
    {
        $link = $this->link(['amount' => 150.00, 'paypal_order_id' => 'ORDER-1']);

        // El servidor de PayPal reporta un importe distinto al del link
        // (simula que el orderID no corresponde al monto esperado).
        $this->fakeGetOrder('ORDER-1', 1.00);

        $response = $this->validCaptureCall($link);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('bookings', ['payment_method' => 'payment_link']);

        $link->refresh();
        $this->assertSame('pending', $link->status);
        $this->assertNull($link->paypal_capture_id);
    }

    public function test_double_capture_creates_only_one_booking(): void
    {
        Mail::fake();

        $link = $this->link(['tour_id' => $this->tour()->id, 'amount' => 150.00, 'paypal_order_id' => 'ORDER-1']);
        $this->fakeGetOrder('ORDER-1', 150.00);

        $first = $this->validCaptureCall($link);
        $first->assertOk();
        $first->assertJson(['success' => true]);

        // Segunda captura del MISMO orderID: PayPal ya no puede volver a
        // capturarla en la vida real; acá el fake simplemente vuelve a
        // responder COMPLETED, así que lo que prueba la idempotencia es nuestra
        // propia rama "already_paid" (mismo orderID + mismo link ya 'paid').
        $second = $this->validCaptureCall($link);
        $second->assertOk();
        $second->assertJson(['success' => true]);

        $this->assertSame(1, Booking::where('payment_method', 'payment_link')->count());
    }

    public function test_capture_rejects_order_id_mismatch(): void
    {
        $link = $this->link(['amount' => 150.00, 'paypal_order_id' => 'ORDER-1']);
        $this->fakeGetOrder('ORDER-1', 150.00);

        $response = $this->validCaptureCall($link, 'SOME-OTHER-ORDER');

        $response->assertStatus(422);
        $this->assertDatabaseMissing('bookings', ['payment_method' => 'payment_link']);
    }

    public function test_capture_rejects_expired_link(): void
    {
        $link = $this->link(['amount' => 150.00, 'paypal_order_id' => 'ORDER-1', 'expires_at' => now()->subHour()]);
        $this->fakeGetOrder('ORDER-1', 150.00);

        $response = $this->validCaptureCall($link);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('bookings', ['payment_method' => 'payment_link']);
    }

    public function test_capture_rejects_paid_link(): void
    {
        // Link ya pagado con OTRA orden (no la que llega ahora): no coincide
        // con la rama idempotente (orderID distinto) ni pasa isUsable()
        // (status ya no es 'pending') → rechazado, sin volver a cobrar.
        $link = $this->link([
            'amount' => 150.00,
            'paypal_order_id' => 'ORDER-1',
            'status' => 'paid',
            'paid_at' => now(),
            'paypal_capture_id' => 'ALREADY-CAPTURED',
        ]);
        $this->fakeGetOrder('ORDER-2', 150.00);

        $response = $this->validCaptureCall($link, 'ORDER-2');

        $response->assertStatus(422);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/v2/checkout/orders/ORDER-2/capture'));
    }

    public function test_capture_rejects_cancelled_link(): void
    {
        $link = $this->link(['amount' => 150.00, 'paypal_order_id' => 'ORDER-1', 'status' => 'cancelled']);
        $this->fakeGetOrder('ORDER-1', 150.00);

        $response = $this->validCaptureCall($link);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('bookings', ['payment_method' => 'payment_link']);
    }

    // ─────────────────────────────────────────────────────────────
    //  A-1 — PII: customer_* (prefill del admin) vs buyer_* (comprador real)
    // ─────────────────────────────────────────────────────────────

    public function test_capture_writes_buyer_columns_and_never_overwrites_admin_prefill(): void
    {
        Mail::fake();

        $tour = $this->tour();
        $link = $this->link([
            'tour_id' => $tour->id,
            'customer_email' => null, // el admin no precargó nada para este link genérico
            'paypal_order_id' => 'ORDER-1',
        ]);
        $this->fakeGetOrder('ORDER-1', 150.00);

        $this->validCaptureCall($link); // paga con juan@example.com (ver validCaptureCall())

        $link->refresh();
        // N-1 (decisión del coordinador 2026-09-25): ya no existe el link
        // multiuso — TODO link queda 'paid' después de su único cobro.
        $this->assertSame('paid', $link->status);
        $this->assertNull($link->customer_email); // el prefill del admin NUNCA cambia
        $this->assertSame('juan@example.com', $link->buyer_email); // lo que de verdad pagó va a buyer_*
        $this->assertSame('Juan Pérez', $link->buyer_name);

        // El link pagado no vuelve a mostrar el formulario — y de paso no
        // filtra a nadie que reabra el mismo enlace los datos de quien pagó.
        $response = $this->get(route('payment-links.show', ['code' => $link->code]));
        $response->assertOk();
        $response->assertDontSee('juan@example.com', false);
        $response->assertDontSee('987654321', false);
    }

    // ─────────────────────────────────────────────────────────────
    //  N-1 — decisión del coordinador: se eliminan los links multiuso
    // ─────────────────────────────────────────────────────────────

    public function test_single_use_is_always_forced_true_even_if_explicitly_set_to_false(): void
    {
        // El toggle se quitó del formulario, pero la columna se conserva —
        // el guardián debe forzar true sin importar por qué camino llegue
        // un false (factory, tinker, un seeder futuro).
        $link = PaymentLink::factory()->create(['single_use' => false]);

        $this->assertTrue($link->refresh()->single_use);

        $link->update(['single_use' => false]);

        $this->assertTrue($link->refresh()->single_use);
    }

    public function test_a_second_payment_attempt_on_an_already_paid_link_is_rejected_before_creating_a_paypal_order(): void
    {
        Mail::fake();

        $tour = $this->tour();
        $link = $this->link(['tour_id' => $tour->id, 'amount' => 150.00, 'paypal_order_id' => 'ORDER-1']);
        $this->fakeGetOrder('ORDER-1', 150.00);

        $this->validCaptureCall($link)->assertOk(); // primer pago — deja el link 'paid'
        $this->assertSame('paid', $link->refresh()->status);

        // Segundo intento: otra persona (o el mismo comprador reabriendo el
        // enlace) intenta iniciar un pago NUEVO sobre el mismo link.
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
        ]);

        $response = $this->postJson(route('payment-links.paypal.create', ['code' => $link->code]), []);

        $response->assertStatus(422);
        // El motivo de fondo: isUsable() ya es false (status='paid') ANTES
        // de que createOrder() llegue a considerar llamar a PayPal.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/v2/checkout/orders') && ! str_contains($request->url(), '/capture'));
    }

    public function test_capture_step_two_reuses_a_booking_matched_by_payment_reference_even_if_link_booking_id_is_null(): void
    {
        Mail::fake();

        $tour = $this->tour();
        $link = $this->link(['tour_id' => $tour->id, 'amount' => 150.00, 'paypal_order_id' => 'ORDER-1']);
        $this->fakeGetOrder('ORDER-1', 150.00);

        // Simula que YA existe una Booking con esta misma referencia de
        // cobro (creada por otra vía — p.ej. el webhook, en un escenario
        // donde su propio forceFill(booking_id) no llegó a correr) pero el
        // link TODAVÍA no quedó enlazado: booking_id sigue null. El
        // re-chequeo del paso 2 debe encontrarla por payment_reference, no
        // solo por $link->booking_id — si no, crearía una SEGUNDA reserva
        // para el mismo dinero cobrado una sola vez.
        $existingBooking = Booking::create([
            'tour_id' => $tour->id,
            'tour_title_snapshot' => $tour->title_es,
            'customer_name' => 'Ya Existente',
            'customer_email' => 'ya-existente@example.com',
            'customer_phone' => '999999999',
            'travel_date' => null,
            'adults' => 1,
            'children' => 0,
            'unit_price' => 150,
            'total_price' => 150,
            'currency' => 'USD',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'payment_link',
            'payment_reference' => 'CAPTURE-1',
            'locale' => 'es',
        ]);

        $response = $this->validCaptureCall($link);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertSame(1, Booking::where('payment_reference', 'CAPTURE-1')->count());
        $this->assertSame($existingBooking->id, $link->refresh()->booking_id);
    }

    // ─────────────────────────────────────────────────────────────
    //  N-3 — el correo de credenciales de invitado no sobrevive un rollback
    // ─────────────────────────────────────────────────────────────

    public function test_guest_credentials_email_is_discarded_if_the_outer_transaction_rolls_back_after_booking_creation(): void
    {
        Mail::fake();

        $tour = $this->tour();
        $link = $this->link(['tour_id' => $tour->id, 'amount' => 150.00, 'paypal_order_id' => 'ORDER-1']);
        $this->fakeGetOrder('ORDER-1', 150.00);

        // El 2º save() de PaymentLink dentro de captureOrder() es el del
        // PASO 2 ($fresh->forceFill(['booking_id' => ...])->save()) — para
        // entonces, createBookings() YA creó (en su propia sub-transacción,
        // anidada dentro de esta) el Customer invitado y la Booking, y ya
        // encoló el correo de credenciales vía DB::afterCommit(). Al hacer
        // fallar ESTE save(), la transacción completa del paso 2 revierte
        // TODO lo anterior — el correo NUNCA debió llegar para una cuenta
        // que quedó sin existir.
        $saveCount = 0;
        PaymentLink::saving(function () use (&$saveCount) {
            $saveCount++;
            if ($saveCount === 2) {
                throw new \RuntimeException('Simulated failure right after booking creation');
            }
        });

        try {
            $this->post(route('payment-links.paypal.capture', ['code' => $link->code]), [
                'orderID' => 'ORDER-1',
                'customer_name' => 'Invitado Nuevo',
                'customer_email' => 'invitado-nuevo@example.com',
                'customer_phone' => null,
            ]);
        } finally {
            PaymentLink::flushEventListeners();
        }

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('customers', ['email' => 'invitado-nuevo@example.com']);
    }

    // ─────────────────────────────────────────────────────────────
    //  M-2 — carrera capture síncrono vs webhook: nunca dos reservas
    // ─────────────────────────────────────────────────────────────

    public function test_capture_reuses_booking_already_created_by_a_concurrent_webhook(): void
    {
        Mail::fake();

        $tour = $this->tour();
        $link = $this->link(['tour_id' => $tour->id, 'amount' => 150.00, 'paypal_order_id' => 'ORDER-1']);
        $this->fakeGetOrder('ORDER-1', 150.00);

        // Simula que el webhook YA reconcilió esta captura y creó la
        // Booking ANTES de que el flujo síncrono de captura llegue al paso
        // 2 (misma fila, mismo lockForUpdate() que WebhookController usa).
        $existingBooking = Booking::create([
            'tour_id' => $tour->id,
            'tour_title_snapshot' => $tour->title_es,
            'customer_name' => 'Webhook Ganador',
            'customer_email' => 'webhook@example.com',
            'customer_phone' => '999999999',
            'travel_date' => null,
            'adults' => 1,
            'children' => 0,
            'unit_price' => 150,
            'total_price' => 150,
            'currency' => 'USD',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'payment_link',
            'payment_reference' => 'CAPTURE-1',
            'locale' => 'es',
        ]);
        $link->forceFill(['booking_id' => $existingBooking->id])->save();

        $response = $this->validCaptureCall($link);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Sin el re-chequeo bajo lock (M-2), el paso 2 del flujo síncrono
        // habría creado una SEGUNDA Booking e ignorado la que ya existía.
        $this->assertSame(1, Booking::where('payment_method', 'payment_link')->count());
        $this->assertDatabaseHas('bookings', ['id' => $existingBooking->id, 'customer_email' => 'webhook@example.com']);
    }

    // ─────────────────────────────────────────────────────────────
    //  M-3 — PayPal ya cobró, la escritura en BD falla DESPUÉS
    // ─────────────────────────────────────────────────────────────

    public function test_capture_reports_captured_not_failed_when_db_write_fails_after_paypal_capture(): void
    {
        $tour = $this->tour();
        $link = $this->link(['tour_id' => $tour->id, 'amount' => 150.00, 'paypal_order_id' => 'ORDER-1']);
        $this->fakeGetOrder('ORDER-1', 150.00);

        // Simula que TODO en PayPal salió bien, pero el forceFill()->save()
        // posterior (dentro de la misma tx) revienta por una falla de BD.
        PaymentLink::saving(function () {
            throw new \RuntimeException('Simulated DB failure right after PayPal capture');
        });

        try {
            $response = $this->validCaptureCall($link);
        } finally {
            PaymentLink::flushEventListeners();
        }

        // NUNCA "pago fallido": el dinero YA se cobró en PayPal.
        $response->assertOk();
        $response->assertJson(['success' => false, 'code' => 'payment_captured_booking_pending']);
        $response->assertJsonFragment(['reference' => 'CAPTURE-1']);

        // El rastro del cobro quedó en una escritura AISLADA fuera de la tx
        // que reventó — y el link queda bloqueado (isUsable() = false) para
        // que un reintento del comprador no pueda volver a cobrar.
        $this->assertDatabaseHas('payment_links', [
            'id' => $link->id,
            'paypal_capture_id' => 'CAPTURE-1',
            'status' => 'paid',
        ]);
        $this->assertFalse($link->refresh()->isUsable());
    }

    // ─────────────────────────────────────────────────────────────
    //  B-7 — replay con code+orderID de otra persona
    // ─────────────────────────────────────────────────────────────

    public function test_replay_with_mismatched_email_does_not_fill_session_with_strangers_booking(): void
    {
        Mail::fake();

        $tour = $this->tour();
        $link = $this->link(['tour_id' => $tour->id, 'amount' => 150.00, 'paypal_order_id' => 'ORDER-1']);
        $this->fakeGetOrder('ORDER-1', 150.00);

        $this->validCaptureCall($link); // paga con juan@example.com — deja el link 'paid'

        // Simula que quien reintenta NO es el mismo navegador que pagó: la
        // sesión de prueba es compartida entre requests del mismo test, así
        // que sin este flush la aserción de abajo pasaría por la sesión ya
        // llena del pago legítimo, no por el guardián de B-7.
        session()->flush();

        // Replay: mismo code + mismo orderID (los únicos dos datos que la
        // rama idempotente exige), pero un correo DISTINTO al de la
        // reserva real — como si alguien con el orderID filtrado intentara
        // "entrar" a la reserva ajena.
        $replay = $this->post(route('payment-links.paypal.capture', ['code' => $link->code]), [
            'orderID' => 'ORDER-1',
            'customer_name' => 'Otra Persona',
            'customer_email' => 'otra-persona@example.com',
            'customer_phone' => null,
        ]);

        $replay->assertOk();
        $replay->assertJson(['success' => true]);
        $this->assertEmpty(session('last_bookings', []));
    }

    // ─────────────────────────────────────────────────────────────
    //  N-2 — captura ambigua: PayPal cobró pero nuestra respuesta se perdió
    // ─────────────────────────────────────────────────────────────

    public function test_create_order_detects_a_previously_completed_order_and_does_not_charge_again(): void
    {
        // Caso 1: createOrder() encuentra que la orden que tenía guardada ya
        // está COMPLETED en PayPal (un timeout previo nos impidió
        // enterarnos) — no debe crear una orden nueva (eso cobraría dos
        // veces), sino persistir el capture recuperado y avisar que ya está
        // pagado.
        $link = $this->link(['paypal_order_id' => 'ORDER-GHOST']);

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-GHOST' => Http::response([
                'id' => 'ORDER-GHOST',
                'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAPTURE-GHOST']]]]],
            ], 200),
        ]);

        $response = $this->postJson(route('payment-links.paypal.create', ['code' => $link->code]), []);

        $response->assertStatus(422);
        Http::assertNotSent(fn ($request) => $request->url() === 'https://api-m.sandbox.paypal.com/v2/checkout/orders');

        $this->assertDatabaseHas('payment_links', [
            'id' => $link->id,
            'paypal_capture_id' => 'CAPTURE-GHOST',
            'status' => 'paid',
        ]);
    }

    public function test_capture_recovers_via_get_order_when_the_capture_response_is_lost_after_paypal_already_charged(): void
    {
        // Caso 2: PayPalService::captureOrder() revienta (timeout/500) DESPUÉS
        // de que PayPal ya cobró — el catch sin $captureId debe confirmar el
        // estado REAL con getOrder() antes de decirle "pago fallido" al
        // comprador (que reintentaría y pagaría dos veces).
        Mail::fake();

        $tour = $this->tour();
        $link = $this->link(['tour_id' => $tour->id, 'amount' => 150.00, 'paypal_order_id' => 'ORDER-TIMEOUT']);

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-TIMEOUT' => Http::sequence()
                // 1ª llamada: verificación de importe ANTES de capturar — la
                // orden sigue APPROVED (aún no se había cobrado).
                ->push([
                    'id' => 'ORDER-TIMEOUT',
                    'status' => 'APPROVED',
                    'purchase_units' => [['amount' => ['currency_code' => 'USD', 'value' => '150.00']]],
                ], 200)
                // 2ª llamada: recuperación DESPUÉS de que capture() reventó
                // — PayPal YA la cobró, aunque nuestra respuesta se perdió.
                ->push([
                    'id' => 'ORDER-TIMEOUT',
                    'status' => 'COMPLETED',
                    'purchase_units' => [[
                        'amount' => ['currency_code' => 'USD', 'value' => '150.00'],
                        'payments' => ['captures' => [['id' => 'CAPTURE-RECOVERED']]],
                    ]],
                ], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-TIMEOUT/capture' => Http::response(['error' => 'timeout'], 500),
        ]);

        $response = $this->validCaptureCall($link, 'ORDER-TIMEOUT');

        $response->assertOk();
        $response->assertJson(['success' => false, 'code' => 'payment_captured_booking_pending']);
        $response->assertJsonFragment(['reference' => 'CAPTURE-RECOVERED']);

        $this->assertDatabaseHas('payment_links', [
            'id' => $link->id,
            'paypal_capture_id' => 'CAPTURE-RECOVERED',
            'status' => 'paid',
        ]);
        $this->assertDatabaseMissing('bookings', ['payment_reference' => 'CAPTURE-RECOVERED']);
    }

    public function test_capture_sends_a_deterministic_idempotent_paypal_request_id(): void
    {
        // Caso 3: PayPal-Request-Id derivado del order_id — si NUESTRO
        // propio cliente reintenta el mismo capture(), PayPal debe recibir
        // la MISMA clave de idempotencia.
        Mail::fake();

        $tour = $this->tour();
        $link = $this->link(['tour_id' => $tour->id, 'amount' => 150.00, 'paypal_order_id' => 'ORDER-1']);
        $this->fakeGetOrder('ORDER-1', 150.00);

        $this->validCaptureCall($link)->assertOk();

        $expectedKey = substr(hash('sha256', 'payment-link-capture:ORDER-1'), 0, 36);

        Http::assertSent(function ($request) use ($expectedKey) {
            if (! str_contains($request->url(), '/v2/checkout/orders/ORDER-1/capture')) {
                return false;
            }

            return $request->hasHeader('PayPal-Request-Id')
                && $request->header('PayPal-Request-Id')[0] === $expectedKey;
        });
    }

    // ─────────────────────────────────────────────────────────────
    //  N-4 — B-6 no debe reutilizar una orden viva con OTRO monto
    // ─────────────────────────────────────────────────────────────

    public function test_editing_the_amount_of_a_pending_link_clears_its_stale_paypal_order_id(): void
    {
        $link = $this->link(['amount' => 150.00, 'paypal_order_id' => 'ORDER-OLD']);

        $link->update(['amount' => 200.00]);

        $this->assertNull($link->refresh()->paypal_order_id);
    }

    public function test_editing_a_pending_link_without_touching_the_amount_keeps_its_paypal_order_id(): void
    {
        // El guardián solo debe limpiar la orden cuando el MONTO cambia —
        // no en cualquier guardado (p.ej. corregir la nota interna).
        $link = $this->link(['amount' => 150.00, 'paypal_order_id' => 'ORDER-KEEP', 'note' => null]);

        $link->update(['note' => 'Cliente frecuente']);

        $this->assertSame('ORDER-KEEP', $link->refresh()->paypal_order_id);
    }

    public function test_create_order_does_not_reuse_a_live_order_whose_amount_no_longer_matches_the_link(): void
    {
        // Defensa en profundidad: simula que la orden vieja sobrevivió SIN
        // pasar por el guardián del modelo (editada directo en BD) —
        // createOrder() debe blindarse igual, nunca reutiliza una orden cuyo
        // importe ya no coincide con el link.
        $link = $this->link(['amount' => 150.00, 'paypal_order_id' => 'ORDER-OLD']);
        DB::table('payment_links')->where('id', $link->id)->update(['amount' => 200.00]);

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-OLD' => Http::response([
                'id' => 'ORDER-OLD',
                'status' => 'APPROVED',
                'purchase_units' => [['amount' => ['currency_code' => 'USD', 'value' => '150.00']]],
            ], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response(['id' => 'ORDER-NEW'], 201),
        ]);

        $response = $this->postJson(route('payment-links.paypal.create', ['code' => $link->code]), []);

        $response->assertOk();
        $response->assertJson(['id' => 'ORDER-NEW']);
    }
}
