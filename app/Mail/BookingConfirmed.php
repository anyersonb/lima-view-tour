<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class BookingConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Collection $bookings  Collection of Booking model instances
     * @param string     $toEmail   Customer email address
     */
    public function __construct(
        public readonly Collection $bookings,
        public readonly string $toEmail
    ) {}

    public function envelope(): Envelope
    {
        // Detect locale from the first booking; fallback to app locale
        $locale = $this->bookings->first()?->locale ?? app()->getLocale();

        $subject = match ($locale) {
            'en'    => 'Booking Confirmation — Lima View Tours',
            'pt'    => 'Confirmação de reserva — Lima View Tours',
            default => 'Confirmación de reserva — Lima View Tours',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bookings.confirmed',
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
