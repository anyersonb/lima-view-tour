<?php

namespace App\Console;

use App\Support\BookingCalendar;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Recuperación de carritos abandonados (recordatorios 1h y 24h)
        $schedule->command('carts:send-recovery')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Recordatorio de pago para reservas "pagar luego" (2 días antes del tour)
        $schedule->command('bookings:send-payment-reminders')
            ->dailyAt('13:00')
            ->timezone(BookingCalendar::timezone())
            ->withoutOverlapping()
            ->runInBackground();

        // Reservas de último momento (tour hoy/mañana): recordatorio ~2h tras
        // reservar, solo en horario razonable de Lima. Un solo recordatorio por
        // reserva (payment_reminder_sent_at), así que no choca con la tanda diaria.
        $schedule->command('bookings:send-payment-reminders --urgent')
            ->everyFifteenMinutes()
            ->between(
                config('cart.payment_reminder.urgent_window_start', '07:00'),
                config('cart.payment_reminder.urgent_window_end', '22:00')
            )
            ->timezone(BookingCalendar::timezone())
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
