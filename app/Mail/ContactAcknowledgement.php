<?php

namespace App\Mail;

use App\Models\ContactLead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactAcknowledgement extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ContactLead $lead
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->lead->locale === 'en'
            ? 'We received your message — Lima View Tours'
            : 'Recibimos tu mensaje — Lima View Tours';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contact.acknowledgement',
            with: [
                'lead'   => $this->lead,
                'locale' => $this->lead->locale,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
