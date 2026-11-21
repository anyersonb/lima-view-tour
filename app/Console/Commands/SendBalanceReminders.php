<?php

namespace App\Console\Commands;

use App\Exceptions\BalanceLinkException;
use App\Models\Booking;
use App\Models\PaymentLink;
use App\Services\BalancePaymentService;
use App\Support\BookingCalendar;
use App\Support\PaymentPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Recordatorio automático del SALDO: para reservas con adelanto (partial) cuyo
 * tour sale en 3 días o menos, genera (o reutiliza) el link de pago del saldo y
 * se lo envía al cliente. Un solo recordatorio por reserva
 * (balance_reminder_sent_at, reclamo atómico: dos procesos no lo duplican).
 *
 * El link vence 48 h antes del tour: si ya no se puede generar (tour en menos
 * de 48 h) NO se envía nada y se deja un aviso en el log para que el equipo
 * cobre el saldo a mano.
 *
 * Además marca como 'expired' los links de saldo vencidos (el panel no miente).
 */
class SendBalanceReminders extends Command
{
    protected $signature = 'bookings:send-balance-reminders
                            {--dry : Muestra qué se enviaría sin enviar correos ni tocar la BD}';

    protected $description = 'Envía el link de pago del saldo a reservas con adelanto, 3 días antes del tour';

    public function handle(BalancePaymentService $balance): int
    {
        $dry = (bool) $this->option('dry');

        // Higiene: links de saldo vencidos por reloj => 'expired'.
        if (! $dry) {
            PaymentLink::query()
                ->where('purpose', PaymentLink::PURPOSE_BALANCE)
                ->where('status', 'pending')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->update(['status' => 'expired']);
        }

        $today = BookingCalendar::today();
        $target = $today->copy()->addDays(PaymentPlan::BALANCE_REMINDER_DAYS_BEFORE);
        $batch = max(1, (int) config('cart.balance_reminder.batch_size', 100));

        $bookings = Booking::query()
            ->with('tour')
            ->withPendingBalance()
            ->whereNull('balance_reminder_sent_at')
            ->whereNotNull('customer_email')
            ->whereDate('travel_date', '>=', $today)
            ->whereDate('travel_date', '<=', $target)
            ->orderBy('travel_date')
            ->limit($batch)
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No hay saldos por recordar.');

            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;

        foreach ($bookings as $booking) {
            if ($dry) {
                $this->line("[dry] {$booking->customer_email} · {$booking->reference} · saldo US$".number_format((float) $booking->balance_due, 2).' · tour '.$booking->travel_date->toDateString());

                continue;
            }

            // Reclamo atómico POR RESERVA.
            $claimed = Booking::whereKey($booking->id)
                ->whereNull('balance_reminder_sent_at')
                ->update(['balance_reminder_sent_at' => now()]) === 1;

            if (! $claimed) {
                continue;
            }

            try {
                $link = $balance->createLink($booking, null, false);
                $balance->sendLink($booking, $link, true);
                $sent++;

                Log::info('booking.balance_reminder.sent', [
                    'booking' => $booking->reference,
                    'payment_link_id' => $link->id,
                    'travel_date' => $booking->travel_date->toDateString(),
                ]);
            } catch (BalanceLinkException $e) {
                // Plazo vencido / saldo ya pagado: no es un fallo de envío; no se reintenta.
                $skipped++;
                Log::warning('booking.balance_reminder.skipped', [
                    'booking' => $booking->reference,
                    'reason' => $e->getMessage(),
                ]);
            } catch (Throwable $e) {
                // Fallo de correo/BD: se libera el reclamo para reintentar en la próxima corrida.
                Booking::whereKey($booking->id)->update(['balance_reminder_sent_at' => null]);

                Log::warning('booking.balance_reminder.failed', [
                    'booking' => $booking->reference,
                    'message' => $e->getMessage(),
                ]);
                $this->error("Fallo al enviar a {$booking->customer_email} (ver log).");
            }
        }

        $this->info($dry
            ? "Simulación: {$bookings->count()} reserva(s)."
            : "Recordatorios de saldo enviados: {$sent}; omitidos: {$skipped}.");

        return self::SUCCESS;
    }
}
