<?php

namespace App\Http\Controllers;

use App\Exceptions\PayPalCardDeclinedException;
use App\Exceptions\UnbookableDateException;
use App\Http\Requests\ProcessPaymentRequest;
use App\Mail\AccountCredentials;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Customer;
use App\Services\AbandonedCartService;
use App\Services\BookingNotifier;
use App\Services\CartService;
use App\Services\PaymentLockService;
use App\Services\PaymentService;
use App\Services\PayPalService;
use App\Support\BookingCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly PaymentService $payment,
        private readonly PayPalService $paypal,
        private readonly BookingNotifier $notifier,
        private readonly AbandonedCartService $abandoned,
        private readonly PaymentLockService $paymentLock,
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
            // Puerta 3 del mismo bloqueo que paypalCreateOrder() /
            // paypalCaptureOrder(): un pago con PayPal que se cobró pero
            // cuya reserva no se creó no debe poder "resolverse" grabando en
            // su lugar una reserva pendiente por "pagar luego" sobre el
            // mismo carrito — el carrito sigue lleno porque finalizeBookings()
            // lanzó ANTES de llegar a cart->clear(). Esta ruta no responde
            // JSON: redirige con el mismo mensaje de revisión manual.
            if ($lock = $this->paymentLock->current()) {
                Log::warning('checkout.process_payment: blocked, pending manual review lock', [
                    'capture_id' => $lock['capture_id'],
                ]);

                return redirect()
                    ->route('cart.index', ['locale' => $locale])
                    ->with('error', $this->paymentLock->message($lock));
            }

            $items = $this->cart->items();

            if ($items->isEmpty()) {
                return redirect()
                    ->route('cart.index', ['locale' => $locale])
                    ->with('error', __('cart.empty_checkout_redirect'));
            }

            $validated = $request->validated();
            $payingNow = ($validated['payment_timing'] === 'now');

            // "Pay now" via the old form submission is no longer supported.
            // The PayPal JS flow handles "now"; only "later" should reach here.
            if ($payingNow) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'El pago inmediato debe realizarse a través del botón de PayPal.');
            }

            $customer = [
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'],
                'travel_date' => $validated['travel_date'],
                'pickup_point' => $request->input('pickup_point'),
                'pickup_detail' => $request->input('pickup_detail'),
            ];

            // Defense-in-depth: re-verify blocked dates for every cart item
            foreach ($items as $item) {
                // Cada tour va con SU fecha desde que se pueden editar por
                // separado en el carrito: comprobar solo la del formulario
                // dejaba pasar un tour cuya propia fecha sí está bloqueada.
                $travelDate = $item['travel_date'] ?? $validated['travel_date'];

                if (BlockedDate::isBlocked($travelDate, $item['tour_id'] ?? null)) {
                    Log::info('checkout.process_payment: blocked date rejected', [
                        'travel_date' => $travelDate,
                        'tour_id' => $item['tour_id'] ?? null,
                    ]);

                    return redirect()
                        ->route('cart.index', ['locale' => $locale])
                        ->with('error', __('booking.date_blocked'));
                }
            }

            $bookings = $this->finalizeBookings($items, $customer, 'pay_later', null, false);

            $request->session()->put('last_bookings', $bookings->toArray());

            return redirect()->route('checkout.thanks', ['locale' => $locale]);

        } catch (UnbookableDateException $e) {
            // finalizeBookings() revalida la fecha de CADA ítem contra
            // BookingCalendar antes de grabar nada: ProcessPaymentRequest
            // solo valida el campo `travel_date` del formulario, nunca la
            // fecha guardada en el ítem. Un carrito abierto en una pestaña
            // días atrás llega aquí con una fecha ya pasada.
            Log::info('checkout.process_payment: item date no longer bookable', [
                'travel_date' => $e->travelDate,
                'tour_id' => $e->tourId,
            ]);

            return redirect()
                ->route('cart.index', ['locale' => $locale])
                ->with('error', __('booking.date_outdated'));

        } catch (\Throwable $e) {
            Log::error('checkout.process_payment.error', [
                'message' => $e->getMessage(),
                'email' => $request->input('customer_email'),
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
            // Bloqueo en servidor: si YA hay un cobro capturado en esta
            // sesión cuya reserva no se pudo crear (ver el catch de
            // paypalCaptureOrder), no se crea una orden nueva aunque el
            // cliente refresque, vuelva atrás o pulse el botón de PayPal otra
            // vez. Deshabilitar el botón en el frontend es UX y un refresco
            // lo salta; esto no. El marcador vive en sesión y caduca solo
            // (ver PaymentLockService).
            if ($lock = $this->paymentLock->current()) {
                Log::warning('checkout.paypal_create_order: blocked, pending manual review lock', [
                    'order_id' => $lock['order_id'],
                    'capture_id' => $lock['capture_id'],
                ]);

                return response()->json($this->paymentLock->blockedResponsePayload($lock), 409);
            }

            $items = $this->cart->items();

            if ($items->isEmpty()) {
                return response()->json(['error' => 'El carrito está vacío.'], 422);
            }

            // Amount always calculated server-side — never trust the client
            $total = $this->cart->total();

            $order = $this->paypal->createOrder($total, 'USD', [
                'locale' => $locale,
            ]);

            // Snapshot de lo que había en el carrito EN ESTE MOMENTO: es lo
            // único que ata el orderID de PayPal a un importe y a unos ítems
            // concretos. Sin esto, nada impedía que entre "crear" y "capturar"
            // el cliente engordara el carrito desde otra pestaña: se cobraba
            // el importe viejo (aprobado) y se reservaba el carrito vivo (más
            // caro). Ver paypalCaptureOrder(), que es la única fuente de
            // verdad para lo que se cobra y lo que se reserva.
            $this->storePaypalSnapshot($order['id'], [
                'total' => $total,
                'currency' => 'USD',
                'items' => $items->values()->all(),
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
     *
     * Todo lo que se cobra y lo que se reserva sale del SNAPSHOT guardado en
     * paypalCreateOrder() — nunca del carrito vivo en sesión, que pudo
     * cambiar entre que el cliente aprobó el pago en el popup de PayPal y
     * que este endpoint corre.
     */
    public function paypalCaptureOrder(Request $request, string $locale): JsonResponse
    {
        $validated = $request->validate([
            'orderID' => ['required', 'string', 'max:100'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'regex:/^\+?\d{7,15}$/'],
            'travel_date' => array_merge(['required'], BookingCalendar::dateRules()),
            'pickup_point' => ['nullable', 'string', 'max:100'],
            'pickup_detail' => ['nullable', 'string', 'max:255'],
        ], BookingCalendar::dateMessages());

        $orderId = $validated['orderID'];
        $captureId = null;

        try {
            // Puerta 2 del mismo bloqueo que paypalCreateOrder(): dos
            // pestañas pueden tener CADA UNA su propio snapshot vivo
            // (PAYPAL_SNAPSHOT_LIMIT = 10). Si la pestaña 1 ya capturó su
            // orden y falló al reservar (marcador puesto, ver el catch más
            // abajo), la pestaña 2 NO debe poder capturar su propia orden —
            // distinta, con snapshot válido — porque sería un cobro real y
            // doble. Esto es DIFERENTE del replay de la MISMA orden, que ya
            // cubre paypalSnapshot()/forgetPaypalSnapshot() más abajo. Se
            // corta ANTES de tocar PayPal (ni getOrder ni captureOrder).
            if ($lock = $this->paymentLock->current()) {
                Log::warning('checkout.paypal_capture: blocked, pending manual review lock', [
                    'order_id' => $orderId,
                    'blocking_capture_id' => $lock['capture_id'],
                ]);

                return response()->json($this->paymentLock->blockedResponsePayload($lock), 409);
            }

            // Si el orderID no tiene snapshot (nunca se creó desde acá, ya se
            // capturó antes, o expiró de la sesión) se rechaza SIN llamar a
            // PayPal. Esto también resuelve la idempotencia: tras capturar se
            // borra el snapshot (más abajo), así que un replay del mismo
            // orderID siempre cae aquí y nunca crea un segundo juego de
            // reservas.
            $snapshot = $this->paypalSnapshot($orderId);

            if (! $snapshot) {
                Log::warning('checkout.paypal_capture: unknown or already-used order snapshot', [
                    'order_id' => $orderId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => __('booking.payment_failed'),
                ], 422);
            }

            $snapshotItems = collect($snapshot['items']);

            if ($snapshotItems->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'El carrito está vacío.'], 422);
            }

            $customer = [
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'],
                'travel_date' => $validated['travel_date'],
                'pickup_point' => $validated['pickup_point'] ?? null,
                'pickup_detail' => $validated['pickup_detail'] ?? null,
            ];

            // Defense-in-depth: re-verify blocked dates against the SNAPSHOT
            // (lo que de verdad se va a reservar y cobrar), no contra el
            // carrito vivo.
            foreach ($snapshotItems as $item) {
                $travelDate = $item['travel_date'] ?? $customer['travel_date'];

                if (BlockedDate::isBlocked($travelDate, $item['tour_id'] ?? null)) {
                    Log::info('checkout.paypal_capture: blocked date rejected', [
                        'travel_date' => $travelDate,
                        'tour_id' => $item['tour_id'] ?? null,
                        'order_id' => $orderId,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => __('booking.date_blocked'),
                    ], 422);
                }
            }

            // Revalidar ANTES de capturar: acá ya se mueve dinero real, así
            // que rechazar la fecha DESPUÉS de cobrar no sirve de nada. Esta
            // comprobación NO se repite después de capturar (ver el
            // $datesAlreadyChecked=true más abajo): la llamada a PayPal (get
            // order + capture) puede tardar lo suficiente para cruzar la
            // medianoche de Lima y convertir un carrito válido en inválido a
            // mitad del proceso — repetir el chequeo ahí solo lograba cobrar
            // y NO reservar (hallazgo de seguridad, 2026-08-27).
            $this->assertBookableDates($snapshotItems, $customer);

            // GET del pedido en PayPal y comparación del importe contra el
            // snapshot ANTES de capturar. Los importes se comparan como texto
            // normalizado a 2 decimales, nunca con `==` sobre floats.
            $remoteOrder = $this->paypal->getOrder($orderId);
            $remoteAmount = $remoteOrder['purchase_units'][0]['amount']['value'] ?? null;
            $remoteCurrency = $remoteOrder['purchase_units'][0]['amount']['currency_code'] ?? null;

            $amountMatches = $remoteAmount !== null
                && $this->normalizedAmount($remoteAmount) === $this->normalizedAmount($snapshot['total']);
            $currencyMatches = $remoteCurrency !== null
                && strtoupper($remoteCurrency) === strtoupper($snapshot['currency']);

            if (! $amountMatches || ! $currencyMatches) {
                // Este es el ataque que motivó todo el snapshot: aprobar un
                // pedido barato, engordar el carrito, y disparar la captura
                // esperando que se cobre lo viejo y se reserve lo nuevo. Si
                // el importe en PayPal no coincide con lo que calculamos al
                // crear la orden, no se captura nada.
                Log::error('checkout.paypal_capture: amount mismatch, capture aborted', [
                    'order_id' => $orderId,
                    'expected_amount' => $snapshot['total'],
                    'expected_currency' => $snapshot['currency'],
                    'received_amount' => $remoteAmount,
                    'received_currency' => $remoteCurrency,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => __('booking.payment_failed'),
                ], 422);
            }

            $captureResponse = $this->paypal->captureOrder($orderId);
            $captureId = $this->paypal->captureId($captureResponse);

            // Se borra apenas se captura el dinero, no después de crear las
            // reservas: un replay de la captura (doble clic, reintento del
            // JS) con el mismo orderID nunca vuelve a encontrar snapshot y se
            // rechaza arriba, sin cobrar ni reservar dos veces.
            $this->forgetPaypalSnapshot($orderId);

            // Las reservas se construyen desde el SNAPSHOT, no del carrito
            // vivo: es lo único que ata lo cobrado a lo reservado.
            // $datesAlreadyChecked=true: ya se verificó arriba, contra estos
            // mismos ítems — repetirlo en finalizeBookings() es el chequeo
            // que, tras capturar, podía fallar por el cruce de medianoche y
            // dejar el dinero cobrado sin reserva.
            $bookings = $this->finalizeBookings($snapshotItems, $customer, 'paypal', $captureId, true, datesAlreadyChecked: true);

            $request->session()->put('last_bookings', $bookings->toArray());

            return response()->json([
                'success' => true,
                'redirect' => route('checkout.thanks', ['locale' => $locale]),
            ]);

        } catch (UnbookableDateException $e) {
            Log::info('checkout.paypal_capture: item date no longer bookable', [
                'travel_date' => $e->travelDate,
                'tour_id' => $e->tourId,
                'order_id' => $orderId,
            ]);

            return response()->json([
                'success' => false,
                'message' => __('booking.date_outdated'),
            ], 422);

        } catch (PayPalCardDeclinedException $e) {
            // El banco/emisor rechazó la tarjeta al capturar: no se movió
            // dinero (el 422 de PayPal llega ANTES de que exista capture_id)
            // y no es una falla nuestra, así que no comparte código, mensaje
            // ni status con el catch genérico de abajo. 402 Payment Required
            // es el status que mejor describe "tu instrumento de pago fue
            // rechazado" — distinto del 500 genérico (fallo de servidor) y
            // del 422 que ya usan más arriba fecha bloqueada/importe no
            // coincide (esos son rechazos de la solicitud, no del banco).
            Log::info('checkout.paypal_capture: card declined by issuer', [
                'order_id' => $orderId,
                'issue' => $e->issue,
            ]);

            return response()->json([
                'success' => false,
                'code' => 'card_declined',
                'message' => __('booking.card_declined'),
            ], 402);

        } catch (\Throwable $e) {
            // Si esto revienta DESPUÉS de capturar (captureOrder ya tuvo
            // éxito, pero p.ej. finalizeBookings falla por un error de BD),
            // el dinero quedó cobrado sin una reserva que lo respalde y sin
            // compensación automática (no hay refund acá). Por eso este log
            // SIEMPRE lleva order_id, capture_id, importe, moneda y el
            // snapshot completo: es el único rastro para que un humano lo
            // revise y decida reembolsar o crear la reserva a mano.
            Log::error('checkout.paypal_capture_order.error', [
                'message' => $e->getMessage(),
                'order_id' => $orderId,
                'capture_id' => $captureId,
                'amount' => $snapshot['total'] ?? null,
                'currency' => $snapshot['currency'] ?? null,
                'email' => $validated['customer_email'] ?? null,
                'snapshot' => $snapshot ?? null,
            ]);

            // $captureId solo se llena DESPUÉS de que PayPal confirmó el
            // cobro (línea 323-324 arriba): si está presente, el dinero SÍ se
            // movió y lo que falló fue crear la reserva. Son situaciones
            // opuestas y no pueden compartir mensaje ni invitar a reintentar:
            // reintentar acá cobraría una SEGUNDA vez por la misma reserva.
            if ($captureId !== null) {
                // Deja el marcador de bloqueo: mientras exista, las otras
                // dos puertas (paypalCreateOrder, paypalCaptureOrder para
                // OTRA orden, y processPayment/pay_later) se niegan a seguir
                // adelante en esta sesión (ver PaymentLockService).
                $this->paymentLock->store($orderId, $captureId, $snapshot ?? [], $validated);

                // Contrato JSON para el frontend (maquetador-frontend pinta
                // sobre esto):
                //   HTTP 200
                //   {
                //     "success": false,
                //     "code": "payment_captured_booking_pending",
                //     "message": "<texto para mostrar tal cual>",
                //     "reference": "<capture_id de PayPal>"
                //   }
                // El frontend NO debe reintentar el pago ni reactivar el
                // botón de PayPal ante este código: debe mostrar el mensaje,
                // la referencia, y retirar el flujo de pago de la pantalla.
                return response()->json([
                    'success' => false,
                    'code' => 'payment_captured_booking_pending',
                    'message' => 'Tu pago se procesó correctamente, pero tuvimos un problema al '
                        .'confirmar tu reserva. Nuestro equipo ya fue notificado y la completará '
                        .'de forma manual; te contactaremos en breve. Por favor NO vuelvas a '
                        .'intentar el pago. Guarda esta referencia para cualquier consulta: '
                        .$captureId,
                    'reference' => $captureId,
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => __('booking.payment_failed'),
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
     * Creates one Booking per item, sends confirmation emails,
     * clears the LIVE cart and returns the collection of created bookings.
     *
     * @param  Collection  $items  Ítems a reservar. Para 'pay_later' son el
     *                             carrito vivo; para 'paypal' son el
     *                             SNAPSHOT capturado en paypalCreateOrder(),
     *                             nunca el carrito vivo (ver
     *                             paypalCaptureOrder).
     * @param  array  $customer  Keys: customer_name, customer_email,
     *                           customer_phone, travel_date,
     *                           pickup_point, pickup_detail
     * @param  string  $method  'paypal' | 'pay_later'
     * @param  string|null  $paymentReference  PayPal capture ID (null for pay_later)
     * @param  bool  $paid  true = mark as paid & confirmed
     * @param  bool  $datesAlreadyChecked  true si el llamador ya corrió
     *                                     assertBookableDates() sobre estos
     *                                     MISMOS $items justo antes (caso
     *                                     PayPal: repetirlo tras capturar el
     *                                     pago solo servía para cobrar y no
     *                                     reservar si la fecha dejaba de ser
     *                                     válida a mitad de la llamada a
     *                                     PayPal). 'pay_later' no toca este
     *                                     flag: sigue siendo su última línea
     *                                     de defensa.
     * @return Collection<Booking>
     */
    private function finalizeBookings(
        Collection $items,
        array $customer,
        string $method,
        ?string $paymentReference,
        bool $paid,
        bool $datesAlreadyChecked = false,
    ): Collection {
        $locale = app()->getLocale();
        $hasPickupColumns = Schema::hasColumn('bookings', 'pickup_point');

        if (! $datesAlreadyChecked) {
            // Última línea de defensa: revalida la fecha de CADA ítem antes
            // de grabar un solo Booking. ProcessPaymentRequest solo mira el
            // campo `travel_date` del FORMULARIO, nunca el de cada ítem del
            // carrito, así que un carrito abierto en una pestaña días atrás
            // se grababa con una fecha en el pasado. Ver assertBookableDates().
            $this->assertBookableDates($items, $customer);
        }

        // Resolve customer_id y crear los Bookings dentro de UNA transacción:
        // un carrito de 3 tours donde el segundo revienta (BD, validación,
        // lo que sea) no debe dejar 1 reserva pagada y 2 sin crear. Todo o
        // nada — y si "nada", el catch de paypalCaptureOrder() decide qué
        // hacer con un pago que ya se cobró (ver el marcador de bloqueo).
        //
        // OJO: el correo de credenciales de invitado (dentro de
        // resolveCustomerId) y BookingNotifier::send() se disparan FUERA de
        // esta transacción, después del commit. Un rollback no puede
        // des-enviar un correo: si el correo saliera dentro y luego la
        // transacción revirtiera, quedaría prometiendo una cuenta o una
        // reserva que ya no existe.
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
                    // La fecha del ÍTEM, no la del formulario. Grababa
                    // $customer['travel_date'] en todas las reservas del carrito,
                    // así que reservar dos tours en días distintos guardaba los dos
                    // en la fecha del primero, sin avisar a nadie. El carrito
                    // siempre soportó fecha por tour (rowId = md5(tour_id.fecha));
                    // era el checkout el que la tiraba.
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
                ];

                if ($hasPickupColumns) {
                    $attrs['pickup_point'] = $customer['pickup_point'] ?? null;
                    $attrs['pickup_detail'] = $customer['pickup_detail'] ?? null;
                }

                return Booking::create($attrs);
            });
        });

        // Recién aquí, con la transacción ya confirmada, es seguro enviar el
        // correo con las credenciales del cliente invitado recién creado.
        if ($pendingGuestMail !== null) {
            $this->sendGuestCredentialsMail(
                $pendingGuestMail['customer'],
                $pendingGuestMail['plain'],
                $pendingGuestMail['locale'],
            );
        }

        Log::info('checkout.finalize_bookings', [
            'method' => $method,
            'paid' => $paid,
            'reference' => $paymentReference,
            'email' => $customer['customer_email'],
            'customer_id' => $bookings->first()?->customer_id,
            'bookings' => $bookings->pluck('reference')->all(),
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
     * Revalida la fecha de CADA ítem del carrito contra
     * App\Support\BookingCalendar justo antes de comprometer la reserva
     * (grabar Bookings o capturar el pago de PayPal).
     *
     * Se rechaza el carrito COMPLETO ante el primer ítem con fecha inválida,
     * en vez de grabar unos tours sí y otros no: una reserva a medias (parte
     * de un mismo carrito, un mismo email, un mismo pago) confunde más de lo
     * que ayuda — el cliente no sabría cuáles de sus tours sí quedaron
     * reservados sin leer con cuidado el correo. Rechazar entero deja el
     * carrito intacto (nada se limpia) y el cliente solo tiene que corregir
     * la fecha marcada y reenviar.
     *
     * @throws UnbookableDateException con la fecha y el tour_id del primer
     *                                 ítem que ya no es reservable
     */
    private function assertBookableDates(Collection $items, array $customer): void
    {
        foreach ($items as $item) {
            $travelDate = $item['travel_date'] ?? $customer['travel_date'];

            if (! BookingCalendar::isBookable($travelDate)) {
                throw new UnbookableDateException($travelDate, $item['tour_id'] ?? null);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PayPal order snapshots (session)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Tope de snapshots de pedidos PayPal en vuelo por sesión. Crear una
     * orden sin nunca capturarla (el cliente cierra el popup, carrito
     * abandonado a mitad del botón) deja un snapshot huérfano para siempre;
     * un cliente real nunca tiene más de un pedido en vuelo a la vez.
     */
    private const PAYPAL_SNAPSHOT_LIMIT = 10;

    /**
     * Compara importes sin `==` sobre floats: normaliza a texto con 2
     * decimales fijos y compara como cadenas.
     */
    private function normalizedAmount(string|float $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Guarda, bajo el orderID de PayPal, el total y los ítems calculados en
     * servidor en el momento de crear la orden (paypalCreateOrder). Es la
     * única fuente de verdad que consume paypalCaptureOrder() para verificar
     * el importe y construir las reservas.
     */
    private function storePaypalSnapshot(string $orderId, array $snapshot): void
    {
        $orders = session()->get('paypal_orders', []);
        $orders[$orderId] = $snapshot;

        if (count($orders) > self::PAYPAL_SNAPSHOT_LIMIT) {
            $orders = array_slice($orders, -self::PAYPAL_SNAPSHOT_LIMIT, null, true);
        }

        session()->put('paypal_orders', $orders);
    }

    /**
     * @return array{total: float, currency: string, items: array}|null
     */
    private function paypalSnapshot(string $orderId): ?array
    {
        return session()->get('paypal_orders', [])[$orderId] ?? null;
    }

    /**
     * Borra el snapshot de un orderID ya capturado. Sin esto, un replay de
     * la captura (doble clic, reintento del JS) con el mismo orderID podría
     * volver a encontrar el snapshot y crear un segundo juego de reservas.
     */
    private function forgetPaypalSnapshot(string $orderId): void
    {
        $orders = session()->get('paypal_orders', []);
        unset($orders[$orderId]);
        session()->put('paypal_orders', $orders);
    }

    /**
     * Returns the customer_id to attach to new bookings.
     * - Logged-in customers: use their existing id.
     * - Existing email (no session): reuse without sending any email.
     * - New email: create an account with a generated temporary password.
     *   The credentials email is NOT sent here — this method runs inside
     *   finalizeBookings()'s DB::transaction(), and a rollback can't
     *   un-send an email. Instead, it fills $pendingGuestMail by reference
     *   so the caller can send it AFTER the transaction commits (see
     *   sendGuestCredentialsMail()).
     *
     * @param  array{customer: \App\Models\Customer, plain: string, locale: string}|null  $pendingGuestMail
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
        // Str::password() is available since Laravel 10.x.
        $plain = Str::password(10, letters: true, numbers: true, symbols: false, spaces: false);

        // Create the new guest customer — the 'hashed' cast on Customer::$password
        // automatically bcrypts the plain string on assignment.
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
     * finalizeBookings()'s DB::transaction() has committed — see
     * resolveCustomerId(). Failure is non-fatal: log a warning and continue.
     */
    private function sendGuestCredentialsMail(Customer $guestCustomer, string $plain, string $locale): void
    {
        try {
            Mail::to($guestCustomer->email)
                ->send(new AccountCredentials($guestCustomer, $plain, $locale));
        } catch (\Throwable $ex) {
            Log::warning('checkout.guest_credentials_email.failed', [
                'email' => $guestCustomer->email,
                'message' => $ex->getMessage(),
            ]);
        }
    }
}
