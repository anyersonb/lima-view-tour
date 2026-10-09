<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\BookingResource\Pages\ListBookings;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use App\Support\BookingCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ejercita los filtros REALES de la tabla de Filament (BookingResource) vía
 * Livewire::test()->filterTable(), no scopes de Eloquent en aislamiento.
 * Cada test crea registros que SÍ y que NO deben pasar el filtro, así un
 * filtro roto o ausente hace que el test falle (verificado manualmente
 * comentando cada ->filters([...]) durante el desarrollo).
 */
class BookingResourceFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'qa@webtilia.com']);
    }

    // ── Estado de pago ──────────────────────────────────────────────────────

    public function test_payment_status_filter_shows_only_selected_statuses(): void
    {
        $this->actingAs($this->admin());

        $paid = Booking::factory()->paid()->create();
        $pending = Booking::factory()->pendingPayment()->create();
        $failed = Booking::factory()->failedPayment()->create();

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->filterTable('payment_status', ['paid'])
            ->assertCanSeeTableRecords([$paid])
            ->assertCanNotSeeTableRecords([$pending, $failed]);

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->filterTable('payment_status', ['paid', 'failed'])
            ->assertCanSeeTableRecords([$paid, $failed])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    // ── Estado de la reserva ─────────────────────────────────────────────────

    public function test_status_filter_shows_only_selected_statuses(): void
    {
        $this->actingAs($this->admin());

        $confirmed = Booking::factory()->confirmed()->create();
        $pending = Booking::factory()->create(['status' => 'pending']);

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->filterTable('status', ['confirmed'])
            ->assertCanSeeTableRecords([$confirmed])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    // ── Rango de fecha de viaje ──────────────────────────────────────────────

    public function test_travel_date_range_filter(): void
    {
        $this->actingAs($this->admin());

        $inRange = Booking::factory()->create(['travel_date' => '2026-09-10']);
        $beforeRange = Booking::factory()->create(['travel_date' => '2026-08-01']);
        $afterRange = Booking::factory()->create(['travel_date' => '2026-10-01']);

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->filterTable('travel_date_range', [
                'travel_from' => '2026-09-01',
                'travel_until' => '2026-09-30',
            ])
            ->assertCanSeeTableRecords([$inRange])
            ->assertCanNotSeeTableRecords([$beforeRange, $afterRange]);
    }

    // ── Rango de fecha de reserva (created_at) ──────────────────────────────

    public function test_booked_date_range_filter(): void
    {
        $this->actingAs($this->admin());

        $inRange = Booking::factory()->create(['created_at' => Carbon::parse('2026-08-10 10:00:00')]);
        $before = Booking::factory()->create(['created_at' => Carbon::parse('2026-07-01 10:00:00')]);
        $after = Booking::factory()->create(['created_at' => Carbon::parse('2026-09-01 10:00:00')]);

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->filterTable('booked_date_range', [
                'booked_from' => '2026-08-01',
                'booked_until' => '2026-08-20',
            ])
            ->assertCanSeeTableRecords([$inRange])
            ->assertCanNotSeeTableRecords([$before, $after]);
    }

    // ── Categoría del tour (relación booking → tour → category) ────────────

    public function test_category_filter_resolves_through_tour_relationship(): void
    {
        $this->actingAs($this->admin());

        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();

        $tourA = Tour::factory()->create(['category_id' => $categoryA->id]);
        $tourB = Tour::factory()->create(['category_id' => $categoryB->id]);

        $bookingA = Booking::factory()->create(['tour_id' => $tourA->id]);
        $bookingB = Booking::factory()->create(['tour_id' => $tourB->id]);
        $customBooking = Booking::factory()->customTour()->create();

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->filterTable('category_id', [$categoryA->id])
            ->assertCanSeeTableRecords([$bookingA])
            ->assertCanNotSeeTableRecords([$bookingB, $customBooking]);
    }

    /**
     * Decisión documentada: una reserva de "tour personalizado" no tiene
     * tour_id ni, por lo tanto, categoría. El filtro de categoría NUNCA la
     * muestra (para eso está el toggle "Solo tours personalizados"). Este
     * test deja esa decisión explícita y a prueba de regresiones.
     */
    public function test_custom_tour_bookings_never_match_a_category_filter(): void
    {
        $this->actingAs($this->admin());

        $category = Category::factory()->create();
        $tour = Tour::factory()->create(['category_id' => $category->id]);

        $catalogBooking = Booking::factory()->create(['tour_id' => $tour->id]);
        $customBooking = Booking::factory()->customTour()->create();

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->filterTable('category_id', [$category->id])
            ->assertCanSeeTableRecords([$catalogBooking])
            ->assertCanNotSeeTableRecords([$customBooking]);
    }

    // ── Tour específico ──────────────────────────────────────────────────────

    public function test_specific_tour_filter(): void
    {
        $this->actingAs($this->admin());

        $tourX = Tour::factory()->create();
        $tourY = Tour::factory()->create();

        $bookingX = Booking::factory()->create(['tour_id' => $tourX->id]);
        $bookingY = Booking::factory()->create(['tour_id' => $tourY->id]);

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->filterTable('tour_id', [$tourX->id])
            ->assertCanSeeTableRecords([$bookingX])
            ->assertCanNotSeeTableRecords([$bookingY]);
    }

    // ── Monto total ──────────────────────────────────────────────────────────

    public function test_amount_range_filter(): void
    {
        $this->actingAs($this->admin());

        $low = Booking::factory()->create(['total_price' => 50]);
        $mid = Booking::factory()->create(['total_price' => 150]);
        $high = Booking::factory()->create(['total_price' => 300]);

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->filterTable('amount_range', ['amount_from' => 100, 'amount_until' => 200])
            ->assertCanSeeTableRecords([$mid])
            ->assertCanNotSeeTableRecords([$low, $high]);
    }

    // ── Pago en riesgo (plata a punto de perderse) ──────────────────────────

    public function test_payment_at_risk_filter(): void
    {
        $this->actingAs($this->admin());

        $atRisk = Booking::factory()->pendingPayment()->create([
            'travel_date' => now()->addDay()->toDateString(),
        ]);
        $pendingButFar = Booking::factory()->pendingPayment()->create([
            'travel_date' => now()->addDays(30)->toDateString(),
        ]);
        $paidAndSoon = Booking::factory()->paid()->create([
            'travel_date' => now()->addDay()->toDateString(),
        ]);
        // Caso que faltaba y dejaba pasar el bug: pago pendiente cuyo viaje YA
        // ocurrio. No es plata en riesgo (el viaje se perdio o se cobro aparte);
        // sin cota inferior en el scope, estas inundaban el filtro y lo volvian
        // un duplicado de "Pago pendiente".
        $pendingButPast = Booking::factory()->pendingPayment()->create([
            'travel_date' => now()->subMonths(2)->toDateString(),
        ]);

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->filterTable('payment_at_risk', true)
            ->assertCanSeeTableRecords([$atRisk])
            ->assertCanNotSeeTableRecords([$pendingButFar, $paidAndSoon, $pendingButPast]);
    }

    // ── Pago pendiente sin recordatorio ──────────────────────────────────────

    public function test_no_reminder_sent_filter(): void
    {
        $this->actingAs($this->admin());

        $uncontacted = Booking::factory()->pendingPayment()->create(['payment_reminder_sent_at' => null]);
        $alreadyReminded = Booking::factory()->pendingPayment()->create(['payment_reminder_sent_at' => now()]);
        $paidNoReminder = Booking::factory()->paid()->create(['payment_reminder_sent_at' => null]);

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->filterTable('no_reminder_sent', true)
            ->assertCanSeeTableRecords([$uncontacted])
            ->assertCanNotSeeTableRecords([$alreadyReminded, $paidNoReminder]);
    }

    // ── Otros atributos (recojo / descuento / personalizado agrupados) ─────

    public function test_pickup_attribute_filter(): void
    {
        $this->actingAs($this->admin());

        $withPickup = Booking::factory()->withPickup()->create();
        $withoutPickup = Booking::factory()->create(['pickup_point' => null]);

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->filterTable('booking_attributes', ['pickup' => 'with'])
            ->assertCanSeeTableRecords([$withPickup])
            ->assertCanNotSeeTableRecords([$withoutPickup]);
    }

    public function test_discount_attribute_filter(): void
    {
        $this->actingAs($this->admin());

        $withDiscount = Booking::factory()->withDiscount()->create();
        $withoutDiscount = Booking::factory()->create(['discount_amount' => 0]);

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->filterTable('booking_attributes', ['discount' => 'with'])
            ->assertCanSeeTableRecords([$withDiscount])
            ->assertCanNotSeeTableRecords([$withoutDiscount]);
    }

    public function test_only_custom_tours_attribute_filter(): void
    {
        $this->actingAs($this->admin());

        $custom = Booking::factory()->customTour()->create();
        $catalog = Booking::factory()->create();

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->filterTable('booking_attributes', ['only_custom' => true])
            ->assertCanSeeTableRecords([$custom])
            ->assertCanNotSeeTableRecords([$catalog]);
    }

    // ── Combinación de filtros ───────────────────────────────────────────────

    public function test_filters_can_be_combined(): void
    {
        $this->actingAs($this->admin());

        $category = Category::factory()->create();
        $tour = Tour::factory()->create(['category_id' => $category->id]);
        $otherCategory = Category::factory()->create();
        $otherTour = Tour::factory()->create(['category_id' => $otherCategory->id]);

        // Matches: paid + this category + within travel range.
        $match = Booking::factory()->paid()->create([
            'tour_id' => $tour->id,
            'travel_date' => '2026-09-15',
        ]);
        // Wrong payment status.
        $wrongPayment = Booking::factory()->pendingPayment()->create([
            'tour_id' => $tour->id,
            'travel_date' => '2026-09-15',
        ]);
        // Wrong category.
        $wrongCategory = Booking::factory()->paid()->create([
            'tour_id' => $otherTour->id,
            'travel_date' => '2026-09-15',
        ]);
        // Wrong date.
        $wrongDate = Booking::factory()->paid()->create([
            'tour_id' => $tour->id,
            'travel_date' => '2026-11-01',
        ]);

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->filterTable('payment_status', ['paid'])
            ->filterTable('category_id', [$category->id])
            ->filterTable('travel_date_range', [
                'travel_from' => '2026-09-01',
                'travel_until' => '2026-09-30',
            ])
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$wrongPayment, $wrongCategory, $wrongDate]);
    }

    // ── Pestañas rápidas ─────────────────────────────────────────────────────

    /**
     * La pestaña "Todas" lleva contador con el total de reservas, igual que
     * el resto de pestañas. Cuenta TODAS (no depende de la pestaña activa).
     */
    public function test_all_tab_has_badge_with_total_bookings(): void
    {
        $this->actingAs($this->admin());

        Booking::factory()->paid()->create();
        Booking::factory()->pendingPayment()->create();
        Booking::factory()->failedPayment()->create();

        $component = Livewire::test(ListBookings::class);
        $tabs = $component->instance()->getCachedTabs();

        $this->assertSame(3, $tabs['all']->getBadge());
        // Control: el contador sigue el dato, no es una constante.
        $this->assertSame(1, $tabs['paid']->getBadge());

        Booking::factory()->paid()->create();
        $tabs = Livewire::test(ListBookings::class)->instance()->getCachedTabs();
        $this->assertSame(4, $tabs['all']->getBadge());
    }

    public function test_paid_tab_shows_only_paid_bookings(): void
    {
        $this->actingAs($this->admin());

        $paid = Booking::factory()->paid()->create();
        $pending = Booking::factory()->pendingPayment()->create();

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->set('activeTab', 'paid')
            ->assertCanSeeTableRecords([$paid])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    public function test_at_risk_tab_shows_only_bookings_at_risk(): void
    {
        $this->actingAs($this->admin());

        $atRisk = Booking::factory()->pendingPayment()->create([
            'travel_date' => now()->addDay()->toDateString(),
        ]);
        $notAtRisk = Booking::factory()->pendingPayment()->create([
            'travel_date' => now()->addDays(30)->toDateString(),
        ]);

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->set('activeTab', 'at_risk')
            ->assertCanSeeTableRecords([$atRisk])
            ->assertCanNotSeeTableRecords([$notAtRisk]);
    }

    /**
     * `travel_date` en BookingCalendar::today() (Lima), no en now()->toDateString()
     * (UTC vía app.timezone): entre las 19:00 y la medianoche de Lima ambas
     * fechas difieren, y este test con now() pasaba antes solo porque la
     * implementación TAMBIÉN usaba UTC (Date::today()) — el mismo bug en
     * ambos lados se cancelaba. Corregida la implementación, el test debe
     * medir contra la MISMA fuente de verdad que ahora usa el tab.
     */
    public function test_today_tab_shows_only_todays_departures(): void
    {
        $this->actingAs($this->admin());

        $today = Booking::factory()->create(['travel_date' => BookingCalendar::today()->toDateString()]);
        $tomorrow = Booking::factory()->create(['travel_date' => BookingCalendar::today()->addDay()->toDateString()]);

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->set('activeTab', 'today')
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$tomorrow]);
    }

    /**
     * "Mañana" (tab nuevo) tiene que calcular su fecha con
     * App\Support\BookingCalendar::today(), no con now()/Date::today(): el
     * servidor corre en UTC (config/app.php: 'timezone' => 'UTC') y el
     * operador está en Lima (America/Lima, ver config/booking.php). Las
     * fechas se comparan como string Y-m-d (whereDate vía scopeTravelingBetween)
     * a propósito: la suite corre en SQLite y producción en MySQL, y un
     * cast de fecha entre motores da falsos verdes si se compara distinto.
     */
    public function test_tomorrow_tab_shows_only_next_day_departures(): void
    {
        $this->actingAs($this->admin());

        $lima = BookingCalendar::today();
        $tomorrowInLima = $lima->copy()->addDay()->toDateString();
        $todayInLima = $lima->toDateString();

        $tomorrow = Booking::factory()->create(['travel_date' => $tomorrowInLima]);
        $today = Booking::factory()->create(['travel_date' => $todayInLima]);

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->set('activeTab', 'tomorrow')
            ->assertCanSeeTableRecords([$tomorrow])
            ->assertCanNotSeeTableRecords([$today]);
    }

    /**
     * El caso que motivó el fix: a las 23:30 hora de Lima (04:30 UTC del día
     * SIGUIENTE), una reserva con travel_date = mañana-en-Lima tiene que
     * seguir apareciendo en el tab "Mañana", y una con la fecha de hoy-en-Lima
     * NO debe aparecer. Antes de este fix, 'today' (y por construcción
     * 'tomorrow' si hubiera copiado el mismo patrón con Date::today()) leía
     * la fecha en UTC: a esa hora ya es "un día más" en el servidor, así que
     * el tab mostraba las salidas de PASADO MAÑANA bajo la etiqueta "Mañana".
     */
    public function test_tomorrow_tab_uses_lima_time_not_utc_near_midnight(): void
    {
        $this->actingAs($this->admin());

        // 2026-09-29 23:30 hora de Lima == 2026-09-30 04:30 UTC.
        Carbon::setTestNow(Carbon::parse('2026-09-30 04:30:00', 'UTC'));

        try {
            // "Mañana" en Lima a esta hora es 2026-09-30 (no 2026-10-01, que
            // es lo que UTC diría que es "pasado mañana" si se comparara mal).
            $tomorrowInLima = Booking::factory()->create(['travel_date' => '2026-09-30']);
            $todayInLima = Booking::factory()->create(['travel_date' => '2026-09-29']);

            Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
                ->set('activeTab', 'tomorrow')
                ->assertCanSeeTableRecords([$tomorrowInLima])
                ->assertCanNotSeeTableRecords([$todayInLima]);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * B5: 'this_week' tenía el MISMO bug que 'today'/'tomorrow' antes del
     * fix de la Tarea A — calculaba el inicio/fin de semana con
     * Date::today() (UTC vía app.timezone) en vez de BookingCalendar::today()
     * (Lima). Mismo caso límite: 23:30 hora de Lima ya es 04:30 del día
     * SIGUIENTE en UTC, lo que podía correr toda la ventana de la semana un
     * día hacia adelante.
     */
    public function test_this_week_tab_uses_lima_time_not_utc_near_midnight(): void
    {
        $this->actingAs($this->admin());

        // 2026-09-29 23:30 hora de Lima == 2026-09-30 04:30 UTC.
        Carbon::setTestNow(Carbon::parse('2026-09-30 04:30:00', 'UTC'));

        try {
            $limaToday = BookingCalendar::today();
            $startOfWeekLima = $limaToday->copy()->startOfWeek()->toDateString();
            $endOfWeekLima = $limaToday->copy()->endOfWeek()->toDateString();

            // Dentro de la semana calculada en hora de Lima: debe aparecer.
            $inWeek = Booking::factory()->create(['travel_date' => $startOfWeekLima]);
            // Un día después del fin de semana-en-Lima: NO debe aparecer. Si
            // 'this_week' volviera a usar UTC, la ventana completa se corre
            // un día hacia adelante y este caso (junto con $inWeek) se
            // invierte — el test detectaría la regresión.
            $outOfWeek = Booking::factory()->create([
                'travel_date' => Carbon::parse($endOfWeekLima)->addDay()->toDateString(),
            ]);

            Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
                ->set('activeTab', 'this_week')
                ->assertCanSeeTableRecords([$inWeek])
                ->assertCanNotSeeTableRecords([$outOfWeek]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_default_tab_is_tomorrow(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ListBookings::class)->assertSet('activeTab', 'tomorrow');
    }
}
