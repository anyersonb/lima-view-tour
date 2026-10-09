<?php

namespace App\Services;

use App\Mail\BookingConfirmed;
use App\Mail\BookingNotificationAdmin;
use App\Mail\BookingPaymentReminder;
use App\Models\Booking;
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
     * @param  bool  $paid  true = pagado/confirmado ("now"); false = pendiente ("later")
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

    /**
     * Reenvía manualmente la confirmación al cliente (usado por el botón
     * "Reenviar correo" del panel de Reservas). Lanza excepción si falla,
     * para que la acción del panel muestre el error real.
     *
     * @param  Collection  $bookings  Reservas a incluir en el correo
     * @param  string|null  $customerEmail  Destino; por defecto el de la 1ª reserva
     */
    public function resendCustomerConfirmation(Collection $bookings, ?string $customerEmail = null): void
    {
        if ($bookings->isEmpty()) {
            throw new \RuntimeException('No hay reservas para enviar.');
        }

        $customerEmail ??= $bookings->first()?->customer_email;

        if (! $customerEmail) {
            throw new \RuntimeException('La reserva no tiene un correo de cliente.');
        }

        // Idioma de la RESERVA, no el del panel (el admin opera en es).
        Mail::to($customerEmail)
            ->locale($this->safeLocale($bookings->first()?->locale))
            ->send(new BookingConfirmed($bookings, $customerEmail));

        Log::info('booking_notifier.confirmation_email.resent', [
            'by' => auth()->id(),
            'email' => $customerEmail,
            'customer_email_original' => $bookings->first()?->customer_email,
            'bookings' => $bookings->pluck('reference')->all(),
        ]);
    }

    /**
     * Reenvío manual del recordatorio de pago de UNA reserva (no agrupa por
     * email+fecha como el comando automático). Marca payment_reminder_sent_at
     * para que la tanda automática no lo duplique. Lanza si el envío falla
     * (y en ese caso NO marca). El mailable no es ShouldQueue: sale síncrono.
     */
    public function resendPaymentReminder(Booking $booking, ?string $customerEmail = null): void
    {
        $customerEmail ??= $booking->customer_email;

        if (! $customerEmail) {
            throw new \RuntimeException('La reserva no tiene un correo de cliente.');
        }

        Mail::to($customerEmail)
            ->locale($this->safeLocale($booking->locale))
            ->send(new BookingPaymentReminder(collect([$booking]), $customerEmail));

        // Solo cuenta como "recordado" si fue al correo REAL del cliente; un envío
        // de prueba/otra dirección no debe apagar la tanda automática.
        $toCustomer = strcasecmp(trim($customerEmail), trim((string) $booking->customer_email)) === 0;

        if ($toCustomer) {
            Booking::whereKey($booking->getKey())->update(['payment_reminder_sent_at' => now()]);
        }

        Log::info('booking_notifier.payment_reminder.resent', [
            'by' => auth()->id(),
            'email' => $customerEmail,
            'customer_email_original' => $booking->customer_email,
            'booking' => $booking->reference,
            'marked_sent' => $toCustomer,
        ]);
    }

    /** Locale de la reserva solo si está en la allowlist; si no, español. */
    private function safeLocale(?string $locale): string
    {
        return in_array($locale, config('app.supported_locales', ['es', 'en', 'pt']), true) ? $locale : 'es';
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
                'email' => $customerEmail,
                'bookings' => $bookings->pluck('reference')->all(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('booking_notifier.confirmation_email.failed', [
                'email' => $customerEmail,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function sendAdminNotification(Collection $bookings, bool $paid): void
    {
        try {
            $recipients = $this->adminRecipients();

            if (empty($recipients)) {
                Log::warning('booking_notifier.admin_notification_email.skipped_no_recipients', [
                    'bookings' => $bookings->pluck('reference')->all(),
                ]);

                return;
            }

            $paymentTiming = $paid ? 'now' : 'later';

            Mail::to($recipients)
                ->send(new BookingNotificationAdmin($bookings, $paymentTiming));

            Log::info('booking_notifier.admin_notification_email.sent', [
                'admin_email' => $recipients,
                'bookings' => $bookings->pluck('reference')->all(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('booking_notifier.admin_notification_email.failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Lista de correos que reciben el aviso interno de reserva.
     *
     * El campo `booking_notification_email` del CMS admite VARIOS correos
     * separados por coma, punto y coma, espacio o salto de línea. Se validan
     * uno a uno y se descartan duplicados. Si el CMS no tiene ninguno válido
     * se cae al remitente configurado en el servidor (config('mail.from.address')).
     *
     * Público (no solo usado internamente): PaymentLinkController lo reutiliza
     * para avisar al admin de un cobro capturado en PayPal cuya reserva no se
     * pudo crear (ver payment_link.capture.db_write_failed_after_paypal_capture),
     * sin duplicar la lógica de resolución de destinatarios.
     *
     * @return string[]
     */
    public function adminRecipients(): array
    {
        $raw = (string) Setting::get('booking_notification_email');

        $emails = collect(preg_split('/[,;\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $e) => trim($e))
            ->filter(fn (string $e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();

        if (empty($emails)) {
            $fallback = config('mail.from.address');

            return $fallback ? [$fallback] : [];
        }

        return $emails;
    }
}
