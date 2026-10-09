<?php

namespace App\Console\Commands;

use App\Mail\BookingPaymentReminder;
use App\Models\Booking;
use App\Support\BookingCalendar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Throwable;

class SendBookingPaymentReminders extends Command
{
    protected $signature = 'bookings:send-payment-reminders
                            {--dry : Muestra qué se enviaría sin enviar correos ni tocar la BD}
                            {--urgent : Solo reservas de último momento (tour hoy o mañana, hora Lima), pasado el retraso mínimo}';

    protected $description = 'Envía recordatorios de pago a reservas "pagar luego" pendientes, X días antes del tour';

    private static function failKey(int $bookingId): string
    {
        return 'payment_reminder:failures:'.$bookingId;
    }

    private function hasDeparted(Booking $booking, Carbon $today): bool
    {
        if (! $booking->travel_date->isSameDay($today)) {
            return false;
        }

        $raw = trim((string) ($booking->tour?->departure_time ?? ''));

        if (! preg_match('/^\d{1,2}:\d{2}\s*([ap]\.?m\.?)?$/i', $raw)) {
            return false;
        }

        try {
            $departure = Carbon::parse($raw, BookingCalendar::timezone())
                ->setDateFrom($today);
        } catch (Throwable) {
            return false;
        }

        return $departure->lte(Carbon::now(BookingCalendar::timezone()));
    }

    public function handle(): int
    {
        $cfg        = config('cart.payment_reminder');
        $enabled    = (bool) ($cfg['enabled'] ?? true);
        $daysBefore = (int) ($cfg['days_before'] ?? 2);
        $batch      = (int) ($cfg['batch_size'] ?? 100);
        $dry        = (bool) $this->option('dry');
        $urgent     = (bool) $this->option('urgent');
        $delayHours = max(0, (int) ($cfg['urgent_delay_hours'] ?? 2));

        if (! $enabled) {
            $this->info('Recordatorios de pago desactivados (cart.payment_reminder.enabled = false).');
            return self::SUCCESS;
        }

        // "Hoy" en hora de Lima (misma fuente que el checkout), no en UTC.
        $today  = BookingCalendar::today();
        // Modo urgente: solo hoy y mañana. Modo diario: hasta hoy + days_before.
        $target = $today->copy()->addDays($urgent ? 1 : $daysBefore);

        // Reservas "pagar luego" aún pendientes cuyo tour cae dentro de la ventana
        // [hoy, hoy + days_before] y a las que aún no se les recordó el pago.
        // Usamos la ventana (no un día exacto) para no perder reservas si el cron
        // se salta una corrida o si la reserva se creó ya dentro de la ventana.
        // En ambos modos se respeta el retraso mínimo desde la creación: da tiempo
        // a que el cliente pague solo antes de recibir el recordatorio.
        $bookings = Booking::query()
            ->with('tour:id,departure_time')
            ->where('payment_method', 'pay_later')
            ->where('payment_status', 'pending')
            ->where('status', 'pending')
            ->whereNull('payment_reminder_sent_at')
            ->whereNotNull('customer_email')
            ->whereDate('travel_date', '>=', $today)
            ->whereDate('travel_date', '<=', $target)
            ->where('created_at', '<=', now()->subHours($delayHours))
            ->orderBy('travel_date')
            ->limit($batch)
            ->get();

        // Reservas que ya agotaron sus intentos fallidos no se reintentan.
        $maxAttempts = max(1, (int) ($cfg['max_attempts'] ?? 3));
        $bookings = $bookings->reject(fn (Booking $b) => (int) Cache::get(self::failKey($b->id), 0) >= $maxAttempts)->values();

        if ($urgent) {
            // Tour de HOY que ya salió: el recordatorio no tiene sentido.
            // Best-effort: tours.departure_time es texto libre; solo se usa si
            // es una hora clara (HH:MM [am/pm]). Si no se puede leer, se envía.
            $bookings = $bookings->reject(fn (Booking $b) => $this->hasDeparted($b, $today))->values();
        }

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

            // Reclamo atómico POR RESERVA: dos procesos (diaria + urgente a las
            // 13:00) pueden leer lo mismo; solo quien logra el UPDATE ... WHERE
            // sent_at IS NULL envía esa reserva.
            $claimed = $group->filter(fn (Booking $b) => Booking::whereKey($b->id)
                ->whereNull('payment_reminder_sent_at')
                ->update(['payment_reminder_sent_at' => now()]) === 1)->values();

            if ($claimed->isEmpty()) {
                continue;
            }

            $mailed = false;

            try {
                Mail::to($email)->send(new BookingPaymentReminder($claimed, $email));
                $mailed = true;

                foreach ($claimed as $b) {
                    Cache::forget(self::failKey($b->id));
                }

                $sent += $claimed->count();

                Log::info('booking.payment_reminder.sent', [
                    'email'      => $email,
                    'bookings'   => $claimed->pluck('reference')->all(),
                    'travel_date'=> $first->travel_date->toDateString(),
                ]);
            } catch (Throwable $e) {
                if ($mailed) {
                    // El correo YA salió: un fallo posterior (Log/Cache) no debe revertir el reclamo.
                    report($e);

                    continue;
                }

                // Revertir el reclamo y contar el fallo (máx. max_attempts, sin migración).
                Booking::whereIn('id', $claimed->pluck('id'))->update(['payment_reminder_sent_at' => null]);

                foreach ($claimed as $b) {
                    Cache::put(self::failKey($b->id), (int) Cache::get(self::failKey($b->id), 0) + 1, now()->addDays(3));
                }

                Log::warning('booking.payment_reminder.failed', [
                    'email'   => $email,
                    'message' => $e->getMessage(),
                ]);
                $this->error("Fallo al enviar a {$email} (ver log).");
            }
        }

        $this->info($dry
            ? "Simulación: {$bookings->count()} reserva(s) en {$groups->count()} correo(s)."
            : "Recordatorios enviados: {$sent} reserva(s) en {$groups->count()} correo(s).");

        return self::SUCCESS;
    }
}
