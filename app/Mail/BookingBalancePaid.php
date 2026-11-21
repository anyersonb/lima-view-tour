<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Recibo al cliente cuando el saldo de su reserva queda pagado (reserva 100% pagada). */
class BookingBalancePaid extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Booking $booking) {}

    public function envelope(): Envelope
    {
        $locale = $this->booking->locale ?? app()->getLocale();

        $subject = match ($locale) {
            'en' => 'Balance received — booking '.$this->booking->reference.' paid in full (Lima View Tours)',
            'pt' => 'Saldo recebido — reserva '.$this->booking->reference.' paga integralmente (Lima View Tours)',
            default => 'Saldo recibido — reserva '.$this->booking->reference.' pagada al 100% (Lima View Tours)',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bookings.balance-paid',
            with: [
                'booking' => $this->booking,
                'locale' => $this->booking->locale ?? app()->getLocale(),
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
