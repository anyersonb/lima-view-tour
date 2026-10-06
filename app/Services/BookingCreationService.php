<?php

namespace App\Services;

use App\Mail\AccountCredentials;
use App\Models\Booking;
use App\Models\Customer;
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
     * @param  string  $method  'paypal' | 'pay_later' | ...
     * @return Collection<Booking>
     */
    public function createBookings(
        Collection $items,
        array $customer,
        string $method,
        ?string $paymentReference,
        bool $paid,
        string $locale,
    ): Collection {
        $hasPickupColumns = Schema::hasColumn('bookings', 'pickup_point');
        $pendingGuestMail = null;

        $bookings = DB::transaction(function () use (
            $items, $customer, $method, $paymentReference, $paid, $locale, $hasPickupColumns, &$pendingGuestMail
        ): Collection {
            $customerId = $this->resolveCustomerId($customer, $locale, $pendingGuestMail);

            return $items->map(function (array $item) use (
                $customer, $method, $paymentReference, $paid, $locale, $hasPickupColumns, $customerId
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
                    'payment_status' => $paid ? 'paid' : 'pending',
                    'payment_method' => $method,
                    'payment_reference' => $paymentReference,
                    'locale' => $locale,
                    // B3: 'bookings.notes' existe desde la migración original
                    // (create_bookings_table), a diferencia de pickup_point/
                    // pickup_detail (agregadas después), así que se agrega
                    // directo sin el guard de Schema::hasColumn() de abajo.
                    'notes' => $customer['notes'] ?? null,
                ];

                if ($hasPickupColumns) {
                    $attrs['pickup_point'] = $customer['pickup_point'] ?? null;
                    $attrs['pickup_detail'] = $customer['pickup_detail'] ?? null;
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
