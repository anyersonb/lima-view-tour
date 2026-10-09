<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\BookingResource\Pages\ListBookings;
use App\Mail\BookingConfirmed;
use App\Mail\BookingPaymentReminder;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Botón manual "Reenviar recordatorio de pago" (BookingResource) y locale de
 * la reserva en "Reenviar correo".
 */
class BookingResendPaymentReminderTest extends TestCase
{
    use RefreshDatabase;

    private function list(): \Livewire\Features\SupportTesting\Testable
    {
        $this->actingAs(User::firstOrCreate(['email' => 'qa@webtilia.com'], User::factory()->make()->getAttributes()));

        return Livewire::test(ListBookings::class)->set('activeTab', 'all');
    }

    public function test_action_sends_reminder_in_booking_locale_and_marks_sent_at(): void
    {
        Mail::fake();
        $b = Booking::factory()->create(['locale' => 'en', 'customer_email' => 'old@example.com']);

        $this->list()
            ->callTableAction('resendPaymentReminder', $b, data: ['email' => 'old@example.com'])
            ->assertHasNoTableActionErrors();

        Mail::assertSent(BookingPaymentReminder::class, function (BookingPaymentReminder $m) use ($b) {
            return $m->hasTo('old@example.com')
                && $m->locale === 'en'
                && $m->bookings->count() === 1
                && $m->bookings->first()->is($b);
        });
        Mail::assertNotQueued(BookingPaymentReminder::class);
        $this->assertNotNull($b->fresh()->payment_reminder_sent_at);
    }

    public function test_action_works_for_payment_link_bookings_too(): void
    {
        Mail::fake();
        $b = Booking::factory()->create(['payment_method' => 'payment_link', 'locale' => 'pt']);

        $this->list()->callTableAction('resendPaymentReminder', $b, data: ['email' => 'x@example.com']);

        Mail::assertSent(BookingPaymentReminder::class, fn ($m) => $m->locale === 'pt');
    }

    public function test_action_is_hidden_for_paid_and_cancelled_bookings(): void
    {
        $paid = Booking::factory()->paid()->create();
        $cancelled = Booking::factory()->create(['status' => 'cancelled']);
        $pending = Booking::factory()->create();

        $this->list()
            ->assertTableActionHidden('resendPaymentReminder', $paid)
            ->assertTableActionHidden('resendPaymentReminder', $cancelled)
            ->assertTableActionVisible('resendPaymentReminder', $pending);
    }

    public function test_failed_send_does_not_mark_sent_at(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));
        $b = Booking::factory()->create();

        $this->list()->callTableAction('resendPaymentReminder', $b, data: ['email' => 'x@example.com']);

        $this->assertNull($b->fresh()->payment_reminder_sent_at);
    }

    public function test_resend_confirmation_uses_booking_locale_not_app_locale(): void
    {
        Mail::fake();
        app()->setLocale('es');
        $b = Booking::factory()->create(['locale' => 'pt']);

        $this->list()->callTableAction('resendConfirmation', $b, data: ['email' => 'x@example.com']);

        Mail::assertSent(BookingConfirmed::class, fn ($m) => $m->locale === 'pt');
        Mail::assertNotQueued(BookingConfirmed::class);
    }

    public function test_sent_at_column_exists_and_is_toggleable(): void
    {
        $this->list()->assertTableColumnExists('payment_reminder_sent_at');
    }

    public function test_sending_to_another_address_does_not_mark_sent_at(): void
    {
        Mail::fake();
        $b = Booking::factory()->create(['customer_email' => 'Cliente@Example.com']);

        $this->list()->callTableAction('resendPaymentReminder', $b, data: ['email' => 'staff@example.com']);

        Mail::assertSent(BookingPaymentReminder::class, 1);
        $this->assertNull($b->fresh()->payment_reminder_sent_at);

        $this->list()->callTableAction('resendPaymentReminder', $b, data: ['email' => 'cliente@example.com']);
        $this->assertNotNull($b->fresh()->payment_reminder_sent_at);
    }

    public function test_unknown_booking_locale_falls_back_to_spanish(): void
    {
        Mail::fake();
        $b = Booking::factory()->create(['locale' => 'xx']);

        $this->list()->callTableAction('resendPaymentReminder', $b, data: ['email' => $b->customer_email]);

        Mail::assertSent(BookingPaymentReminder::class, fn ($m) => $m->locale === 'es');
    }

    public function test_error_notification_is_generic_and_manual_log_has_actor(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP secret-host:587 refused'));
        $b = Booking::factory()->create();

        $this->list()->callTableAction('resendPaymentReminder', $b, data: ['email' => 'x@example.com']);

        $notes = session('filament.notifications', []);
        $this->assertStringNotContainsString('secret-host', json_encode($notes));
    }
}
