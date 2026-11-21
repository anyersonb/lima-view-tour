<?php

namespace App\Services;

use App\Exceptions\BalanceLinkException;
use App\Mail\BookingBalanceLink;
use App\Mail\BookingBalancePaid;
use App\Models\Booking;
use App\Models\PaymentLink;
use App\Support\PaymentPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Cobro del SALDO de una reserva con adelanto (payment_status = partial).
 *
 * El admin (o el recordatorio automático) genera un PaymentLink con
 * purpose = 'balance' ligado a la reserva por booking_id. Pagar ese link NUNCA
 * crea una reserva nueva: marca la existente como pagada (settle()).
 *
 * El link vence 48 h antes de la salida del tour (PaymentPlan::
 * BALANCE_LINK_EXPIRES_HOURS_BEFORE): pasado ese punto el saldo se cobra a mano.
 */
class BalancePaymentService
{
    /** Límite de cobro del saldo: salida del tour - 48 h (null si la reserva no tiene fecha). */
    public function deadline(Booking $booking): ?Carbon
    {
        return $booking->balanceDeadline();
    }

    /** Link de saldo VIGENTE de la reserva (pendiente, sin vencer y con el monto actual), si existe. */
    public function activeLink(Booking $booking): ?PaymentLink
    {
        return PaymentLink::query()
            ->where('booking_id', $booking->id)
            ->where('purpose', 'balance')
            ->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->get()
            ->first(fn (PaymentLink $l) => PaymentPlan::toCents($l->amount) === PaymentPlan::toCents($booking->balance_due));
    }

    /**
     * Genera el link de pago del saldo (o reutiliza el vigente) y, si
     * $send, lo envía por correo al cliente.
     *
     * @throws BalanceLinkException si no hay saldo, es menor al mínimo o el
     *                              plazo (tour - 48 h) ya pasó
     */
    public function createLink(Booking $booking, ?int $createdBy = null, bool $send = true): PaymentLink
    {
        $link = DB::transaction(function () use ($booking, $createdBy): PaymentLink {
            // Lock de la reserva: dos clics / admin + cron no crean dos links.
            $fresh = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            if (! $fresh || ! $fresh->hasPendingBalance()) {
                throw new BalanceLinkException('Esta reserva no tiene un saldo pendiente por cobrar.');
            }

            $amount = PaymentPlan::fromCents(PaymentPlan::toCents($fresh->balance_due));

            if ($amount < PaymentLink::MIN_AMOUNT) {
                throw new BalanceLinkException('El saldo es menor a US$'.number_format(PaymentLink::MIN_AMOUNT, 2).': cóbralo directamente y márcalo como pagado.');
            }

            $deadline = $fresh->balanceDeadline();

            if ($deadline !== null && $deadline->lte(now())) {
                throw new BalanceLinkException(
                    'El tour sale en menos de '.PaymentPlan::BALANCE_LINK_EXPIRES_HOURS_BEFORE.' horas: el link de pago ya no puede generarse. Cobra el saldo directamente y márcalo como pagado.'
                );
            }

            $existing = $this->activeLink($fresh);

            if ($existing) {
                return $existing;
            }

            // Cualquier link de saldo anterior que siga abierto (monto viejo) se anula.
            PaymentLink::query()
                ->where('booking_id', $fresh->id)
                ->where('purpose', 'balance')
                ->where('status', 'pending')
                ->get()
                ->each(fn (PaymentLink $l) => $l->forceFill(['status' => 'cancelled'])->save());

            return PaymentLink::create([
                'purpose' => 'balance',
                'booking_id' => $fresh->id,
                'tour_id' => $fresh->tour_id,
                'amount' => $amount,
                'adults' => max(1, (int) $fresh->adults),
                'children' => (int) $fresh->children,
                'travel_date' => $fresh->travel_date?->toDateString(),
                'customer_name' => $fresh->customer_name,
                'customer_email' => $fresh->customer_email,
                'customer_phone' => $fresh->customer_phone,
                'note' => 'Saldo de la reserva '.$fresh->reference,
                'locale' => in_array($fresh->locale, ['es', 'en', 'pt'], true) ? $fresh->locale : 'es',
                // Vence 48 h antes del tour (= balance_due_at de la reserva).
                'expires_at' => $deadline?->copy()->utc(),
                'status' => 'pending',
                'created_by' => $createdBy,
            ]);
        });

        if ($send) {
            $this->sendLink($booking->fresh(), $link);
        }

        return $link;
    }

    /**
     * Envía por correo el link de saldo. Lanza si el correo falla (el panel
     * muestra el error real); el comando de recordatorios lo captura.
     *
     * @throws BalanceLinkException si la reserva no tiene correo
     */
    public function sendLink(Booking $booking, PaymentLink $link, bool $isReminder = false): void
    {
        if (! $booking->customer_email) {
            throw new BalanceLinkException('La reserva no tiene un correo de cliente.');
        }

        $booking->loadMissing('tour');

        Mail::to($booking->customer_email)
            ->locale($this->safeLocale($booking->locale))
            ->send(new BookingBalanceLink($booking, $link, $isReminder));

        Log::info('booking.balance_link.sent', [
            'booking' => $booking->reference,
            'payment_link_id' => $link->id,
            'reminder' => $isReminder,
            'by' => auth()->id(),
        ]);
    }

