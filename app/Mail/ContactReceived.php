<?php

namespace App\Mail;

use App\Models\ContactLead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ContactLead $lead
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Nuevo mensaje de contacto — {$this->lead->name}"
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contact.received',
            with: ['lead' => $this->lead]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
