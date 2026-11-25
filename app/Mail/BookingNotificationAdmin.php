<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class BookingNotificationAdmin extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Collection $bookings     Collection of Booking model instances
     * @param string     $paymentTiming  'now' | 'later'
     */
    public function __construct(
        public readonly Collection $bookings,
        public readonly string $paymentTiming
    ) {}

    public function envelope(): Envelope
    {
        $references = $this->bookings->pluck('reference')->implode(', ');
        $subject    = "[Nueva reserva] {$references} — Lima View Tours";

        // Reserva para el mismo día: el operador tiene que verla ANTES que el resto.
        if ($this->bookings->contains(fn ($b) => (bool) ($b->same_day ?? false))) {
            $subject = __('booking.same_day_mail_prefix', [], 'es').' '.$subject;
        }


        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bookings.admin-notification',
            with: [
                'bookings'      => $this->bookings,
                'paymentTiming' => $this->paymentTiming,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
