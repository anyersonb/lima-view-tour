<?php

namespace Tests\Feature;

use App\Mail\BookingPaymentReminder;
use App\Models\Booking;
use App\Models\Tour;
use App\Services\BookingNotifier;
use App\Support\BookingCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Booking e-mails read $booking->tour for every booking: that must be ONE query
 * (not one per booking), and the payment reminder must carry the tour cover.
 */
class BookingEmailTourLoadingTest extends TestCase
{
    use RefreshDatabase;

    /** @return \Illuminate\Database\Eloquent\Collection<int, Booking> */
    private function freshBookings(int $n)
    {
        $ids = [];
        foreach (range(1, $n) as $i) {
            $tour = Tour::factory()->create(['cover_image' => "tours/cover-{$i}.jpg"]);
            $ids[] = Booking::factory()->create([
                'tour_id' => $tour->id,
                'customer_email' => 'cliente@example.com',
                'travel_date' => BookingCalendar::today()->addDays(10)->toDateString(),
            ])->id;
        }

        // Fresh instances: no relation loaded, like in CheckoutController / the panel.
        return Booking::whereIn('id', $ids)->get();
    }

    private function toursQueries(): int
    {
        return collect(DB::getQueryLog())
            ->filter(fn ($q) => preg_match('/from [`"]tours[`"]/i', $q['query']))
            ->count();
    }

    public function test_send_loads_the_tours_with_a_single_query(): void
    {
        $bookings = $this->freshBookings(4);

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(BookingNotifier::class)->send($bookings, true);

        $this->assertSame(1, $this->toursQueries(), 'tours must be loaded once, not per booking/mail');
    }

    public function test_resend_confirmation_loads_the_tours_with_a_single_query(): void
    {
        $bookings = $this->freshBookings(3);

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(BookingNotifier::class)->resendCustomerConfirmation($bookings);

        $this->assertSame(1, $this->toursQueries());
    }

    public function test_query_count_does_not_grow_with_the_number_of_bookings(): void
    {
        // Warm-up: the first send also fills one-off caches (settings...).
        app(BookingNotifier::class)->send($this->freshBookings(1), true);

        $few = $this->freshBookings(2);
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(BookingNotifier::class)->send($few, true);
        $withFew = count(DB::getQueryLog());

        $many = $this->freshBookings(6);
        DB::flushQueryLog();
        app(BookingNotifier::class)->send($many, true);
        $withMany = count(DB::getQueryLog());

        $this->assertSame($withFew, $withMany);
    }

    public function test_payment_reminder_command_puts_the_tour_cover_in_the_email(): void
    {
        Mail::fake();
        $tour = Tour::factory()->create(['cover_image' => 'tours/reminder-cover.jpg', 'departure_time' => null]);
        $booking = Booking::factory()->create([
            'tour_id' => $tour->id,
            'customer_email' => 'cliente@example.com',
            'travel_date' => BookingCalendar::today()->addDays(1)->toDateString(),
        ]);
        $booking->forceFill(['created_at' => now()->subHours(8)])->saveQuietly();

        $this->artisan('bookings:send-payment-reminders')->assertSuccessful();

        Mail::assertSent(BookingPaymentReminder::class, function (BookingPaymentReminder $mail) {
            $html = $mail->render();

            return str_contains($html, 'tours/reminder-cover.jpg')
                && ! str_contains($html, 'banner-hero.jpg"');
        });
    }
}
