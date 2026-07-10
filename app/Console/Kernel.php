<?php

namespace App\Console;

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
            ->dailyAt('09:00')
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
