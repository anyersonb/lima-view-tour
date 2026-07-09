<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcessPaymentRequest;
use App\Models\BlockedDate;
use App\Models\Customer;
use App\Models\Booking;
use App\Services\AbandonedCartService;
use App\Services\BookingNotifier;
use App\Services\CartService;
use App\Services\PaymentService;
use App\Services\PayPalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\AccountCredentials;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService          $cart,
        private readonly PaymentService       $payment,
        private readonly PayPalService        $paypal,
        private readonly BookingNotifier      $notifier,
        private readonly AbandonedCartService $abandoned,
    ) {}

    /**
     * The payment form now lives inside the cart page (3-step flow).
     * Redirect to cart.index, optionally jumping to the payment step via hash.
     */
    public function showPaymentForm(Request $request): View|RedirectResponse
    {
        $locale = app()->getLocale();

        try {
            $items = $this->cart->items();

            if ($items->isEmpty()) {
                return redirect()
                    ->route('cart.index', ['locale' => $locale])
                    ->with('error', __('cart.empty_checkout_redirect'));
            }

            return redirect()
                ->route('cart.index', ['locale' => $locale])
                ->with('open_step', 'pago');

        } catch (\Throwable $e) {
            Log::error('checkout.show_payment_form.error', ['message' => $e->getMessage()]);

            return redirect()
                ->route('cart.index', ['locale' => $locale])
                ->with('error', 'Ocurrió un error al cargar el formulario de pago.');
        }
    }

    /**
     * Process a "pay later" booking: creates pending bookings, sends emails,
     * clears the cart and redirects to the thanks page.
     *
     * The "pay now" flow is handled by paypalCreateOrder + paypalCaptureOrder.
     */
    public function processPayment(ProcessPaymentRequest $request): RedirectResponse
    {
        $locale = app()->getLocale();

        try {
            $items = $this->cart->items();

            if ($items->isEmpty()) {
                return redirect()
                    ->route('cart.index', ['locale' => $locale])
                    ->with('error', __('cart.empty_checkout_redirect'));
            }

            $validated   = $request->validated();
            $payingNow   = ($validated['payment_timing'] === 'now');

            // "Pay now" via the old form submission is no longer supported.
            // The PayPal JS flow handles "now"; only "later" should reach here.
            if ($payingNow) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'El pago inmediato debe realizarse a través del botón de PayPal.');
            }

            $customer = [
                'customer_name'  => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'],
                'travel_date'    => $validated['travel_date'],
                'pickup_point'   => $request->input('pickup_point'),
                'pickup_detail'  => $request->input('pickup_detail'),
            ];

            // Defense-in-depth: re-verify blocked dates for every cart item
            $travelDate = $validated['travel_date'];
            foreach ($items as $item) {
                if (BlockedDate::isBlocked($travelDate, $item['tour_id'] ?? null)) {
                    Log::info('checkout.process_payment: blocked date rejected', [
                        'travel_date' => $travelDate,
                        'tour_id'     => $item['tour_id'] ?? null,
                    ]);

                    return redirect()
                        ->route('cart.index', ['locale' => $locale])
                        ->with('error', __('booking.date_blocked'));
                }
            }

            $bookings = $this->finalizeBookings($customer, 'pay_later', null, false);

            $request->session()->put('last_bookings', $bookings->toArray());

            return redirect()->route('checkout.thanks', ['locale' => $locale]);

        } catch (\Throwable $e) {
            Log::error('checkout.process_payment.error', [
                'message' => $e->getMessage(),
                'email'   => $request->input('customer_email'),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No pudimos procesar la reserva. Por favor inténtalo de nuevo o contáctanos.');
        }
    }

    /**
     * Creates a PayPal order for the current cart total.
     * Returns JSON with the PayPal order ID for the JS SDK.
     */
    public function paypalCreateOrder(Request $request, string $locale): JsonResponse
    {
        try {
            $items = $this->cart->items();

            if ($items->isEmpty()) {
                return response()->json(['error' => 'El carrito está vacío.'], 422);
            }

            // Amount always calculated server-side — never trust the client
            $total = $this->cart->total();

            $order = $this->paypal->createOrder($total, 'USD', [
                'locale' => $locale,
            ]);

            return response()->json(['id' => $order['id']]);

        } catch (\Throwable $e) {
            Log::error('checkout.paypal_create_order.error', ['message' => $e->getMessage()]);

            return response()->json(['error' => 'No se pudo iniciar el pago. Inténtalo de nuevo.'], 500);
        }
    }

    /**
     * Captures a PayPal order approved by the buyer.
     * On success, finalizes the bookings and returns a redirect URL.
     */
    public function paypalCaptureOrder(Request $request, string $locale): JsonResponse
    {
        $validated = $request->validate([
            'orderID'        => ['required', 'string', 'max:100'],
            'customer_name'  => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'regex:/^\+?\d{7,15}$/'],
            'travel_date'    => ['required', 'date', 'after:today'],
            'pickup_point'   => ['nullable', 'string', 'max:100'],
            'pickup_detail'  => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $items = $this->cart->items();

            if ($items->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'El carrito está vacío.'], 422);
            }

            // Defense-in-depth: re-verify blocked dates before capturing payment
            $travelDate = $validated['travel_date'];
            foreach ($items as $item) {
                if (BlockedDate::isBlocked($travelDate, $item['tour_id'] ?? null)) {
                    Log::info('checkout.paypal_capture: blocked date rejected', [
                        'travel_date' => $travelDate,
                        'tour_id'     => $item['tour_id'] ?? null,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => __('booking.date_blocked'),
                    ], 422);
                }
            }

            $captureResponse = $this->paypal->captureOrder($validated['orderID']);
            $captureId       = $this->paypal->captureId($captureResponse);

            $customer = [
                'customer_name'  => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'],
                'travel_date'    => $validated['travel_date'],
                'pickup_point'   => $validated['pickup_point'] ?? null,
                'pickup_detail'  => $validated['pickup_detail'] ?? null,
            ];

            $bookings = $this->finalizeBookings($customer, 'paypal', $captureId, true);

            $request->session()->put('last_bookings', $bookings->toArray());

            return response()->json([
                'success'  => true,
                'redirect' => route('checkout.thanks', ['locale' => $locale]),
            ]);

        } catch (\Throwable $e) {
            Log::error('checkout.paypal_capture_order.error', [
                'message'  => $e->getMessage(),
                'order_id' => $validated['orderID'] ?? null,
                'email'    => $validated['customer_email'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'El pago no pudo completarse. Por favor inténtalo de nuevo o contáctanos.',
            ], 500);
        }
    }

    /**
     * Show the thank-you page after a successful payment.
     */
    public function thanks(Request $request): View
    {
        $bookings = collect($request->session()->get('last_bookings', []));

        return view('checkout.thanks', [
            'bookings' => $bookings,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Creates one Booking per cart item, sends confirmation emails,
     * clears the cart and returns the collection of created bookings.
     *
     * @param  array       $customer         Keys: customer_name, customer_email,
     *                                       customer_phone, travel_date,
     *                                       pickup_point, pickup_detail
     * @param  string      $method           'paypal' | 'pay_later'
     * @param  string|null $paymentReference PayPal capture ID (null for pay_later)
     * @param  bool        $paid             true = mark as paid & confirmed
     * @return Collection<Booking>
     */
    private function finalizeBookings(
        array   $customer,
        string  $method,
        ?string $paymentReference,
        bool    $paid,
    ): Collection {
        $locale           = app()->getLocale();
        $items            = $this->cart->items();
        $hasPickupColumns = Schema::hasColumn('bookings', 'pickup_point');

        // Resolve customer_id once before the map
        $customerId = $this->resolveCustomerId($customer, $locale);

        $bookings = $items->map(function (array $item) use (
            $customer, $method, $paymentReference, $paid, $locale, $hasPickupColumns, $customerId
        ): Booking {
            $attrs = [
                'customer_id'         => $customerId,
                'tour_id'             => $item['tour_id'],
                'tour_title_snapshot' => $item['title_snapshot'],
                'customer_name'       => $customer['customer_name'],
                'customer_email'      => $customer['customer_email'],
                'customer_phone'      => $customer['customer_phone'],
                'travel_date'         => $customer['travel_date'],
                'adults'              => $item['adults'],
                'children'            => $item['children'],
                'unit_price'          => $item['unit_price'],
                'total_price'         => $item['subtotal'],
                'currency'            => 'USD',
                'status'              => $paid ? 'confirmed' : 'pending',
                'payment_status'      => $paid ? 'paid' : 'pending',
                'payment_method'      => $method,
                'payment_reference'   => $paymentReference,
                'locale'              => $locale,
            ];

            if ($hasPickupColumns) {
                $attrs['pickup_point']  = $customer['pickup_point'] ?? null;
                $attrs['pickup_detail'] = $customer['pickup_detail'] ?? null;
            }

            return Booking::create($attrs);
        });

        Log::info('checkout.finalize_bookings', [
            'method'      => $method,
            'paid'        => $paid,
            'reference'   => $paymentReference,
            'email'       => $customer['customer_email'],
            'customer_id' => $customerId,
            'bookings'    => $bookings->pluck('reference')->all(),
        ]);

        // Send customer confirmation + internal admin notification (non-blocking).
        // Shared with the Filament admin "create booking" flow via BookingNotifier.
        $this->notifier->send($bookings, $paid, $customer['customer_email']);

        // Cierra el carrito abandonado asociado (por sesión y/o email)
        $this->abandoned->markConverted(session()->getId(), $customer['customer_email']);

        $this->cart->clear();

        return $bookings;
    }

    /**
     * Returns the customer_id to attach to new bookings.
     * - Logged-in customers: use their existing id.
     * - Existing email (no session): reuse without sending any email.
     * - New email: create an account with a generated temporary password
     *   and send the credentials via email (AccountCredentials mailable).
     */
    private function resolveCustomerId(array $customer, string $locale): int
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
        // Str::password() is available since Laravel 10.x.
        $plain = Str::password(10, letters: true, numbers: true, symbols: false, spaces: false);

        // Create the new guest customer — the 'hashed' cast on Customer::$password
        // automatically bcrypts the plain string on assignment.
        $guestCustomer = Customer::create([
            'name'     => $customer['customer_name'],
            'email'    => $customer['customer_email'],
            'phone'    => $customer['customer_phone'] ?? null,
            'locale'   => $locale,
            'password' => $plain,
        ]);

        // Send credentials email. Failure is non-fatal: log a warning and continue.
        try {
            Mail::to($guestCustomer->email)
                ->send(new AccountCredentials($guestCustomer, $plain, $locale));
        } catch (\Throwable $ex) {
            Log::warning('checkout.guest_credentials_email.failed', [
                'email'   => $guestCustomer->email,
                'message' => $ex->getMessage(),
            ]);
        }

        return $guestCustomer->id;
    }
}
