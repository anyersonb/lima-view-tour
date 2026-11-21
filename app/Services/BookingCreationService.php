<?php

namespace App\Services;

use App\Mail\AccountCredentials;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Tour;
use App\Support\BookingCalendar;
use App\Support\PaymentPlan;
use App\Support\TourFlags;
use App\Support\TourLanguage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Núcleo compartido de "crear fila(s) de Booking para una compra": extraído
 * de CheckoutController::finalizeBookings() el 2026-09-24 al construir el
 * módulo "Links de pago" (ver docs/payment-links/INFORME.md), para que
 * PaymentLinkController pueda crear una reserva con el MISMO contrato de
 * datos que el checkout normal (customer_id, snapshot de precios, columnas
 * de recojo condicionales, cuenta de invitado) sin duplicar esa lógica.
 *
 * Deliberadamente NO toca el carrito ni el carrito abandonado — son
 * conceptos exclusivos del checkout y siguen viviendo en CheckoutController,
 * que llama a este servicio y luego hace su propio cart->clear() /
 * abandoned->markConverted(). Tampoco envía el correo de confirmación: cada
 * llamador decide cuándo (CheckoutController ya lo hacía fuera de este
 * método; PaymentLinkController hace lo mismo).
 */
class BookingCreationService
{
    /**
     * @param  Collection  $items  Uno o varios ítems: tour_id, title_snapshot,
     *                             travel_date, adults, children, unit_price,
     *                             subtotal.
     * @param  array  $customer  Keys: customer_name, customer_email,
     *                           customer_phone, travel_date (fallback),
     *                           pickup_point, pickup_detail, notes.
     * @param  string  $method  'paypal' | 'culqi' | 'pay_later' | ...
     * @param  array|null  $plan  Resultado de PaymentPlan::split() (mode, total_cents,
     *                            due_cents, ...). null = comportamiento histórico:
     *                            $paid ? full : later. Con modo deposit el estado queda
     *                            confirmed/partial y cada reserva guarda su parte
     *                            cobrada y su saldo (reparto proporcional exacto).
     * @return Collection<Booking>
     */
    public function createBookings(
        Collection $items,
        array $customer,
        string $method,
        ?string $paymentReference,
        bool $paid,
        string $locale,
        ?array $plan = null,
    ): Collection {
        $hasPickupColumns = Schema::hasColumn('bookings', 'pickup_point');
        // Tours without pickup never store a pickup point, even if the
        // client posted one (hidden field tampering / stale form).
        // Column missing (migration pending) => every tour has pickup.
        $noPickupTourIds = TourFlags::has('pickup_included')
            ? Tour::query()
                ->whereIn('id', $items->pluck('tour_id')->filter()->unique()->all())
                ->where('pickup_included', false)
                ->pluck('id')
                ->all()
            : [];
        // Language the customer takes the tour in: code es|en|pt, default = the
        // site language of the request. Column missing (migration pending) => not stored.
        $tourLanguage = TourLanguage::hasColumn()
            ? TourLanguage::resolve($customer['tour_language'] ?? null, $locale)
            : null;
        $pendingGuestMail = null;

        // Plan de pago: modo + reparto por reserva (solo si las columnas existen).
        $planColumns = self::hasPlanColumns();
        $mode = $plan['mode'] ?? ($paid ? PaymentPlan::FULL : PaymentPlan::LATER);
        $itemsList = $items->values();
        $totalCents = (int) ($plan['total_cents'] ?? $itemsList->sum(fn (array $i) => PaymentPlan::toCents($i['subtotal'] ?? 0)));
        $dueCents = (int) ($plan['due_cents'] ?? ($paid ? $totalCents : 0));
        $allocation = PaymentPlan::allocate($itemsList, $totalCents, $dueCents);
        $tours = $planColumns
            ? Tour::query()->whereIn('id', $items->pluck('tour_id')->filter()->unique()->all())->get(['id', 'departure_time'])->keyBy('id')
            : collect();

        $bookings = DB::transaction(function () use (
            $itemsList, $customer, $method, $paymentReference, $paid, $locale, $hasPickupColumns, $noPickupTourIds, $tourLanguage, &$pendingGuestMail,
            $planColumns, $mode, $allocation, $tours
        ): Collection {
            $customerId = $this->resolveCustomerId($customer, $locale, $pendingGuestMail);

            return $itemsList->map(function (array $item, int $idx) use (
                $customer, $method, $paymentReference, $paid, $locale, $hasPickupColumns, $noPickupTourIds, $tourLanguage, $customerId,
                $planColumns, $mode, $allocation, $tours
            ): Booking {
                $attrs = [
                    'customer_id' => $customerId,
                    'tour_id' => $item['tour_id'],
                    'tour_title_snapshot' => $item['title_snapshot'],
                    'customer_name' => $customer['customer_name'],
                    'customer_email' => $customer['customer_email'],
                    'customer_phone' => $customer['customer_phone'],
                    'travel_date' => $item['travel_date'] ?? $customer['travel_date'],
                    'adults' => $item['adults'],
                    'children' => $item['children'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['subtotal'],
                    'currency' => 'USD',
                    'status' => $paid ? 'confirmed' : 'pending',
                    'payment_status' => ! $paid ? 'pending' : ($mode === PaymentPlan::DEPOSIT ? 'partial' : 'paid'),
                    'payment_method' => $method,
                    'payment_reference' => $paymentReference,
                    'locale' => $locale,
                    // B3: 'bookings.notes' existe desde la migración original
                    // (create_bookings_table), a diferencia de pickup_point/
                    // pickup_detail (agregadas después), así que se agrega
                    // directo sin el guard de Schema::hasColumn() de abajo.
                    'notes' => $customer['notes'] ?? null,
                ];

                if ($tourLanguage !== null) {
                    $attrs['tour_language'] = $tourLanguage;
                }

                if ($planColumns) {
                    $row = $allocation[$idx] ?? ['paid_cents' => 0, 'balance_cents' => 0];
                    $attrs['payment_plan'] = $mode;
                    $attrs['amount_paid'] = PaymentPlan::fromCents($row['paid_cents']);
                    $attrs['balance_due'] = PaymentPlan::fromCents($row['balance_cents']);

                    // Límite del saldo: el mismo instante en que vence su link (tour - 48 h).
                    if ($mode === PaymentPlan::DEPOSIT && $row['balance_cents'] > 0) {
                        $travelDate = $attrs['travel_date'] ?? null;
                        $attrs['balance_due_at'] = $travelDate
                            ? BookingCalendar::departureAt((string) $travelDate, $tours->get($item['tour_id'] ?? null))
                                ->subHours(PaymentPlan::BALANCE_LINK_EXPIRES_HOURS_BEFORE)
                                ->utc() // las columnas timestamp se guardan en UTC (app.timezone)
                            : null;
                    }
                }

                if ($hasPickupColumns) {
                    $hasPickup = ! in_array($item['tour_id'], $noPickupTourIds, true);
                    $attrs['pickup_point'] = $hasPickup ? ($customer['pickup_point'] ?? null) : null;
                    $attrs['pickup_detail'] = $hasPickup ? ($customer['pickup_detail'] ?? null) : null;
                }

                return Booking::create($attrs);
            });
        });

        // N-3 (docs/payment-links/SECURITY.md): DB::afterCommit(), no una
        // llamada directa. createBookings() corre ANIDADO dentro de la
        // transacción con lockForUpdate() de PaymentLinkController/
        // WebhookController — su propio DB::transaction() de arriba solo
        // libera un SAVEPOINT ahí, no hace un commit real todavía. Una
        // llamada directa acá mandaría el correo de credenciales ANTES de
        // que el commit de verdad ocurra; si la transacción externa revierte
        // después (p.ej. el forceFill() de booking_id falla), el correo
        // habría llegado para una cuenta que nunca existió. DB::afterCommit()
        // encola el envío hasta el commit REAL más externo, y lo descarta
        // solo si esa transacción termina en rollback. En el checkout normal
        // (CheckoutController no abre transacción propia, así que esta ES la
        // más externa) el envío ocurre en el mismo instante que antes.
        if ($pendingGuestMail !== null) {
            DB::afterCommit(function () use ($pendingGuestMail) {
                $this->sendGuestCredentialsMail(
                    $pendingGuestMail['customer'],
                    $pendingGuestMail['plain'],
                    $pendingGuestMail['locale'],
                );
            });
        }

        return $bookings;
    }

    /**
     * ¿Ya corrió la migración de payment_plan? Un despliegue de código antes
     * que la migración no debe romper el checkout (mismo criterio que
     * pickup_point / tour_language).
     */
    public static function hasPlanColumns(): bool
    {
        static $has = null;

        // Solo `true` es estable: un `false` se reevalúa (tests / migración en caliente).
        if ($has !== true) {
            $has = Schema::hasColumn('bookings', 'payment_plan');
        }

        return $has;
    }

    /**
     * Returns the customer_id to attach to new bookings.
     * - Logged-in customers: use their existing id.
     * - Existing email (no session): reuse without sending any email.
     * - New email: create an account with a generated temporary password.
     *   The credentials email is NOT sent here — this method runs inside
     *   createBookings()'s DB::transaction(), and a rollback can't un-send
     *   an email. Instead, it fills $pendingGuestMail by reference so the
     *   caller can send it AFTER the transaction commits.
     *
     * @param  array{customer: Customer, plain: string, locale: string}|null  $pendingGuestMail
     */
    private function resolveCustomerId(array $customer, string $locale, ?array &$pendingGuestMail = null): int
    {
        $loggedIn = auth('customer')->user();

        if ($loggedIn) {
            return $loggedIn->id;
        }

        $existing = Customer::where('email', $customer['customer_email'])->first();

        if ($existing) {
            return $existing->id;
        }

        // Generate a readable temporary password (10 chars, no symbols, no ambiguous chars).
        $plain = Str::password(10, letters: true, numbers: true, symbols: false, spaces: false);

        $guestCustomer = Customer::create([
            'name' => $customer['customer_name'],
            'email' => $customer['customer_email'],
            'phone' => $customer['customer_phone'] ?? null,
            'locale' => $locale,
            'password' => $plain,
        ]);

        $pendingGuestMail = [
            'customer' => $guestCustomer,
            'plain' => $plain,
            'locale' => $locale,
        ];

        return $guestCustomer->id;
    }

    /**
     * Sends the guest account credentials email. Must be called AFTER
     * createBookings()'s DB::transaction() has committed. Failure is
     * non-fatal: log a warning and continue.
     */
    private function sendGuestCredentialsMail(Customer $guestCustomer, string $plain, string $locale): void
    {
        try {
            Mail::to($guestCustomer->email)
                ->send(new AccountCredentials($guestCustomer, $plain, $locale));
        } catch (\Throwable $ex) {
            Log::warning('booking_creation.guest_credentials_email.failed', [
                'email' => $guestCustomer->email,
                'message' => $ex->getMessage(),
            ]);
        }
    }
}
