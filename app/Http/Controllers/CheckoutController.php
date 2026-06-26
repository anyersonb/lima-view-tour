<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcessPaymentRequest;
use App\Mail\BookingConfirmed;
use App\Mail\BookingNotificationAdmin;
use App\Models\Setting;
use App\Models\Booking;
use App\Services\CartService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly PaymentService $payment
    ) {}

    /**
     * Display the payment form or redirect to cart if empty.
     */
    public function showPaymentForm(Request $request): View|RedirectResponse
    {
        try {
            $items = $this->cart->items();

            if ($items->isEmpty()) {
                return redirect()
                    ->route('cart.index', ['locale' => app()->getLocale()])
                    ->with('error', __('cart.empty_checkout_redirect'));
            }

            return view('checkout.payment', [
                'items'          => $items,
                'subtotal'       => $this->cart->subtotal(),
                'discount'       => $this->cart->couponDiscount(),
                'total'          => $this->cart->total(),
                'couponCode'     => $this->cart->couponCode(),
                'total_centavos' => (int) round($this->cart->total() * 100),
                'public_key'     => config('services.culqi.public_key'),
            ]);
        } catch (\Throwable $e) {
            Log::error('checkout.show_payment_form.error', ['message' => $e->getMessage()]);

            return redirect()
                ->route('cart.index', ['locale' => app()->getLocale()])
                ->with('error', 'Ocurrió un error al cargar el formulario de pago.');
        }
    }

    /**
     * Process the payment: create booking(s), charge via Culqi (if paying now),
     * send confirmation email, redirect to thanks page.
     *
     * payment_timing=now  → full Culqi charge flow (existing behavior, unchanged)
     * payment_timing=later → bookings created as pending/pay_later, no charge
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

            $validated     = $request->validated();
            $total         = $this->cart->total();
            $totalCentavos = (int) round($total * 100);
            $payingNow     = ($validated['payment_timing'] === 'now');

            // Las columnas pickup_* pueden no existir todavía en algunos entornos
            // (migración pendiente). Solo las incluimos si están presentes para
            // no romper la creación de la reserva.
            $hasPickupColumns = \Illuminate\Support\Facades\Schema::hasColumn('bookings', 'pickup_point');
            $pickupPoint  = $request->input('pickup_point');
            $pickupDetail = $request->input('pickup_detail');

            // Create one Booking per cart item
            $bookings = $items->map(function (array $item) use ($validated, $locale, $payingNow, $hasPickupColumns, $pickupPoint, $pickupDetail): Booking {
                $attrs = [
                    'tour_id'             => $item['tour_id'],
                    'tour_title_snapshot' => $item['title_snapshot'],
                    'customer_name'       => $validated['customer_name'],
                    'customer_email'      => $validated['customer_email'],
                    'customer_phone'      => $validated['customer_phone'],
                    'travel_date'         => $validated['travel_date'],
                    'adults'              => $item['adults'],
                    'children'            => $item['children'],
                    'unit_price'          => $item['unit_price'],
                    'total_price'         => $item['subtotal'],
                    'currency'            => 'USD',
                    'status'              => 'pending',
                    'payment_status'      => 'pending',
                    'payment_method'      => $payingNow ? 'culqi' : 'pay_later',
                    'locale'              => $locale,
                ];

                if ($hasPickupColumns) {
                    $attrs['pickup_point']  = $pickupPoint;
                    $attrs['pickup_detail'] = $pickupDetail;
                }

                return Booking::create($attrs);
            });

            if ($payingNow) {
                // ── Flujo pago inmediato: cobrar con Culqi ──────────────────
                $chargeData = $this->payment->createCharge([
                    'amount'        => $totalCentavos,
                    'currency_code' => 'USD',
                    'email'         => $validated['customer_email'],
                    'source_id'     => $validated['culqi_token'],
                    'antifraud_details' => [
                        'first_name'   => explode(' ', $validated['customer_name'])[0] ?? $validated['customer_name'],
                        'last_name'    => implode(' ', array_slice(explode(' ', $validated['customer_name']), 1)) ?: '-',
                        'phone_number' => preg_replace('/\D/', '', $validated['customer_phone']),
                    ],
                    'metadata' => [
                        'booking_references' => $bookings->pluck('reference')->implode(','),
                        'locale'             => $locale,
                    ],
                ]);

                $chargeId = $chargeData['id'];

                // Mark all bookings as paid and confirmed
                $bookings->each(function (Booking $booking) use ($chargeId): void {
                    $booking->update([
                        'payment_status'    => 'paid',
                        'status'            => 'confirmed',
                        'payment_reference' => $chargeId,
                    ]);
                });

                Log::info('checkout.process_payment.success', [
                    'charge_id' => $chargeId,
                    'email'     => $validated['customer_email'],
                    'bookings'  => $bookings->pluck('reference')->all(),
                ]);
            } else {
                // ── Flujo pagar después: reservas pendientes, sin cobro ─────
                Log::info('checkout.process_payment.pay_later', [
                    'email'    => $validated['customer_email'],
                    'bookings' => $bookings->pluck('reference')->all(),
                ]);
            }

            // Send confirmation email to the customer (applies to both flows).
            // Un fallo de correo NO debe abortar la reserva ya creada.
            try {
                Mail::to($validated['customer_email'])
                    ->send(new BookingConfirmed($bookings, $validated['customer_email']));

                Log::info('checkout.confirmation_email.sent', [
                    'email'    => $validated['customer_email'],
                    'bookings' => $bookings->pluck('reference')->all(),
                ]);
            } catch (\Throwable $mailEx) {
                Log::warning('checkout.confirmation_email.failed', [
                    'email'   => $validated['customer_email'],
                    'message' => $mailEx->getMessage(),
                ]);
            }

            // Send internal admin notification.
            // Destination: 'booking_notification_email' setting, fallback to mail.from.address.
            try {
                $adminEmail = Setting::get('booking_notification_email')
                    ?: config('mail.from.address');

                Mail::to($adminEmail)
                    ->send(new BookingNotificationAdmin($bookings, $validated['payment_timing']));

                Log::info('checkout.admin_notification_email.sent', [
                    'admin_email' => $adminEmail,
                    'bookings'    => $bookings->pluck('reference')->all(),
                ]);
            } catch (\Throwable $adminMailEx) {
                Log::warning('checkout.admin_notification_email.failed', [
                    'message' => $adminMailEx->getMessage(),
                ]);
            }

            // Clear cart and store booking references in session
            $this->cart->clear();

            $request->session()->put('last_bookings', $bookings->toArray());

            return redirect()->route('checkout.thanks', ['locale' => $locale]);

        } catch (\Throwable $e) {
            Log::error('checkout.process_payment.error', [
                'message' => $e->getMessage(),
                'email'   => $request->input('customer_email'),
            ]);

            // Mark bookings as failed if they were already created (best effort)
            if (isset($bookings)) {
                $bookings->each(fn (Booking $b) => $b->update(['payment_status' => 'failed']));
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'El pago no pudo procesarse: ' . $e->getMessage());
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
}
