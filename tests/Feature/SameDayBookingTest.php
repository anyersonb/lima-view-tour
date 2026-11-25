<?php

namespace Tests\Feature;

use App\Mail\BookingNotificationAdmin;
use App\Models\Booking;
use App\Models\PaymentLink;
use App\Models\Setting;
use App\Models\Tour;
use App\Services\BookingNotifier;
use App\Services\CartService;
use App\Support\BookingCalendar;
use App\Support\DepartureTime;
use App\Support\PaymentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Reserva el mismo día + varios horarios + pago 100% si sale en < 48 h +
 * PayPal oculto por interruptor (2026-11-24). Todos los instantes en hora de
 * Lima (el servidor corre en UTC).
 */
class SameDayBookingTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALE = 'es';

    /** Lunes 7-dic-2026. */
    private const DAY = '2026-12-07';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'booking.same_day_min_hours' => 4,
            'booking.next_day_cutoff' => '19:00',
            'booking.min_days_ahead' => 1,
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

    private function at(string $hhmm, string $day = self::DAY): void
    {
        Carbon::setTestNow(Carbon::parse("{$day} {$hhmm}:00", 'America/Lima'));
    }

    private function cityTour(array $overrides = []): Tour
    {
        return Tour::factory()->create(array_merge([
            'allows_same_day' => true,
            'departure_time' => '09:00',
            'departure_times' => ['14:30', '09:00', '09:00'],
            'price' => 50,
        ], $overrides));
    }

    private function plainTour(array $overrides = []): Tour
    {
        return Tour::factory()->create(array_merge(['departure_time' => '09:00', 'price' => 50], $overrides));
    }

    // ── Regla de calendario ────────────────────────────────────────────

    public function test_departure_times_are_normalized_sorted_and_deduplicated(): void
    {
        $tour = $this->cityTour();

        $this->assertSame(['09:00', '14:30'], $tour->fresh()->departure_times);
        $this->assertSame(['08:00', '14:30'], DepartureTime::normalizeList(['2:30 PM', '08:00', 'basura', '14:30']));
        $this->assertSame([], DepartureTime::normalizeList(null));
        $this->assertNull(Tour::factory()->create(['departure_times' => ['x']])->fresh()->departure_times);
    }

    public function test_tour_without_the_toggle_keeps_the_never_today_rule(): void
    {
        $tour = $this->plainTour();

        $this->at('06:00');
        $this->assertSame('2026-12-08', BookingCalendar::earliestDate($tour));
        $this->assertFalse(BookingCalendar::isBookable(self::DAY, $tour));
        $this->assertSame([], BookingCalendar::todaySlots($tour));

        // Corte de las 19:00: pasado mañana.
        $this->at('19:00');
        $this->assertSame('2026-12-09', BookingCalendar::earliestDate($tour));
        $this->assertFalse(BookingCalendar::isBookable('2026-12-08', $tour));
        $this->assertTrue(BookingCalendar::isBookable('2026-12-09', $tour));
    }

    public function test_same_day_tour_allows_today_only_for_slots_with_enough_margin(): void
    {
        $tour = $this->cityTour();

        $this->at('09:00');
        $this->assertSame(['14:30'], BookingCalendar::todaySlots($tour));
        $this->assertSame(self::DAY, BookingCalendar::earliestDate($tour));
        $this->assertTrue(BookingCalendar::isBookable(self::DAY, $tour, '14:30'));
        $this->assertFalse(BookingCalendar::isBookable(self::DAY, $tour, '09:00'), '09:00 ya no tiene margen.');
        $this->assertFalse(BookingCalendar::isBookable(self::DAY, $tour, null), 'Con 2 horarios el slot es obligatorio.');
        // Mañana: ambos horarios.
        $this->assertTrue(BookingCalendar::isBookable('2026-12-08', $tour, '09:00'));
        $this->assertSame(['09:00', '14:30'], BookingCalendar::slotsForDate('2026-12-08', $tour));
        $this->assertSame(['14:30'], BookingCalendar::slotsForDate(self::DAY, $tour));
    }

    public function test_margin_boundary_is_inclusive_and_one_minute_later_is_rejected(): void
    {
        $tour = $this->cityTour();

        $this->at('10:30'); // faltan exactamente 4 h para las 14:30
        $this->assertSame(['14:30'], BookingCalendar::todaySlots($tour));

        $this->at('10:31');
        $this->assertSame([], BookingCalendar::todaySlots($tour));
        $this->assertSame('2026-12-08', BookingCalendar::earliestDate($tour));
        $this->assertFalse(BookingCalendar::isBookable(self::DAY, $tour, '14:30'));
    }

    public function test_after_the_cutoff_a_same_day_tour_follows_the_standard_rule(): void
    {
        $tour = $this->cityTour();

        $this->at('19:30');
        $this->assertSame([], BookingCalendar::todaySlots($tour));
        $this->assertSame('2026-12-09', BookingCalendar::earliestDate($tour));
    }

    public function test_per_tour_margin_overrides_the_global_one(): void
    {
        $tour = $this->cityTour(['same_day_min_hours' => 2]);

        $this->at('12:30');
        $this->assertSame(2, BookingCalendar::sameDayMinHours($tour));
        $this->assertSame(['14:30'], BookingCalendar::todaySlots($tour));
    }

    public function test_invalid_or_empty_global_margin_falls_back_to_four_hours(): void
    {
        foreach (['', '  ', 'abc', null, '-3', '99', 4.5] as $raw) {
            config(['booking.same_day_min_hours' => $raw]);
            $this->assertSame(4, BookingCalendar::sameDayMinHours(), 'Valor '.var_export($raw, true));
        }

        config(['booking.same_day_min_hours' => '6']);
        $this->assertSame(6, BookingCalendar::sameDayMinHours());
    }

    public function test_timezone_is_lima_even_if_the_env_timezone_is_empty_and_the_server_is_utc(): void
    {
        config(['booking.timezone' => '']);
        // 14:00 UTC = 09:00 Lima.
        Carbon::setTestNow(Carbon::parse(self::DAY.' 14:00:00', 'UTC'));

        $this->assertSame(self::DAY, BookingCalendar::today()->toDateString());
        $this->assertSame(['14:30'], BookingCalendar::todaySlots($this->cityTour()));
    }

    public function test_single_slot_tour_assigns_it_automatically_and_invalid_slots_are_rejected(): void
    {
        $tour = $this->plainTour(['allows_same_day' => true]);

        $this->assertSame('09:00', BookingCalendar::resolveSlot($tour, null));
        $this->assertTrue(BookingCalendar::slotIsValid($tour, null));
        $this->assertTrue(BookingCalendar::slotIsValid($tour, '09:00'));
        $this->assertFalse(BookingCalendar::slotIsValid($tour, '10:00'));
        $this->assertFalse(BookingCalendar::slotIsValid($tour, 'basura'));
        $this->assertFalse(BookingCalendar::slotIsValid($this->cityTour(), '10:00'));
    }

    // ── Carrito ────────────────────────────────────────────────────────

    private function addToCart(Tour $tour, array $overrides = [])
    {
        return $this->post(route('cart.store', ['locale' => self::LOCALE]), array_merge([
            'tour_id' => $tour->id,
            'adults' => 2,
            'children' => 0,
            'travel_date' => self::DAY,
        ], $overrides));
    }

    public function test_cart_accepts_today_with_a_valid_slot_and_stores_it(): void
    {
        $this->at('09:00');
        $tour = $this->cityTour();

        $this->addToCart($tour, ['departure_slot' => '14:30'])->assertSessionHasNoErrors();

        $items = app(CartService::class)->items();
        $this->assertCount(1, $items);
        $this->assertSame('14:30', $items->first()['departure_slot']);
        $this->assertSame(self::DAY, $items->first()['travel_date']);
    }

    public function test_cart_rejects_today_without_margin_wrong_slot_or_missing_slot(): void
    {
        $this->at('09:00');
        $tour = $this->cityTour();

        $this->addToCart($tour, ['departure_slot' => '09:00'])->assertSessionHasErrors('departure_slot');
        $this->addToCart($tour, ['departure_slot' => '10:00'])->assertSessionHasErrors('departure_slot');
        $this->addToCart($tour, [])->assertSessionHasErrors('departure_slot');
        $this->assertSame(0, app(CartService::class)->count());
    }

    public function test_cart_rejects_today_for_a_tour_without_the_toggle(): void
    {
        $this->at('09:00');

        $this->addToCart($this->plainTour())->assertSessionHasErrors('travel_date');
        $this->assertSame(0, app(CartService::class)->count());
    }

    public function test_single_slot_tour_needs_no_slot_in_the_request_and_gets_it_stored(): void
    {
        $this->at('09:00');
        $tour = $this->plainTour();

        $this->addToCart($tour, ['travel_date' => '2026-12-10'])->assertSessionHasNoErrors();
        $this->assertSame('09:00', app(CartService::class)->items()->first()['departure_slot']);
    }

    public function test_same_tour_and_date_with_two_slots_makes_two_cart_rows(): void
    {
        $this->at('09:00');
        $tour = $this->cityTour();

        $this->addToCart($tour, ['travel_date' => '2026-12-10', 'departure_slot' => '09:00']);
        $this->addToCart($tour, ['travel_date' => '2026-12-10', 'departure_slot' => '14:30']);

        $this->assertSame(2, app(CartService::class)->count());
    }

    public function test_cart_item_whose_slot_lost_its_margin_is_flagged_as_unbookable(): void
    {
        $this->at('09:00');
        $tour = $this->cityTour();
        $this->addToCart($tour, ['departure_slot' => '14:30'])->assertSessionHasNoErrors();

        $this->at('11:00'); // ya no faltan 4 h: hoy dejó de ser reservable para este tour
        $bad = BookingCalendar::unbookableItems(app(CartService::class)->items());

        $this->assertCount(1, $bad);
        $this->assertSame('2026-12-08', $bad[0]['first_date']);

        // Fecha futura válida pero horario que el tour no ofrece (carrito viejo/manipulado): mensaje de horario.
        $stale = collect([[
            'row_id' => 'x', 'tour_id' => $tour->id, 'title_snapshot' => 'City Tour',
            'travel_date' => '2026-12-12', 'departure_slot' => '10:00',
        ]]);
        $bad = BookingCalendar::unbookableItems($stale);
        $this->assertCount(1, $bad);
        $this->assertStringContainsString('horario', mb_strtolower($bad[0]['message']));

        // Tour con varios horarios y carrito sin horario: también se marca.
        $noSlot = collect([['row_id' => 'y', 'tour_id' => $tour->id, 'travel_date' => '2026-12-12']]);
        $this->assertCount(1, BookingCalendar::unbookableItems($noSlot));
    }

    public function test_tour_page_exposes_availability_computed_in_lima(): void
    {
        $this->at('09:00');
        $tour = $this->cityTour(['is_published' => true]);

        $html = $this->get(route('tours.show', ['locale' => self::LOCALE, 'slug' => $tour->slug]))->assertOk()->getContent();

        $this->assertStringContainsString('window.LVT_BOOKING', $html);
        $this->assertStringContainsString('window.LVT_MIN_DATE = "'.self::DAY.'"', $html);
        $this->assertStringContainsString('name="departure_slot"', $html);
        $this->assertStringContainsString('data-slot-picker', $html);
    }

    // ── Pago: 100% si sale en menos de 48 h ────────────────────────────

    private function fakeCulqiApproved(): void
    {
        Http::fake(['api.culqi.com/v2/charges' => Http::response([
            'object' => 'charge', 'id' => 'chr_sd_1', 'currency_code' => 'USD',
            'outcome' => ['type' => 'venta_exitosa', 'code' => 'AUT0000'],
        ], 201)]);
    }

    private function culqi(array $overrides = [])
    {
        return $this->postJson(route('checkout.culqi.charge', ['locale' => self::LOCALE]), array_merge([
            'culqi_token' => 'tkn_test_abc123',
            'customer_name' => 'Juan Pérez García',
            'customer_email' => 'juan@example.com',
            'customer_phone' => '+51987654321',
            'accept_terms' => '1',
        ], $overrides));
    }

    public function test_payment_plan_forces_full_when_departure_is_in_less_than_48_hours(): void
    {
        $tour = $this->plainTour();
        $item = fn (string $date) => collect([['tour_id' => $tour->id, 'travel_date' => $date, 'departure_slot' => '09:00']]);

        $this->at('10:00'); // salida mañana 09:00 = 23 h
        $this->assertTrue(PaymentPlan::mustPayInFull($item('2026-12-08')));
        $this->assertSame(['full'], PaymentPlan::allowedModes($item('2026-12-08')));

        // Pasado mañana 09:00 = 47 h: todavía < 48 h.
        $this->assertTrue(PaymentPlan::mustPayInFull($item('2026-12-09')));
        // 3 días: libre.
        $this->assertFalse(PaymentPlan::mustPayInFull($item('2026-12-10')));
        $this->assertSame(PaymentPlan::MODES, PaymentPlan::allowedModes($item('2026-12-10')));
    }

    public function test_server_rejects_deposit_when_the_tour_leaves_in_less_than_48_hours_and_accepts_full(): void
    {
        Mail::fake();
        $this->at('09:00');
        $tour = $this->cityTour();
        $this->addToCart($tour, ['departure_slot' => '14:30'])->assertSessionHasNoErrors();
        $this->fakeCulqiApproved();

        $this->culqi(['payment_mode' => 'deposit'])
            ->assertStatus(422)->assertJson(['code' => 'full_payment_required']);
        Http::assertNothingSent();
        $this->assertSame(0, Booking::count());

        $this->culqi(['payment_mode' => 'full'])->assertOk()->assertJson(['success' => true]);

        $b = Booking::firstOrFail();
        $this->assertSame('14:30', $b->departure_slot);
        $this->assertTrue($b->isSameDay());
        $this->assertSame('paid', $b->payment_status);
        $this->assertSame('full', $b->payment_plan);
        $this->assertSame('14:30', $b->departureTime());
        $this->assertSame('2026-12-07 14:30', $b->departureAt()->format('Y-m-d H:i'));
    }

    public function test_paypal_create_order_rejects_deposit_inside_the_48_hour_window(): void
    {
        $this->at('09:00');
        $tour = $this->cityTour();
        config(['services.paypal.client_id' => 'cid', 'services.paypal.secret' => 'sec']);
        $this->addToCart($tour, ['departure_slot' => '14:30']);
        Http::fake();

        $this->postJson(route('checkout.paypal.create', ['locale' => self::LOCALE]), [
            'accept_terms' => '1', 'payment_mode' => 'deposit',
        ])->assertStatus(422)->assertJson(['code' => 'full_payment_required']);

        Http::assertNothingSent();
    }

    public function test_later_route_refuses_to_reserve_without_paying_inside_the_window(): void
    {
        $this->at('09:00');
        $tour = $this->cityTour();
        $this->addToCart($tour, ['departure_slot' => '14:30']);

        $this->post(route('checkout.process', ['locale' => self::LOCALE]), [
            'customer_name' => 'Ana Torres', 'customer_email' => 'ana@example.com',
            'customer_phone' => '+51987654321', 'payment_mode' => 'later',
            'travel_date' => self::DAY, 'accept_terms' => true,
        ])->assertSessionHas('error', __('checkout_payment.full_only_required'));

        $this->assertSame(0, Booking::count());
    }

    public function test_checkout_page_hides_deposit_and_later_when_full_payment_is_forced(): void
    {
        $this->at('09:00');
        $tour = $this->cityTour();
        $this->addToCart($tour, ['departure_slot' => '14:30']);

        $html = $this->get(route('cart.index', ['locale' => self::LOCALE]))->assertOk()->getContent();
        $this->assertStringContainsString('data-full-only-notice', $html);
        $this->assertStringContainsString('value="full"', $html);
        $this->assertStringNotContainsString('value="deposit"', $html);
        $this->assertStringNotContainsString('value="later"', $html);

        $summary = $this->getJson(route('checkout.summary', ['locale' => self::LOCALE]))->assertOk()->json();
        $this->assertTrue($summary['full_only']);
        $this->assertSame(['full'], $summary['allowed_modes']);
        $this->assertSame('full', $summary['default_mode']);

        // Con salida lejana vuelven los tres modos.
        app(CartService::class)->clear();
        $this->addToCart($tour, ['travel_date' => '2026-12-20', 'departure_slot' => '09:00']);
        $html = $this->get(route('cart.index', ['locale' => self::LOCALE]))->getContent();
        $this->assertStringContainsString('value="deposit"', $html);
        $this->assertStringContainsString('value="later"', $html);
    }

    // ── Avisos al operador, admin y recordatorios ──────────────────────

    public function test_same_day_booking_marks_the_operator_email_and_the_row(): void
    {
        Mail::fake();
        $this->at('09:00');
        $tour = $this->cityTour();
        $this->addToCart($tour, ['departure_slot' => '14:30']);
        $this->fakeCulqiApproved();
        $this->culqi(['payment_mode' => 'full'])->assertOk();

        Mail::assertSent(BookingNotificationAdmin::class, function (BookingNotificationAdmin $m) {
            $html = $m->render();

            return str_starts_with($m->envelope()->subject, '[MISMO DÍA] [Nueva reserva]')
                && str_contains($html, 'RESERVA PARA EL MISMO DÍA')
                && str_contains($html, '2:30 p. m.');
        });
    }

    public function test_regular_booking_has_no_same_day_marker_and_keeps_the_normal_subject(): void
    {
        Mail::fake();
        $this->at('09:00');
        $tour = $this->plainTour();
        $this->addToCart($tour, ['travel_date' => '2026-12-12']);
        $this->fakeCulqiApproved();
        $this->culqi(['payment_mode' => 'full'])->assertOk();

        $b = Booking::firstOrFail();
        $this->assertFalse($b->isSameDay());
        Mail::assertSent(BookingNotificationAdmin::class, fn ($m) => str_starts_with($m->envelope()->subject, '[Nueva reserva]'));
    }

    public function test_balance_link_expiry_and_refund_rule_use_the_booking_slot(): void
    {
        $tour = $this->cityTour();
        $b = Booking::factory()->create([
            'tour_id' => $tour->id, 'travel_date' => '2026-12-20', 'departure_slot' => '14:30',
        ]);
        $noSlot = Booking::factory()->create(['tour_id' => $tour->id, 'travel_date' => '2026-12-20', 'departure_slot' => null]);

        $this->assertSame('14:30', $b->departureAt()->format('H:i'));
        $this->assertSame('14:30 2026-12-18', $b->balanceDeadline()->format('H:i Y-m-d')); // 20-dic 14:30 - 48 h
        $this->assertSame('09:00', $noSlot->departureAt()->format('H:i'));
    }

    // ── Comando de carga ───────────────────────────────────────────────

    public function test_command_activates_short_lima_tours_and_is_idempotent(): void
    {
        $lima = \App\Models\Region::create(['name_es' => 'Lima', 'slug' => 'lima']);
        $ica = \App\Models\Region::create(['name_es' => 'Ica', 'slug' => 'ica']);

        $city = Tour::factory()->create(['title_es' => 'City Tour Lima + Catacumbas', 'duration' => '4 horas', 'region_id' => $lima->id]);
        $full = Tour::factory()->create(['title_es' => 'Full Day Lima', 'duration' => 'Full Day', 'region_id' => $lima->id]);
        $six = Tour::factory()->create(['title_es' => 'Gastronomico', 'duration' => '6 horas', 'region_id' => $lima->id]);
        $nazca = Tour::factory()->create(['title_es' => 'Nazca', 'duration' => '4 horas', 'region_id' => $ica->id]);

        $this->artisan('lvt:same-day-tours')->assertSuccessful();

        $this->assertTrue($city->fresh()->allows_same_day);
        $this->assertSame(['09:00', '14:30'], $city->fresh()->departure_times);
        $this->assertTrue($six->fresh()->allows_same_day);
        $this->assertFalse($full->fresh()->allows_same_day);
        $this->assertFalse($nazca->fresh()->allows_same_day);
        $this->assertNull($six->fresh()->departure_times);

        $before = Tour::orderBy('id')->get()->map->only(['id', 'allows_same_day', 'departure_times', 'departure_time'])->all();
        $this->artisan('lvt:same-day-tours')->expectsOutputToContain('Nada que cambiar')->assertSuccessful();
        $this->assertSame($before, Tour::orderBy('id')->get()->map->only(['id', 'allows_same_day', 'departure_times', 'departure_time'])->all());
    }

    // ── PayPal oculto por interruptor ──────────────────────────────────

    private function addFarTour(): Tour
    {
        $this->at('09:00');
        $tour = $this->plainTour();
        $this->addToCart($tour, ['travel_date' => '2026-12-20']);

        return $tour;
    }

    public function test_paypal_off_hides_it_in_checkout_and_the_server_rejects_new_orders(): void
    {
        Setting::set('paypal_visible', '0', 'boolean');
        $this->addFarTour();
        Http::fake();

        $html = $this->get(route('cart.index', ['locale' => self::LOCALE]))->assertOk()->getContent();
        $this->assertStringNotContainsString('value="paypal"', $html);
        $this->assertStringNotContainsString('paypal.com/sdk', $html);
        $this->assertStringNotContainsString('co-paypal-word', $html);
        $this->assertStringContainsString('value="culqi"', $html);

        $this->postJson(route('checkout.paypal.create', ['locale' => self::LOCALE]), [
            'accept_terms' => '1', 'payment_mode' => 'full',
        ])->assertStatus(403)->assertJson(['code' => 'paypal_disabled']);
        Http::assertNothingSent();

        $this->getJson(route('checkout.summary', ['locale' => self::LOCALE]))->assertJsonPath('methods.paypal', false);
    }

    public function test_paypal_on_keeps_working_like_before(): void
    {
        Setting::set('paypal_visible', '1', 'boolean');
        $this->addFarTour();
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake'], 200),
            '*/v2/checkout/orders' => Http::response(['id' => 'ORDER-ON'], 201),
        ]);
        config(['services.paypal.client_id' => 'cid', 'services.paypal.secret' => 'sec']);

        $html = $this->get(route('cart.index', ['locale' => self::LOCALE]))->getContent();
        $this->assertStringContainsString('value="paypal"', $html);

        $this->postJson(route('checkout.paypal.create', ['locale' => self::LOCALE]), [
            'accept_terms' => '1', 'payment_mode' => 'full',
        ])->assertOk()->assertJson(['id' => 'ORDER-ON']);
    }

    public function test_paypal_is_off_by_default_when_the_admin_never_saved_the_toggle(): void
    {
        config(['services.paypal.visible_default' => false]);

        $this->assertFalse(\App\Services\PayPalService::availableForCustomers());
    }

    public function test_payment_link_page_hides_paypal_and_rejects_new_paypal_orders_for_balance_links(): void
    {
        Mail::fake();
        Setting::set('paypal_visible', '0', 'boolean');
        $tour = $this->plainTour();
        $booking = Booking::factory()->create([
            'tour_id' => $tour->id, 'travel_date' => '2026-12-30', 'status' => 'confirmed',
            'payment_status' => 'partial', 'payment_plan' => 'deposit', 'payment_method' => 'culqi',
            'total_price' => 200, 'amount_paid' => 100, 'balance_due' => 100,
            'customer_email' => 'ana@example.com', 'locale' => 'es',
        ]);
        $this->at('09:00');
        $link = app(\App\Services\BalancePaymentService::class)->createLink($booking, null, true);
        Http::fake();

        $html = $this->get(route('payment-links.show', ['code' => $link->code]))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="paypal-buttons"', $html);
        $this->assertStringNotContainsString('paypal.com/sdk', $html);
        $this->assertStringContainsString('pl-culqi-btn', $html);

        $this->postJson(route('payment-links.paypal.create', ['code' => $link->code]))
            ->assertStatus(403)->assertJson(['code' => 'paypal_disabled']);
        Http::assertNothingSent();
    }

    public function test_without_culqi_and_without_paypal_the_checkout_shows_a_whatsapp_message_and_logs_a_warning(): void
    {
        Setting::set('paypal_visible', '0', 'boolean');
        config(['services.culqi.enabled' => false]);
        $this->at('09:00');
        $tour = $this->cityTour();
        $this->addToCart($tour, ['departure_slot' => '14:30']); // forzado 100%

        Log::spy();
        $html = $this->get(route('cart.index', ['locale' => self::LOCALE]))->assertOk()->getContent();

        $this->assertStringContainsString('data-no-gateway', $html);
        $this->assertStringContainsString(__('checkout_payment.no_method_available'), html_entity_decode($html));
        $this->assertStringContainsString('wa.me/', $html);
        Log::shouldHaveReceived('warning')->withArgs(fn ($m) => $m === 'checkout.no_payment_method_available')->atLeast()->once();
    }
}
