<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmed;
use App\Mail\BookingNotificationAdmin;
use App\Mail\BookingPaymentReminder;
use App\Models\Booking;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M-5 (docs/payment-links/SECURITY.md): con `travel_date` nulo (link de pago
 * sin fecha, "se coordinará después"), `Carbon::parse(null)` devuelve
 * `now()` — sin el guard, los correos de confirmación/aviso/recordatorio
 * mostrarían "hoy" como si fuera la fecha del tour, en vez de avisar que
 * está pendiente de coordinar.
 */
class BookingEmailNullTravelDateTest extends TestCase
{
    use RefreshDatabase;

    private function bookingWithoutTravelDate(): Booking
    {
        $tour = Tour::factory()->create(['price' => 150]);

        return Booking::create([
            'tour_id' => $tour->id,
            'tour_title_snapshot' => $tour->title_es,
            'customer_name' => 'Cliente Sin Fecha',
            'customer_email' => 'sinfecha@example.com',
            'customer_phone' => '987654321',
            'travel_date' => null,
            'adults' => 1,
            'children' => 0,
            'unit_price' => 150,
            'total_price' => 150,
            'currency' => 'USD',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'payment_link',
            'payment_reference' => 'CAP-NO-DATE',
            'locale' => 'es',
        ]);
    }

    public function test_confirmed_email_shows_date_to_be_arranged_instead_of_today(): void
    {
        $booking = $this->bookingWithoutTravelDate();

        $html = (new BookingConfirmed(collect([$booking]), $booking->customer_email))->render();

        $this->assertStringContainsString(__('payment_links.date_to_be_arranged'), $html);
        $this->assertStringNotContainsString(now()->locale('es')->isoFormat('D MMM YYYY'), $html);
    }

    public function test_admin_notification_email_shows_date_to_be_arranged_instead_of_today(): void
    {
        $booking = $this->bookingWithoutTravelDate();

        $html = (new BookingNotificationAdmin(collect([$booking]), 'now'))->render();

        $this->assertStringContainsString(__('payment_links.date_to_be_arranged'), $html);
        $this->assertStringNotContainsString(now()->locale('es')->isoFormat('D MMM YYYY'), $html);
    }

    public function test_payment_reminder_email_shows_date_to_be_arranged_instead_of_today(): void
    {
        $booking = $this->bookingWithoutTravelDate();

        $html = (new BookingPaymentReminder(collect([$booking]), $booking->customer_email))->render();

        $this->assertStringContainsString(__('payment_links.date_to_be_arranged'), $html);
        $this->assertStringNotContainsString(now()->locale('es')->isoFormat('D MMM YYYY'), $html);
    }
}
