<?php

namespace App\Mail;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso interno: hay un reclamo/queja nuevo (plazo legal de 15 días hábiles). */
class ComplaintReceivedAdmin extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Complaint $complaint) {}

    public function envelope(): Envelope
    {
        $kind = $this->complaint->type === Complaint::TYPE_COMPLAINT ? 'Queja' : 'Reclamo';

        return new Envelope(subject: "[Libro de Reclamaciones] {$kind} {$this->complaint->code}");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.complaints.admin',
            with: ['complaint' => $this->complaint],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
