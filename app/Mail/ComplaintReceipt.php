<?php

namespace App\Mail;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Copia de la hoja de reclamación para el consumidor (exigencia INDECOPI). */
class ComplaintReceipt extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Complaint $complaint) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->complaint->locale) {
            'en' => 'Your complaint book sheet '.$this->complaint->code.' — Lima View Tours',
            'pt' => 'Sua folha do livro de reclamações '.$this->complaint->code.' — Lima View Tours',
            default => 'Tu hoja de reclamación '.$this->complaint->code.' — Lima View Tours',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.complaints.receipt',
            with: ['complaint' => $this->complaint, 'locale' => $this->complaint->locale],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
