<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountCredentials extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Customer $customer  Newly created guest customer
     * @param string   $password  Plain-text temporary password (shown once in this email)
     * @param string   $locale    es | en | pt
     */
    /**
     * NOTA: el parámetro de idioma se llama $mailLocale (no $locale) porque
     * Illuminate\Mail\Mailable ya define $locale y no se puede redeclarar readonly.
     */
    public function __construct(
        public readonly Customer $customer,
        public readonly string $password,
        public readonly string $mailLocale
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->mailLocale) {
            'en'    => 'Your Lima View Tours account',
            'pt'    => 'Sua conta na Lima View Tours',
            default => 'Tu cuenta en Lima View Tours',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-credentials',
            with: [
                'customer' => $this->customer,
                'password' => $this->password,
                'locale'   => $this->mailLocale,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
