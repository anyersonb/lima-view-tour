<?php

namespace App\Services;

use App\Mail\BookingConfirmed;
use App\Mail\BookingNotificationAdmin;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Envía los correos asociados a una reserva:
 *   - Confirmación al cliente (BookingConfirmed)
 *   - Notificación interna al administrador (BookingNotificationAdmin)
 *
 * Reutilizable tanto desde el checkout público (CheckoutController) como
 * desde el panel admin (Filament CreateBooking). Ambos envíos son
 * "best-effort": si uno falla se registra un warning pero no se interrumpe
 * el flujo de creación de la reserva.
 */
class BookingNotifier
{
    /**
     * @param  Collection  $bookings  Colección de modelos Booking (1 o varios)
     * @param  bool         $paid      true = pagado/confirmado ("now"); false = pendiente ("later")
     * @param  string|null  $customerEmail  Email destino del cliente; si es null se toma de la 1ª reserva
     */
    public function send(Collection $bookings, bool $paid, ?string $customerEmail = null): void
    {
        if ($bookings->isEmpty()) {
            return;
        }

        $customerEmail ??= $bookings->first()?->customer_email;

        $this->sendCustomerConfirmation($bookings, $customerEmail);
        $this->sendAdminNotification($bookings, $paid);
    }

    private function sendCustomerConfirmation(Collection $bookings, ?string $customerEmail): void
    {
        if (! $customerEmail) {
            Log::warning('booking_notifier.confirmation_email.skipped_no_email', [
                'bookings' => $bookings->pluck('reference')->all(),
            ]);
            return;
        }

        try {
            Mail::to($customerEmail)
                ->send(new BookingConfirmed($bookings, $customerEmail));

            Log::info('booking_notifier.confirmation_email.sent', [
                'email'    => $customerEmail,
                'bookings' => $bookings->pluck('reference')->all(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('booking_notifier.confirmation_email.failed', [
                'email'   => $customerEmail,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function sendAdminNotification(Collection $bookings, bool $paid): void
    {
        try {
            $adminEmail = Setting::get('booking_notification_email')
                ?: config('mail.from.address');

            $paymentTiming = $paid ? 'now' : 'later';

            Mail::to($adminEmail)
                ->send(new BookingNotificationAdmin($bookings, $paymentTiming));

            Log::info('booking_notifier.admin_notification_email.sent', [
                'admin_email' => $adminEmail,
                'bookings'    => $bookings->pluck('reference')->all(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('booking_notifier.admin_notification_email.failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
