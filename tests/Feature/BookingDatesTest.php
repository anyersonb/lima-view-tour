<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Tour;
use App\Services\CartService;
use App\Support\BookingCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        ], $overrides);
    }

    // ── C · zona horaria ───────────────────────────────────────────────

    /** @test */
    public function the_booking_calendar_uses_lima_time_and_not_the_server_clock(): void
    {
        // 21:00 en Lima del 26 = 02:00 UTC del 27. Para el operador todavía es
        // el 26, así que el primer día reservable es el 27 — que es justo el
        // que la regla vieja (`after:today` sobre UTC) rechazaba.
        Carbon::setTestNow(Carbon::parse('2026-08-27 02:00:00', 'UTC'));

        $this->assertSame('2026-08-26', BookingCalendar::today()->toDateString());
        $this->assertSame('2026-08-27', BookingCalendar::earliestDate());
        $this->assertTrue(BookingCalendar::isBookable('2026-08-27'));
        $this->assertFalse(BookingCalendar::isBookable('2026-08-26'));

        Carbon::setTestNow();
    }

    /** @test */
    public function booking_for_tomorrow_is_accepted_in_the_evening_lima_time(): void
    {
        // La ventana rota: 21:00 en Lima, el servidor ya en el día siguiente.
        Carbon::setTestNow(Carbon::parse('2026-08-27 02:00:00', 'UTC'));

        Mail::fake();

        $tour = $this->tour();
        $tomorrow = '2026-08-27';

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
        config(['booking.min_days_ahead' => '']);

        $this->assertSame(
            BookingCalendar::today()->addDay()->toDateString(),
            BookingCalendar::earliestDate()
        );

        config(['booking.min_days_ahead' => 1]);
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
}
