<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Recordatorio de pago para reservas "pagar luego" pendientes.
 *
 * Se envía X días antes de la fecha del tour (config cart.payment_reminder)
 * a las reservas con payment_method = 'pay_later' y payment_status = 'pending'.
 *
 * @param Collection $bookings Reservas pendientes del mismo cliente para la
 *                             misma fecha de viaje.
 */
class BookingPaymentReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Collection $bookings,
        public readonly string $toEmail
    ) {}

    public function envelope(): Envelope
    {
        $locale = $this->bookings->first()?->locale ?? app()->getLocale();

        $subject = $locale === 'en'
            ? 'Payment reminder — complete your Lima View Tours booking'
            : 'Recordatorio de pago — completa tu reserva de Lima View Tours';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bookings.payment-reminder',
            with: [
                'bookings' => $this->bookings,
                'locale'   => $this->bookings->first()?->locale ?? app()->getLocale(),
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
