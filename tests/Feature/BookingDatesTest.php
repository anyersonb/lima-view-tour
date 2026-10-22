<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Tour;
use App\Services\CartService;
use App\Support\BookingCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Lote de fechas de reserva (2026-08-27). Tres fallos que se tapaban entre sí:
 *
 *  A. La fecha no se podía cambiar en el carrito (los dos campos `readonly`) y
 *     venía pre-rellenada con hoy+7 desde la ficha. Un cliente que no abría el
 *     calendario quedaba atrapado en una fecha que no eligió.
 *  B. finalizeBookings() grababa la fecha del formulario en TODAS las reservas,
 *     así que dos tours en días distintos se guardaban ambos en el del primero.
 *  C. La app corre en UTC y el operador está en Lima (UTC-5): entre las 19:00 y
 *     medianoche de Lima el servidor ya estaba en el día siguiente y el
 *     checkout rechazaba justo la fecha de mañana.
 */
class BookingDatesTest extends TestCase
{
    use RefreshDatabase;

    private function cart(): CartService
    {
        return app(CartService::class);
    }

    private function tour(array $overrides = []): Tour
    {
        return Tour::factory()->create(array_merge(['is_published' => true], $overrides));
    }

    private function customerPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Ana Torres',
            'customer_email' => 'ana@example.com',
            'customer_phone' => '+51987654321',
            'payment_timing' => 'later',
            'travel_date' => BookingCalendar::earliestDate(),
            // B4 (lote 2026-09-30): ProcessPaymentRequest ahora exige
            // accept_terms marcado.
            'accept_terms' => true,
        ], $overrides);
    }

    // ── C · zona horaria ───────────────────────────────────────────────

    /** @test */
    public function the_booking_calendar_uses_lima_time_and_not_the_server_clock(): void
    {
        // 21:00 en Lima del 26 = 02:00 UTC del 27. Para el operador todavía es
        // el 26 (en UTC "hoy" ya sería el 27) y ya pasó el corte de las 19:00:
        // la primera fecha es pasado mañana (28).
        Carbon::setTestNow(Carbon::parse('2026-08-27 02:00:00', 'UTC'));

        $this->assertSame('2026-08-26', BookingCalendar::today()->toDateString());
        $this->assertSame('2026-08-28', BookingCalendar::earliestDate());
        $this->assertTrue(BookingCalendar::isBookable('2026-08-28'));
        $this->assertFalse(BookingCalendar::isBookable('2026-08-27'));

        Carbon::setTestNow();
    }

    /** @test */
    public function booking_for_the_first_date_is_accepted_in_the_evening_lima_time(): void
    {
        // La ventana rota: 21:00 en Lima, el servidor ya en el día siguiente.
        Carbon::setTestNow(Carbon::parse('2026-08-27 02:00:00', 'UTC'));

        Mail::fake();

        // Pasado el corte de las 19:00 la primera fecha es el 28 (pasado
        // mañana de Lima). La regla vieja (`after:today` sobre UTC) lo comparaba
        // contra el 27 y el checkout rechazaba la fecha válida.
        $tour = $this->tour(['departure_time' => '23:00']);
        $this->assertFalse(BookingCalendar::isBookable('2026-08-27', $tour));
        $tomorrow = '2026-08-28';

        $this->cart()->add($tour, 2, 0, $tomorrow);

        $response = $this->post('/es/checkout/procesar', $this->customerPayload([
            'travel_date' => $tomorrow,
        ]));

        // Antes: "La fecha de viaje debe ser posterior a hoy" y vuelta al carrito.
        $response->assertSessionHasNoErrors();
        $this->assertSame($tomorrow, Booking::where('tour_id', $tour->id)->value('travel_date')?->toDateString());

        Carbon::setTestNow();
    }

    // ── B · una fecha por tour ─────────────────────────────────────────

    /** @test */
    public function each_booking_keeps_its_own_tour_date(): void
    {
        Mail::fake();

        $cusco = $this->tour(['title_es' => 'Cusco']);
        $lima = $this->tour(['title_es' => 'Lima']);

        $primera = now()->addDays(3)->toDateString();
        $segunda = now()->addDays(9)->toDateString();

        $this->cart()->add($cusco, 2, 0, $primera);
        $this->cart()->add($lima, 1, 0, $segunda);

        $this->post('/es/checkout/procesar', $this->customerPayload([
            'travel_date' => $primera,
        ]))->assertSessionHasNoErrors();

        // El fallo: las dos reservas salían con $primera.
        $this->assertSame($primera, Booking::where('tour_id', $cusco->id)->value('travel_date')?->toDateString());
        $this->assertSame($segunda, Booking::where('tour_id', $lima->id)->value('travel_date')?->toDateString());

        $this->assertSame(2, Booking::count());
        $this->assertSame(
            2,
            Booking::query()->distinct()->count('travel_date'),
            'Las dos reservas quedaron con la misma fecha.'
        );
    }

    // ── A · la fecha se puede cambiar en el carrito ────────────────────

    /** @test */
    public function the_date_of_a_cart_item_can_be_changed_and_the_row_is_rekeyed(): void
    {
        $tour = $this->tour();
        $desde = now()->addDays(4)->toDateString();
        $hasta = now()->addDays(11)->toDateString();

        $this->cart()->add($tour, 2, 1, $desde);
        $rowId = $this->cart()->items()->first()['row_id'];

        $response = $this->patchJson("/es/carrito/{$rowId}", [
            'adults' => 2,
            'children' => 1,
            'travel_date' => $hasta,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $items = $this->cart()->items();

        $this->assertCount(1, $items, 'El cambio de fecha duplicó la fila en vez de moverla.');
        $this->assertSame($hasta, $items->first()['travel_date']);
        $this->assertNotSame($rowId, $items->first()['row_id'], 'El rowId debe seguir a la fecha.');
        $this->assertSame($response->json('row_id'), $items->first()['row_id']);
    }

    /** @test */
    public function moving_a_tour_onto_a_date_it_already_occupies_merges_the_two_rows(): void
    {
        $tour = $this->tour();
        $a = now()->addDays(4)->toDateString();
        $b = now()->addDays(5)->toDateString();

        $this->cart()->add($tour, 2, 0, $a);
        $this->cart()->add($tour, 1, 0, $b);
        $this->assertCount(2, $this->cart()->items());

        $rowB = $this->cart()->items()->firstWhere('travel_date', $b)['row_id'];

        $this->patchJson("/es/carrito/{$rowB}", [
            'adults' => 1,
            'children' => 0,
            'travel_date' => $a,
        ])->assertOk();

        $items = $this->cart()->items();

        // Dos tarjetas del mismo tour en el mismo día no tienen sentido y, peor,
        // comparten rowId: la segunda pisaría a la primera en la sesión.
        $this->assertCount(1, $items);
        $this->assertSame(3, $items->first()['adults']);
    }

    /** @test */
    public function a_date_before_the_first_bookable_day_is_rejected_from_the_cart(): void
    {
        $tour = $this->tour();
        $this->cart()->add($tour, 1, 0, now()->addDays(4)->toDateString());
        $rowId = $this->cart()->items()->first()['row_id'];

        $this->patchJson("/es/carrito/{$rowId}", [
            'adults' => 1,
            'children' => 0,
            'travel_date' => BookingCalendar::today()->subDay()->toDateString(),
        ])->assertStatus(422);
    }

    // ── D · horizonte máximo de reserva ────────────────────────────────

    /** @test */
    public function a_date_beyond_the_maximum_horizon_is_rejected_from_the_cart(): void
    {
        $tour = $this->tour();
        $this->cart()->add($tour, 1, 0, now()->addDays(4)->toDateString());
        $rowId = $this->cart()->items()->first()['row_id'];

        // Tope configurado: booking.max_months_ahead (18 meses por defecto).
        $farAway = BookingCalendar::today()->addMonths(19)->toDateString();

        $this->patchJson("/es/carrito/{$rowId}", [
            'adults' => 1,
            'children' => 0,
            'travel_date' => $farAway,
        ])->assertStatus(422);

        // Sin tope, 9999-12-31 pasaba la validación igual que una fecha razonable.
        $this->assertFalse(BookingCalendar::isBookable('9999-12-31'));
        $this->assertFalse(BookingCalendar::isBookable($farAway));
    }

    /** @test */
    public function a_date_within_the_maximum_horizon_is_still_accepted(): void
    {
        $tour = $this->tour();
        $this->cart()->add($tour, 1, 0, now()->addDays(4)->toDateString());
        $rowId = $this->cart()->items()->first()['row_id'];

        $withinHorizon = BookingCalendar::today()->addMonths(6)->toDateString();

        $this->patchJson("/es/carrito/{$rowId}", [
            'adults' => 1,
            'children' => 0,
            'travel_date' => $withinHorizon,
        ])->assertOk();
    }

    // ── E · config de calendario a prueba de valores vacíos ────────────

    /** @test */
    public function an_empty_booking_timezone_falls_back_to_lima_instead_of_crashing(): void
    {
        // `env('BOOKING_TIMEZONE', 'America/Lima')` solo cae al default cuando
        // la CLAVE falta, no cuando el .env la define vacía. Carbon::now('')
        // lanza InvalidFormatException.
        config(['booking.timezone' => '']);

        $this->assertSame('America/Lima', BookingCalendar::timezone());
        $this->assertInstanceOf(Carbon::class, BookingCalendar::today());

        config(['booking.timezone' => 'America/Lima']);
    }

    /** @test */
    public function an_invalid_booking_timezone_falls_back_to_lima(): void
    {
        config(['booking.timezone' => 'Not/AZone']);

        $this->assertSame('America/Lima', BookingCalendar::timezone());

        config(['booking.timezone' => 'America/Lima']);
    }

    /** @test */
    public function the_cart_page_does_not_crash_when_booking_timezone_is_empty(): void
    {
        // earliestDate() se llama desde la Blade del carrito: antes, un
        // BOOKING_TIMEZONE='' escapaba del try de CartController@index y
        // reventaba la página entera.
        config(['booking.timezone' => '']);

        $this->get('/es/carrito')->assertOk();

        config(['booking.timezone' => 'America/Lima']);
    }

    /** @test */
    public function an_empty_min_days_ahead_still_requires_at_least_one_day(): void
    {
        // (int) '' === 0: un BOOKING_MIN_DAYS_AHEAD='' habilitaba reservas
        // para el mismo día sin que nadie lo decidiera a propósito.
        Carbon::setTestNow(Carbon::parse('2026-10-11 10:00:00', 'America/Lima'));
        config(['booking.min_days_ahead' => '']);

        $this->assertSame('2026-10-12', BookingCalendar::earliestDate());

        config(['booking.min_days_ahead' => 1]);
        Carbon::setTestNow();
    }

    // ── F · la fecha del ítem se revalida al finalizar la reserva ──────

    /** @test */
    public function a_stale_cart_item_date_is_rejected_at_checkout_even_if_the_form_date_is_valid(): void
    {
        Mail::fake();

        $tour = $this->tour();
        $travelDate = now()->addDays(2)->toDateString();
        $this->cart()->add($tour, 2, 0, $travelDate);

        // El carrito quedó abierto 10 días: la fecha del ítem ya pasó, aunque
        // el campo `travel_date` que viaja en el formulario sea válido hoy.
        Carbon::setTestNow(now()->addDays(10));

        $response = $this->post('/es/checkout/procesar', $this->customerPayload([
            'travel_date' => BookingCalendar::earliestDate(),
        ]));

        $response->assertRedirect(route('cart.index', ['locale' => 'es']));
        $this->assertSame(0, Booking::count(), 'Se grabó una reserva con la fecha de un ítem ya vencida.');
        $this->assertCount(1, $this->cart()->items(), 'El carrito se vació aunque el checkout fue rechazado.');

        Carbon::setTestNow();
    }

    /** @test */
    public function a_fresh_cart_item_date_is_accepted_at_checkout(): void
    {
        Mail::fake();

        $tour = $this->tour();
        $travelDate = now()->addDays(3)->toDateString();
        $this->cart()->add($tour, 2, 0, $travelDate);

        $this->post('/es/checkout/procesar', $this->customerPayload([
            'travel_date' => $travelDate,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Booking::count());
        $this->assertSame($travelDate, Booking::first()->travel_date?->toDateString());
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Corte diario de las 19:00 (2026-10-21)
    //
    //  Reemplaza la regla de 24 h antes de la salida. Hora de Lima:
    //  antes de las 19:00 la primera fecha es MAÑANA; desde las 19:00 es
    //  PASADO MAÑANA; hoy nunca. No depende de la hora de salida del tour.
    //  Los tests fijan el reloj en hora de Lima (servidor en UTC) y comparan
    //  fechas como strings Y-m-d, nunca con casts de columna.
    //  Fecha base: domingo 2026-10-11 (mañana 12, pasado mañana 13).
    // ═══════════════════════════════════════════════════════════════════

    private function limaNow(string $dateTime): void
    {
        Carbon::setTestNow(Carbon::parse($dateTime, 'America/Lima'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function addToCartPayload(Tour $t, string $date): array
    {
        return ['tour_id' => $t->id, 'adults' => 1, 'children' => 0, 'travel_date' => $date];
    }

    /** @test */
    public function at_18_59_lima_tomorrow_is_bookable(): void
    {
        $this->limaNow('2026-10-11 18:59:00');

        $this->assertSame('2026-10-12', BookingCalendar::earliestDate());
        $this->assertTrue(BookingCalendar::isBookable('2026-10-12'));
        $this->assertFalse(BookingCalendar::isBookable('2026-10-11'), 'hoy nunca');
    }

    /** @test */
    public function at_19_00_lima_tomorrow_is_rejected_and_the_day_after_is_bookable(): void
    {
        $this->limaNow('2026-10-11 19:00:00');

        $this->assertSame('2026-10-13', BookingCalendar::earliestDate());
        $this->assertFalse(BookingCalendar::isBookable('2026-10-12'));
        $this->assertTrue(BookingCalendar::isBookable('2026-10-13'));

        // Borde: un segundo antes sigue siendo "antes del corte".
        $this->limaNow('2026-10-11 18:59:59');
        $this->assertSame('2026-10-12', BookingCalendar::earliestDate());
    }

    /** @test */
    public function the_cutoff_is_evaluated_on_the_lima_clock_even_when_the_server_clock_is_utc(): void
    {
        // 19:30 Lima del 11 = 00:30 UTC del 12: el servidor ya está en otro día.
        Carbon::setTestNow(Carbon::parse('2026-10-12 00:30:00', 'UTC'));
        $this->assertSame('2026-10-11', BookingCalendar::today()->toDateString());
        $this->assertSame('2026-10-13', BookingCalendar::earliestDate());

        // 18:30 Lima del 12 = 23:30 UTC del 12: sigue antes del corte (mañana = 13).
        Carbon::setTestNow(Carbon::parse('2026-10-12 23:30:00', 'UTC'));
        $this->assertSame('2026-10-13', BookingCalendar::earliestDate());

        // 19:00 Lima del 12 = 00:00 UTC del 13: pasado mañana = 14.
        Carbon::setTestNow(Carbon::parse('2026-10-13 00:00:00', 'UTC'));
        $this->assertSame('2026-10-14', BookingCalendar::earliestDate());
    }

    /** @test */
    public function after_midnight_lima_tomorrow_is_bookable_again_and_today_is_not(): void
    {
        $this->limaNow('2026-10-11 23:59:00');
        $this->assertSame('2026-10-13', BookingCalendar::earliestDate());

        $this->limaNow('2026-10-12 00:30:00');
        $this->assertSame('2026-10-13', BookingCalendar::earliestDate(), 'mañana del nuevo día (el 13)');
        $this->assertTrue(BookingCalendar::isBookable('2026-10-13'));
        $this->assertFalse(BookingCalendar::isBookable('2026-10-12'), 'hoy nunca');

        $this->limaNow('2026-10-11 00:30:00');
        $this->assertSame('2026-10-12', BookingCalendar::earliestDate());
        $this->assertFalse(BookingCalendar::isBookable('2026-10-11'));
    }

    /** @test */
    public function the_departure_time_of_the_tour_no_longer_matters(): void
    {
        $early = $this->tour(['departure_time' => '06:00']);
        $late = $this->tour(['departure_time' => '21:00']);

        // Antes (24 h) un tour de las 06:00 ya no se podía reservar para mañana a las 18:00.
        $this->limaNow('2026-10-11 18:00:00');
        $this->assertSame('2026-10-12', BookingCalendar::earliestDate($early));
        $this->assertSame('2026-10-12', BookingCalendar::earliestDate($late));
        $this->assertTrue(BookingCalendar::isBookable('2026-10-12', $early));

        $this->limaNow('2026-10-11 19:00:00');
        $this->assertSame('2026-10-13', BookingCalendar::earliestDate($early));
        $this->assertSame('2026-10-13', BookingCalendar::earliestDate($late));
    }

    /** @test */
    public function a_06_00_tour_can_be_added_to_the_cart_for_tomorrow_at_18_00(): void
    {
        $tour = $this->tour(['departure_time' => '06:00']);
        $this->limaNow('2026-10-11 18:00:00');

        $this->postJson('/es/carrito/agregar', $this->addToCartPayload($tour, '2026-10-12'))->assertOk();
    }

    /** @test */
    public function adding_to_the_cart_after_the_cutoff_rejects_tomorrow_with_the_cutoff_message(): void
    {
        $tour = $this->tour(['departure_time' => '06:00']);
        $this->limaNow('2026-10-11 19:00:00');

        $r = $this->postJson('/es/carrito/agregar', $this->addToCartPayload($tour, '2026-10-12'));
        $r->assertStatus(422);
        $message = $r->json('errors.travel_date.0');
        $this->assertStringContainsString('13 de octubre', $message);
        $this->assertStringContainsString('7:00 p. m.', $message);
        $this->assertStringContainsString('hora de Lima', $message);
        $this->assertStringNotContainsString('1 día de anticipación', $message);
        $this->assertCount(0, $this->cart()->items());

        $this->postJson('/es/carrito/agregar', $this->addToCartPayload($tour, '2026-10-13'))->assertOk();
    }

    /** @test */
    public function the_validation_messages_name_the_cutoff_in_the_three_languages(): void
    {
        $this->limaNow('2026-10-11 19:30:00');

        $expected = ['es' => '7:00 p. m.', 'en' => '7:00 PM', 'pt' => '19:00'];

        foreach ($expected as $locale => $label) {
            app()->setLocale($locale);

            $msg = BookingCalendar::dateMessages()['travel_date.after_or_equal'];
            $this->assertStringContainsString($label, $msg, "cart.date_future {$locale}");
            $this->assertStringNotContainsString(':cutoff', $msg);
            $this->assertStringNotContainsString(':date', $msg);

            $item = BookingCalendar::itemMessage('Tour X');
            $this->assertStringContainsString($label, $item, "booking.date_item_unavailable {$locale}");
            $this->assertStringNotContainsString(':cutoff', $item);
        }

        app()->setLocale('es');
    }

    /** @test */
    public function an_invalid_or_empty_cutoff_config_falls_back_to_19_00(): void
    {
        $this->limaNow('2026-10-11 18:59:00');

        foreach (['', 'abc', '25:00', '7pm', '19:60', null, '24:00'] as $bad) {
            config(['booking.next_day_cutoff' => $bad]);
            $this->assertSame('19:00', BookingCalendar::nextDayCutoff(), 'next_day_cutoff='.var_export($bad, true));
            $this->assertSame('2026-10-12', BookingCalendar::earliestDate());
        }

        // Con la clave AUSENTE (config cacheada vieja en producción) también cae a 19:00.
        config(['booking.next_day_cutoff' => null]);
        $this->limaNow('2026-10-11 19:00:00');
        $this->assertSame('2026-10-13', BookingCalendar::earliestDate());
    }

    /** @test */
    public function a_valid_custom_cutoff_is_respected(): void
    {
        config(['booking.next_day_cutoff' => '20:30']);

        $this->limaNow('2026-10-11 20:29:00');
        $this->assertSame('2026-10-12', BookingCalendar::earliestDate());

        $this->limaNow('2026-10-11 20:30:00');
        $this->assertSame('2026-10-13', BookingCalendar::earliestDate());

        config(['booking.next_day_cutoff' => '7:05']);
        $this->assertSame('07:05', BookingCalendar::nextDayCutoff());
    }

    /** @test */
    public function min_days_ahead_still_acts_as_a_floor(): void
    {
        config(['booking.min_days_ahead' => 3]);

        $this->limaNow('2026-10-11 10:00:00');
        $this->assertSame('2026-10-14', BookingCalendar::earliestDate());

        $this->limaNow('2026-10-11 19:30:00');
        $this->assertSame('2026-10-14', BookingCalendar::earliestDate());
    }

    /** @test */
    public function a_tour_without_departure_time_uses_the_06_00_default_for_display_only(): void
    {
        $tour = $this->tour(['departure_time' => null]);

        $this->assertSame('06:00', BookingCalendar::departureTime($tour));
        $this->assertSame('06:00', BookingCalendar::departureTime(null));

        // La hora de salida ya no mueve la primera fecha.
        $this->limaNow('2026-10-11 05:59:00');
        $this->assertSame('2026-10-12', BookingCalendar::earliestDate($tour));
        $this->limaNow('2026-10-11 06:01:00');
        $this->assertSame('2026-10-12', BookingCalendar::earliestDate($tour));
    }

    /** @test */
    public function an_unreadable_departure_time_is_stored_as_null_and_falls_back_to_the_default(): void
    {
        $tour = $this->tour(['departure_time' => 'Todo el día']);

        $this->assertNull($tour->fresh()->departure_time);
        $this->assertSame('06:00', BookingCalendar::departureTime($tour->fresh()));
    }

    /** @test */
    public function the_departure_time_is_stored_normalized_to_24_hours(): void
    {
        $this->assertSame('17:30', $this->tour(['departure_time' => '5:30 pm'])->fresh()->departure_time);
    }

    /** @test */
    public function empty_or_invalid_default_departure_config_never_breaks_and_falls_back(): void
    {
        $this->limaNow('2026-10-11 05:59:00');

        foreach (['', 'abc', '-3', null, '06:75'] as $bad) {
            config(['booking.default_departure_time' => $bad]);
            $this->assertSame('06:00', BookingCalendar::defaultDepartureTime(), 'default_departure_time='.var_export($bad, true));
            $this->assertSame('2026-10-12', BookingCalendar::earliestDate());
        }

        config(['booking.default_departure_time' => '08:30']);
        $this->assertSame('08:30', BookingCalendar::defaultDepartureTime());
    }

    /** @test */
    public function the_pages_do_not_crash_with_empty_booking_config(): void
    {
        config(['booking.next_day_cutoff' => '', 'booking.default_departure_time' => '']);
        $tour = $this->tour();

        $this->get('/es/carrito')->assertOk();
        $this->get(route('tours.show', ['locale' => 'es', 'slug' => $tour->slug]))->assertOk();
    }

    /** @test */
    public function the_tour_page_gets_the_same_min_date_for_every_tour_and_follows_the_cutoff(): void
    {
        $early = $this->tour(['departure_time' => '05:00']);
        $late = $this->tour(['departure_time' => '14:00']);

        $this->limaNow('2026-10-11 10:00:00');

        foreach ([$early, $late] as $tour) {
            $r = $this->get(route('tours.show', ['locale' => 'es', 'slug' => $tour->slug]));
            $r->assertOk();
            $r->assertSee('window.LVT_MIN_DATE = "2026-10-12"', false);
            $this->assertSame(2, substr_count($r->getContent(), 'min="2026-10-12"'));
            $r->assertDontSee('min="2026-10-13"', false);
            $r->assertSee('7:00 p. m.', false);
        }

        $this->limaNow('2026-10-11 19:30:00');

        $r = $this->get(route('tours.show', ['locale' => 'es', 'slug' => $early->slug]));
        $r->assertSee('window.LVT_MIN_DATE = "2026-10-13"', false);
        $this->assertSame(2, substr_count($r->getContent(), 'min="2026-10-13"'));
        $r->assertDontSee('min="2026-10-12"', false);
    }

    /** @test */
    public function changing_a_date_in_the_cart_applies_the_cutoff(): void
    {
        $tour = $this->tour(['departure_time' => '05:00']);
        $this->limaNow('2026-10-11 19:30:00');
        $this->cart()->add($tour, 1, 0, '2026-10-20');
        $rowId = $this->cart()->items()->first()['row_id'];

        $this->patchJson("/es/carrito/{$rowId}", ['adults' => 1, 'children' => 0, 'travel_date' => '2026-10-12'])->assertStatus(422);
        $this->patchJson("/es/carrito/{$rowId}", ['adults' => 1, 'children' => 0, 'travel_date' => '2026-10-13'])->assertOk();

        // Antes del corte, mañana sí.
        $this->limaNow('2026-10-11 18:59:00');
        $rowId = $this->cart()->items()->first()['row_id'];
        $this->patchJson("/es/carrito/{$rowId}", ['adults' => 1, 'children' => 0, 'travel_date' => '2026-10-12'])->assertOk();
    }

    /** @test */
    public function checkout_rejects_an_item_saved_before_the_cutoff_and_names_the_offending_one(): void
    {
        Mail::fake();

        $stale = $this->tour(['title_es' => 'Tour Caduco']);
        $fine = $this->tour(['title_es' => 'Tour Vigente']);

        // 18:00: mañana (12) es válido para ambos.
        $this->limaNow('2026-10-11 18:00:00');
        $this->cart()->add($stale, 1, 0, '2026-10-12');
        $this->cart()->add($fine, 1, 0, '2026-10-14');

        // 19:05: el cliente tarda en pagar y el 12 ya no se reserva.
        $this->limaNow('2026-10-11 19:05:00');

        $response = $this->post('/es/checkout/procesar', $this->customerPayload(['travel_date' => '2026-10-12']));

        $response->assertRedirect(route('cart.index', ['locale' => 'es']));
        $error = session('error');
        $this->assertStringContainsString('Tour Caduco', $error);
        $this->assertStringNotContainsString('Tour Vigente', $error);
        $this->assertStringContainsString('13 de octubre', $error);
        $this->assertStringContainsString('7:00 p. m.', $error);
        $this->assertSame(0, Booking::count());
        $this->assertCount(2, $this->cart()->items(), 'El carrito se vació aunque el checkout fue rechazado.');

        // Corregida la fecha, pasa el carrito entero.
        $rowId = $this->cart()->items()->firstWhere('tour_id', $stale->id)['row_id'];
        $this->cart()->update($rowId, ['adults' => 1, 'children' => 0, 'travel_date' => '2026-10-13']);

        $this->post('/es/checkout/procesar', $this->customerPayload(['travel_date' => '2026-10-13']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('checkout.thanks', ['locale' => 'es']));
        $this->assertSame(2, Booking::count());
    }

    /** @test */
    public function the_cart_page_flags_an_expired_item_without_removing_it(): void
    {
        $tour = $this->tour(['title_es' => 'Tour Madrugada', 'departure_time' => '05:00']);
        $this->limaNow('2026-10-11 18:00:00');
        $this->cart()->add($tour, 1, 0, '2026-10-12');

        $this->limaNow('2026-10-11 19:10:00');
        $response = $this->get('/es/carrito');

        $response->assertOk();
        $response->assertSee('Tour Madrugada', false);
        $response->assertSee('La fecha de «Tour Madrugada» ya no está disponible', false);
        $response->assertSee('13 de octubre de 2026', false);
        $this->assertCount(1, $this->cart()->items());
        $this->assertSame('2026-10-13', $response->viewData('items')->first()['min_date']);
        $this->assertTrue($response->viewData('items')->first()['date_expired']);
    }

    /** @test */
    public function the_cart_page_does_not_flag_a_valid_item(): void
    {
        $tour = $this->tour(['title_es' => 'Tour Tarde', 'departure_time' => '14:00']);
        $this->limaNow('2026-10-11 18:00:00');
        $this->cart()->add($tour, 1, 0, '2026-10-12');

        $response = $this->get('/es/carrito');

        $response->assertOk();
        $response->assertDontSee('ya no está disponible', false);
        $this->assertFalse($response->viewData('items')->first()['date_expired']);
    }

    /** @test */
    public function paypal_create_order_rejects_a_stale_item_before_the_buyer_approves_anything(): void
    {
        Http::fake();

        $tour = $this->tour(['title_es' => 'Tour Madrugada', 'departure_time' => '05:00']);
        $this->limaNow('2026-10-11 18:00:00');
        $this->cart()->add($tour, 1, 0, '2026-10-12');

        $this->limaNow('2026-10-11 19:01:00');
        $response = $this->postJson(route('checkout.paypal.create', ['locale' => 'es']), ['accept_terms' => true]);

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'date_unavailable');
        $this->assertStringContainsString('Tour Madrugada', $response->json('error'));
        $this->assertSame('2026-10-13', $response->json('items.0.first_date'));
        Http::assertNothingSent();
    }

    /** @test */
    public function paypal_capture_rejects_an_item_that_expired_after_the_order_was_created_and_never_captures(): void
    {
        Mail::fake();

        $tour = $this->tour(['title_es' => 'Tour Madrugada', 'departure_time' => '05:00', 'price' => 150]);

        // 18:59: mañana (12) es reservable, se crea la orden.
        $this->limaNow('2026-10-11 18:59:00');
        $this->cart()->add($tour, 1, 0, '2026-10-12');

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*/v2/checkout/orders' => Http::response(['id' => 'ORDER-1'], 201),
        ]);
        $this->postJson(route('checkout.paypal.create', ['locale' => 'es']), ['accept_terms' => true])->assertOk();

        // 19:01: el cliente tarda en aprobar y se cruza el corte.
        $this->limaNow('2026-10-11 19:01:00');
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*/v2/checkout/orders/ORDER-1/capture' => Http::response(['status' => 'COMPLETED'], 200),
            '*/v2/checkout/orders/ORDER-1' => Http::response([
                'purchase_units' => [['amount' => ['currency_code' => 'USD', 'value' => '150.00']]],
            ], 200),
        ]);

        $response = $this->postJson(route('checkout.paypal.capture', ['locale' => 'es']), [
            'orderID' => 'ORDER-1',
            'customer_name' => 'Juan Pérez García',
            'customer_email' => 'juan@example.com',
            'customer_phone' => '+51987654321',
            'travel_date' => '2026-10-12',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Tour Madrugada', $response->json('message'));
        $this->assertSame(0, Booking::count());
        Http::assertNotSent(fn ($request) => str_contains((string) $request->url(), '/capture'));
    }

    // ── Links de pago: exentos del corte, no de una salida ya pasada ──

    private function paymentLink(Tour $tour, string $travelDate): \App\Models\PaymentLink
    {
        return \App\Models\PaymentLink::factory()->create([
            'tour_id' => $tour->id,
            'amount' => 150.00,
            'adults' => 1,
            'children' => 0,
            'travel_date' => $travelDate,
            'paypal_order_id' => 'ORDER-1',
        ]);
    }

    private function captureLink(\App\Models\PaymentLink $link): \Illuminate\Testing\TestResponse
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*/v2/checkout/orders/ORDER-1/capture' => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAPTURE-1']]]]],
            ], 200),
            '*/v2/checkout/orders/ORDER-1' => Http::response([
                'id' => 'ORDER-1',
                'purchase_units' => [['amount' => ['currency_code' => 'USD', 'value' => '150.00']]],
            ], 200),
        ]);

        return $this->post(route('payment-links.paypal.capture', ['code' => $link->code]), [
            'orderID' => 'ORDER-1',
            'customer_name' => 'Juan Pérez',
            'customer_email' => 'juan@example.com',
            'customer_phone' => '+51987654321',
        ]);
    }

    /** @test */
    public function a_payment_link_for_tomorrow_after_the_cutoff_is_still_charged(): void
    {
        Mail::fake();
        $tour = $this->tour(['departure_time' => '05:00']);

        // 19:30: la web ya no vende mañana (corte 19:00), el operador sí.
        $this->limaNow('2026-10-11 19:30:00');
        $this->assertFalse(BookingCalendar::isBookable('2026-10-12', $tour));

        $this->captureLink($this->paymentLink($tour, '2026-10-12'))->assertOk()->assertJson(['success' => true]);

        $this->assertSame(1, Booking::where('payment_method', 'payment_link')->count());
    }

    /** @test */
    public function a_payment_link_for_a_departure_that_already_left_is_rejected(): void
    {
        Mail::fake();
        $tour = $this->tour(['departure_time' => '05:00']);

        // Hoy a las 05:00 ya pasó (son las 10:00 en Lima).
        $this->limaNow('2026-10-11 10:00:00');

        $response = $this->captureLink($this->paymentLink($tour, '2026-10-11'));

        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertSame(0, Booking::where('payment_method', 'payment_link')->count());
        Http::assertNotSent(fn ($request) => str_contains((string) $request->url(), '/capture'));
    }

    /** @test */
    public function a_payment_link_for_a_departure_later_today_is_charged(): void
    {
        Mail::fake();
        $tour = $this->tour(['departure_time' => '14:00']);

        $this->limaNow('2026-10-11 10:00:00');

        $this->captureLink($this->paymentLink($tour, '2026-10-11'))->assertOk();
        $this->assertSame(1, Booking::where('payment_method', 'payment_link')->count());
    }

    // ── Hardening (informe de seguridad 2026-10-11) ────────────────────

    /** @test */
    public function a_cart_item_whose_tour_no_longer_exists_is_not_bookable(): void
    {
        $alive = $this->tour();
        $deleted = $this->tour();
        $date = BookingCalendar::earliestDate();
        $deleted->delete(); // soft delete

        $items = collect([
            ['row_id' => 'a', 'tour_id' => $alive->id, 'travel_date' => $date, 'title_snapshot' => 'Vivo'],
            ['row_id' => 'b', 'tour_id' => $deleted->id, 'travel_date' => $date, 'title_snapshot' => 'Borrado'],
            ['row_id' => 'c', 'tour_id' => 999999, 'travel_date' => $date, 'title_snapshot' => 'Inexistente'],
        ]);

        $bad = BookingCalendar::unbookableItems($items);

        $this->assertSame(['b', 'c'], array_column($bad, 'row_id'));
    }

    /** @test */
    public function is_bookable_only_accepts_strict_y_m_d_dates(): void
    {
        $tour = $this->tour();
        $ok = BookingCalendar::earliestDate($tour);

        $this->assertTrue(BookingCalendar::isBookable($ok, $tour));

        foreach ([$ok.'x', '+2 days', '@1792000000', $ok.' 10:00:00', '2026-02-30', 'tomorrow'] as $bad) {
            $this->assertFalse(BookingCalendar::isBookable($bad, $tour), "Should reject: {$bad}");
        }
    }

    /** @test */
    public function date_rules_require_the_y_m_d_format(): void
    {
        $this->assertContains('date_format:Y-m-d', BookingCalendar::dateRules());
        $this->assertContains('date_format:Y-m-d', BookingCalendar::formDateRules());

        $ok = BookingCalendar::earliestDate();
        $rules = ['d' => BookingCalendar::dateRules()];

        $this->assertFalse(validator(['d' => $ok], $rules)->fails());
        $this->assertTrue(validator(['d' => $ok.'x'], $rules)->fails());
        $this->assertTrue(validator(['d' => '+2 days'], $rules)->fails());
        $this->assertTrue(validator(['d' => '@1792000000'], ['d' => BookingCalendar::formDateRules()])->fails());
    }
}
