<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\BookingResource\Pages\ListBookings;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
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
            ->filterTable('payment_status', ['paid'])
            ->assertCanSeeTableRecords([$paid])
            ->assertCanNotSeeTableRecords([$pending, $failed]);

        Livewire::test(ListBookings::class)
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

    public function test_paid_tab_shows_only_paid_bookings(): void
    {
        $this->actingAs($this->admin());

        $paid = Booking::factory()->paid()->create();
        $pending = Booking::factory()->pendingPayment()->create();

        Livewire::test(ListBookings::class)
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
            ->set('activeTab', 'at_risk')
            ->assertCanSeeTableRecords([$atRisk])
            ->assertCanNotSeeTableRecords([$notAtRisk]);
    }

    public function test_today_tab_shows_only_todays_departures(): void
    {
        $this->actingAs($this->admin());

        $today = Booking::factory()->create(['travel_date' => now()->toDateString()]);
        $tomorrow = Booking::factory()->create(['travel_date' => now()->addDay()->toDateString()]);

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'today')
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$tomorrow]);
    }
}
