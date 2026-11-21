<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\PaymentLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo al cliente con el link de pago del SALDO de su reserva (adelanto del
 * 50% ya cobrado). Lo envía el admin desde el panel o el recordatorio
 * automático (3 días antes del tour). El link vence 48 h antes de la salida.
 */
class BookingBalanceLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly PaymentLink $link,
        public readonly bool $isReminder = false,
    ) {}

    public function envelope(): Envelope
    {
        $locale = $this->booking->locale ?? app()->getLocale();

        $subject = match ($locale) {
            'en' => $this->isReminder
                ? 'Reminder: pay the balance of your booking '.$this->booking->reference.' (Lima View Tours)'
                : 'Pay the balance of your booking '.$this->booking->reference.' (Lima View Tours)',
            'pt' => $this->isReminder
                ? 'Lembrete: pague o saldo da sua reserva '.$this->booking->reference.' (Lima View Tours)'
                : 'Pague o saldo da sua reserva '.$this->booking->reference.' (Lima View Tours)',
            default => $this->isReminder
                ? 'Recordatorio: paga el saldo de tu reserva '.$this->booking->reference.' (Lima View Tours)'
                : 'Paga el saldo de tu reserva '.$this->booking->reference.' (Lima View Tours)',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bookings.balance-link',
            with: [
                'booking' => $this->booking,
                'link' => $this->link,
                'isReminder' => $this->isReminder,
                'locale' => $this->booking->locale ?? app()->getLocale(),
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
