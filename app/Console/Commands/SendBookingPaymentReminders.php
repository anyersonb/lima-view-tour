<?php

namespace App\Console\Commands;

use App\Mail\BookingPaymentReminder;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBookingPaymentReminders extends Command
{
    protected $signature = 'bookings:send-payment-reminders
                            {--dry : Muestra qué se enviaría sin enviar correos ni tocar la BD}';

    protected $description = 'Envía recordatorios de pago a reservas "pagar luego" pendientes, X días antes del tour';

    public function handle(): int
    {
        $cfg        = config('cart.payment_reminder');
        $enabled    = (bool) ($cfg['enabled'] ?? true);
        $daysBefore = (int) ($cfg['days_before'] ?? 2);
        $batch      = (int) ($cfg['batch_size'] ?? 100);
        $dry        = (bool) $this->option('dry');

        if (! $enabled) {
            $this->info('Recordatorios de pago desactivados (cart.payment_reminder.enabled = false).');
            return self::SUCCESS;
        }

        $today  = now()->startOfDay();
        $target = $today->copy()->addDays($daysBefore);

        // Reservas "pagar luego" aún pendientes cuyo tour cae dentro de la ventana
        // [hoy, hoy + days_before] y a las que aún no se les recordó el pago.
        // Usamos la ventana (no un día exacto) para no perder reservas si el cron
        // se salta una corrida o si la reserva se creó ya dentro de la ventana.
        $bookings = Booking::query()
            ->where('payment_method', 'pay_later')
            ->where('payment_status', 'pending')
            ->where('status', 'pending')
            ->whereNull('payment_reminder_sent_at')
            ->whereNotNull('customer_email')
            ->whereDate('travel_date', '>=', $today)
            ->whereDate('travel_date', '<=', $target)
            ->orderBy('travel_date')
            ->limit($batch)
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No hay reservas por recordar.');
            return self::SUCCESS;
        }

        // Un solo correo por cliente + fecha de viaje (agrupa varias reservas).
        $groups = $bookings->groupBy(fn (Booking $b) => strtolower($b->customer_email) . '|' . $b->travel_date->toDateString());

        $sent = 0;

        foreach ($groups as $group) {
            $first = $group->first();
            $email = $first->customer_email;
            $refs  = $group->pluck('reference')->all();

            if ($dry) {
                $this->line("[dry] {$email} · tour {$first->travel_date->toDateString()} · " . implode(', ', $refs));
                continue;
            }

            try {
                Mail::to($email)->send(new BookingPaymentReminder($group->values(), $email));

                Booking::whereIn('id', $group->pluck('id'))
                    ->update(['payment_reminder_sent_at' => now()]);

                $sent += $group->count();

                Log::info('booking.payment_reminder.sent', [
                    'email'      => $email,
                    'bookings'   => $refs,
                    'travel_date'=> $first->travel_date->toDateString(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('booking.payment_reminder.failed', [
                    'email'   => $email,
                    'message' => $e->getMessage(),
                ]);
                $this->error("Fallo al enviar a {$email}: {$e->getMessage()}");
            }
        }

        $this->info($dry
            ? "Simulación: {$bookings->count()} reserva(s) en {$groups->count()} correo(s)."
            : "Recordatorios enviados: {$sent} reserva(s) en {$groups->count()} correo(s).");

        return self::SUCCESS;
    }
}