    /**
     * Registra el pago del saldo. Idempotente y a prueba de carreras: toma
     * lock de la reserva; si el saldo ya estaba pagado devuelve false sin
     * tocar nada.
     *
     * Si lo pagado cubre el saldo: payment_status = paid, balance_due = 0.
     * Si es menor (cobro parcial manual): sigue 'partial' con el saldo reducido.
     *
     * @param  string  $method  paypal | culqi | cash | transfer | ...
     * @return bool true = se registró AHORA; false = ya estaba registrado
     *
     * @throws BalanceLinkException si la reserva no está en un estado que admita el pago del saldo
     */
    public function settle(Booking $booking, string $method, string $reference, float $amount, ?PaymentLink $link = null): bool
    {
        return DB::transaction(function () use ($booking, $method, $reference, $amount, $link): bool {
            $fresh = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            if (! $fresh) {
                throw new BalanceLinkException('La reserva ya no existe.');
            }

            // Ya registrado (mismo cobro, doble clic, webhook tras el flujo síncrono).
            if ($fresh->balance_paid_at !== null || ($fresh->payment_status === 'paid' && (float) $fresh->balance_due <= 0)) {
                Log::info('booking.balance.settle_idempotent', ['booking' => $fresh->reference, 'reference' => $reference]);
                $this->closeBalanceLinks($fresh, $link);

                return false;
            }

            if ($fresh->payment_status !== 'partial') {
                throw new BalanceLinkException('La reserva '.$fresh->reference.' no tiene un saldo pendiente (estado: '.$fresh->payment_status.').');
            }

            $paidCents = PaymentPlan::toCents($amount);
            $balanceCents = PaymentPlan::toCents($fresh->balance_due);
            $remaining = max(0, $balanceCents - $paidCents);
            $settled = $remaining === 0;

            $fresh->forceFill([
                'amount_paid' => PaymentPlan::fromCents(PaymentPlan::toCents($fresh->amount_paid) + $paidCents),
                'balance_due' => PaymentPlan::fromCents($remaining),
                'payment_status' => $settled ? 'paid' : 'partial',
                'status' => $fresh->status === 'cancelled' ? 'cancelled' : 'confirmed',
                'balance_paid_at' => $settled ? now() : null,
                'balance_payment_method' => $method,
                'balance_payment_reference' => $reference,
            ])->save();

            if ($settled) {
                $this->closeBalanceLinks($fresh, $link);
            }

            Log::info('booking.balance.settled', [
                'booking' => $fresh->reference,
                'method' => $method,
                'reference' => $reference,
                'amount' => PaymentPlan::fromCents($paidCents),
                'remaining' => PaymentPlan::fromCents($remaining),
            ]);

            // Reflejar en el objeto del llamador (notificaciones, sesión).
            $booking->setRawAttributes($fresh->getAttributes(), true);

            return true;
        });
    }

    /** Cobro manual (efectivo / transferencia / Yape): el admin lo registra desde el panel. */
    public function markPaidManually(Booking $booking, string $method, string $reference): bool
    {
        return $this->settle($booking, $method, $reference, (float) $booking->balance_due, null);
    }

    /** Recibo al cliente + aviso interno. Best-effort: un fallo de correo no revierte el pago. */
    public function notifyBalancePaid(Booking $booking): void
    {
        $booking->loadMissing('tour');

        if ($booking->customer_email) {
            try {
                Mail::to($booking->customer_email)
                    ->locale($this->safeLocale($booking->locale))
                    ->send(new BookingBalancePaid($booking));
            } catch (\Throwable $e) {
                Log::warning('booking.balance_paid.email_failed', ['booking' => $booking->reference, 'message' => $e->getMessage()]);
            }
        }

        try {
            $recipients = app(BookingNotifier::class)->adminRecipients();

            if ($recipients !== []) {
                Mail::raw(
                    "Saldo cobrado de la reserva {$booking->reference} ({$booking->customer_name}): US$"
                    .number_format((float) $booking->amount_paid, 2)." pagados en total, vía {$booking->balance_payment_method}"
                    ." (ref. {$booking->balance_payment_reference}). La reserva quedó PAGADA.",
                    fn ($m) => $m->to($recipients)->subject("[Saldo pagado] {$booking->reference} — Lima View Tours")
                );
            }
        } catch (\Throwable $e) {
            Log::warning('booking.balance_paid.admin_email_failed', ['booking' => $booking->reference, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Cierra los links de saldo de una reserva ya saldada: cualquier otro link
     * pendiente se anula (no se puede pagar dos veces el mismo saldo). El link
     * que se pagó lo marca 'paid' el flujo de captura.
     */
    private function closeBalanceLinks(Booking $booking, ?PaymentLink $paid): void
    {
        PaymentLink::query()
            ->where('booking_id', $booking->id)
            ->where('purpose', 'balance')
            ->where('status', 'pending')
            ->when($paid, fn ($q) => $q->where('id', '!=', $paid->id))
            ->get()
            ->each(fn (PaymentLink $l) => $l->forceFill(['status' => 'cancelled'])->save());
    }

    private function safeLocale(?string $locale): string
    {
        return in_array($locale, config('app.supported_locales', ['es', 'en', 'pt']), true) ? $locale : 'es';
    }
}
