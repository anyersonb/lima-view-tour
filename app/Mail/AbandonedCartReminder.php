<?php

namespace App\Mail;

use App\Models\AbandonedCart;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbandonedCartReminder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  AbandonedCart  $cart      Carrito abandonado a recuperar
     * @param  int            $reminder  Nº de recordatorio (1 = primero, 2 = segundo)
     */
    public function __construct(
        public readonly AbandonedCart $cart,
        public readonly int $reminder = 1,
    ) {}

    public function envelope(): Envelope
    {
        $locale = $this->cart->locale ?: 'es';

        $subject = match ($locale) {
            'en' => $this->reminder >= 2
                ? 'Your tour is waiting — complete your booking'
                : 'You left your tour in the cart 🧳',
            'pt' => $this->reminder >= 2
                ? 'Seu passeio está esperando — finalize sua reserva'
                : 'Você deixou seu passeio no carrinho 🧳',
            default => $this->reminder >= 2
                ? 'Tu tour te espera — completa tu reserva'
                : 'Dejaste tu tour en el carrito 🧳',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.carts.abandoned',
            with: [
                'cart'      => $this->cart,
                'locale'    => $this->cart->locale ?: 'es',
                'reminder'  => $this->reminder,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
