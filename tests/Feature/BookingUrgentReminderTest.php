<?php

namespace Tests\Feature;

use App\Mail\BookingPaymentReminder;
use App\Models\Booking;
use App\Models\Tour;
use App\Support\BookingCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Recordatorio de pago de último momento: `--urgent` (tour hoy/mañana, hora
 * Lima) sale pasadas urgent_delay_hours desde la creación; el resto espera a
 * la tanda diaria. Un solo recordatorio por reserva.
 */
class BookingUrgentReminderTest extends TestCase
{
    use RefreshDatabase;

    private function booking(int $daysAhead, int $createdHoursAgo, array $extra = [], ?string $departure = null): Booking
    {
        $tour = Tour::factory()->create(['departure_time' => $departure]);

        $booking = Booking::factory()->create(array_merge([
            'tour_id' => $tour->id,
            'travel_date' => BookingCalendar::today()->addDays($daysAhead)->toDateString(),
            'customer_email' => 'cliente'.uniqid().'@example.com',
        ], $extra));

        $booking->forceFill(['created_at' => now()->subHours($createdHoursAgo)->subMinutes(5)])->saveQuietly();

        return $booking;
    }

    public function test_urgent_skips_booking_for_tomorrow_created_1h_ago(): void
    {
        Mail::fake();
        $b = $this->booking(1, 1);

        $this->artisan('bookings:send-payment-reminders --urgent')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNull($b->fresh()->payment_reminder_sent_at);
    }

    public function test_urgent_sends_booking_for_tomorrow_created_3h_ago(): void
    {
        Mail::fake();
        $b = $this->booking(1, 3);

        $this->artisan('bookings:send-payment-reminders --urgent')->assertSuccessful();

        Mail::assertSent(BookingPaymentReminder::class, 1);
        $this->assertNotNull($b->fresh()->payment_reminder_sent_at);
    }

    public function test_urgent_sends_booking_for_today_created_3h_ago(): void
    {
        Mail::fake();
        $b = $this->booking(0, 3);

        $this->artisan('bookings:send-payment-reminders --urgent')->assertSuccessful();

        Mail::assertSent(BookingPaymentReminder::class, 1);
        $this->assertNotNull($b->fresh()->payment_reminder_sent_at);
    }

    public function test_urgent_ignores_booking_two_days_ahead_but_daily_run_sends_it(): void
    {
        Mail::fake();
        $b = $this->booking(2, 5);

        $this->artisan('bookings:send-payment-reminders --urgent')->assertSuccessful();
        Mail::assertNothingSent();
        $this->assertNull($b->fresh()->payment_reminder_sent_at);

        $this->artisan('bookings:send-payment-reminders')->assertSuccessful();
        Mail::assertSent(BookingPaymentReminder::class, 1);
        $this->assertNotNull($b->fresh()->payment_reminder_sent_at);
    }

    public function test_daily_run_also_waits_the_minimum_delay_after_creation(): void
    {
        Mail::fake();
        $this->booking(1, 1);

        $this->artisan('bookings:send-payment-reminders')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_already_reminded_booking_is_never_sent_again(): void
    {
        Mail::fake();
        $this->booking(1, 6, ['payment_reminder_sent_at' => now()->subHour()]);

        $this->artisan('bookings:send-payment-reminders --urgent')->assertSuccessful();
        $this->artisan('bookings:send-payment-reminders')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_urgent_then_daily_sends_only_one_reminder(): void
    {
        Mail::fake();
        $this->booking(1, 4);

        $this->artisan('bookings:send-payment-reminders --urgent')->assertSuccessful();
        $this->artisan('bookings:send-payment-reminders')->assertSuccessful();

        Mail::assertSent(BookingPaymentReminder::class, 1);
    }

    public function test_urgent_skips_today_tour_whose_departure_time_already_passed(): void
    {
        Mail::fake();
        $this->booking(0, 3, [], '00:01');

        $this->artisan('bookings:send-payment-reminders --urgent')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_urgent_still_sends_today_tour_with_unreadable_departure_time(): void
    {
        Mail::fake();
        $this->booking(0, 3, [], 'Todo el día');

        $this->artisan('bookings:send-payment-reminders --urgent')->assertSuccessful();

        Mail::assertSent(BookingPaymentReminder::class, 1);
    }

    public function test_urgent_dry_run_does_not_send_nor_mark(): void
    {
        Mail::fake();
        $b = $this->booking(1, 3);

        $this->artisan('bookings:send-payment-reminders --urgent --dry')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNull($b->fresh()->payment_reminder_sent_at);
    }

    public function test_pax_breakdown_uses_singular_and_plural_in_all_locales(): void
    {
        $expected = ['es' => '(1 adulto, 1 niño)', 'en' => '(1 adult, 1 child)', 'pt' => '(1 adulto, 1 criança)'];
        $plural = ['es' => '(2 adultos, 0 niños)', 'en' => '(2 adults, 0 children)', 'pt' => '(2 adultos, 0 crianças)'];

        foreach ($expected as $loc => $one) {
            $fmt = fn (int $a, int $c) => __('checkout.pax_breakdown', [
                'adults' => trans_choice('checkout.adults_count', $a, [], $loc),
                'children' => trans_choice('checkout.children_count', $c, [], $loc),
            ], $loc);

            $this->assertSame($one, $fmt(1, 1));
            $this->assertSame($plural[$loc], $fmt(2, 0));
        }
    }

    public function test_concurrent_run_that_loses_the_claim_sends_nothing(): void
    {
        Mail::fake();
        $b = $this->booking(1, 4);

        // Otro proceso reclama la reserva justo DESPUÉS de que este la leyó.
        Booking::retrieved(function (Booking $m) {
            \Illuminate\Support\Facades\DB::table('bookings')->where('id', $m->id)
                ->update(['payment_reminder_sent_at' => now()]);
        });

        $this->artisan('bookings:send-payment-reminders --urgent')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_failed_send_reverts_claim_and_stops_after_max_attempts(): void
    {
        $b = $this->booking(1, 4);
        Mail::shouldReceive('to')->times(3)->andThrow(new \RuntimeException('smtp down'));

        for ($i = 0; $i < 5; $i++) {
            $this->artisan('bookings:send-payment-reminders --urgent')->assertSuccessful();
            $this->assertNull($b->fresh()->payment_reminder_sent_at);
        }
    }

    public function test_post_send_log_failure_does_not_revert_the_claim(): void
    {
        Mail::fake();
        $b = $this->booking(1, 4);

        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->andThrow(new \RuntimeException('log down'));
        \Illuminate\Support\Facades\Log::shouldReceive('error', 'warning')->zeroOrMoreTimes();

        $this->artisan('bookings:send-payment-reminders --urgent')->assertSuccessful();

        Mail::assertSent(BookingPaymentReminder::class, 1);
        $this->assertNotNull($b->fresh()->payment_reminder_sent_at);
    }
}
