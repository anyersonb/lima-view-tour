<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NewsletterSubscriber $subscriber
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->subscriber->locale === 'en'
            ? 'Confirm your subscription — Lima View Tours'
            : 'Confirma tu suscripción — Lima View Tours';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $confirmUrl = route('newsletter.confirm', ['token' => $this->subscriber->confirmation_token]);

        return new Content(
            markdown: 'emails.newsletter.confirm',
            with: [
                'subscriber'  => $this->subscriber,
                'confirmUrl'  => $confirmUrl,
                'locale'      => $this->subscriber->locale,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
