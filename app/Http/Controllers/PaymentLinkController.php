<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentLinkUnavailableException;
use App\Exceptions\PayPalCardDeclinedException;
use App\Exceptions\UnbookableDateException;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\PaymentLink;
use App\Exceptions\CulqiChargeDeclinedException;
use App\Services\BalancePaymentService;
use App\Services\BookingCreationService;
use App\Services\BookingNotifier;
use App\Services\PaymentService;
use App\Services\PayPalService;
use App\Support\BookingCalendar;
use App\Support\CheckoutInfo;
use App\Support\PaymentPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Flujo público de "Links de pago": el admin crea un PaymentLink desde el
 * panel para un tour + monto concretos, y el cliente lo abre en
 * /pagar/{code} y paga con el mismo botón PayPal JS SDK del checkout, sin
 * pasar por el carrito.
 *
 * El monto y la moneda SIEMPRE salen de PaymentLink (BD), nunca del
 * request — createOrder()/captureOrder() no leen ningún campo de importe
 * del body. La reserva se crea con el mismo contrato de datos que el
 * checkout normal vía BookingCreationService (ver
 * docs/payment-links/INFORME.md).
 *
 * PII del comprador (2026-09-25, ver docs/payment-links/SECURITY.md A-1):
 * `customer_*` es SOLO el prefill que el admin escribe al crear el link —
 * nunca lo toca el flujo de pago. `buyer_*` es SOLO lo que de verdad pagó
 * (escrito acá y por WebhookController), y es lo único que se usa para
 * crear la Booking / reconciliar.
 *
 * Un solo uso, siempre (N-1, docs/payment-links/SECURITY.md, decisión del
 * coordinador 2026-09-25): los links "multiuso" se eliminaron —
 * `PaymentLink::saving()` fuerza `single_use = true` sin excepción. El
 * re-chequeo anti-duplicado de este controller y del webhook confirma
 * "¿existe ya una Booking con `payment_reference = $captureId`?", no solo
 * `link->booking_id`, como red de seguridad adicional.
 */
class PaymentLinkController extends Controller
{
    public function __construct(
        private readonly PayPalService $paypal,
        private readonly BookingCreationService $bookingCreator,
        private readonly BookingNotifier $notifier,
        private readonly BalancePaymentService $balance,
        private readonly PaymentService $culqi,
    ) {}

    /**
     * Página pública del link. Muestra el tour + el botón PayPal si el link
     * es usable; si no (pagado/vencido/anulado/no existe) muestra un
     * mensaje claro sin ofrecer el botón. noindex,nofollow SIEMPRE, tanto en
     * el <meta> (ver la vista, que ahora usa $forceNoindex en vez de su
     * propio @push('head') para no duplicar la etiqueta del layout) como en
     * la cabecera X-Robots-Tag. Código inexistente responde 404 real.
     */
    public function show(Request $request, string $code): Response
    {
        $link = PaymentLink::with(['tour', 'booking'])->where('code', $code)->first();

        $this->resolveLocale($request, $link);

        if ($link) {
            $link->checkAndExpire();
            $link->refresh();
        }

        return response()
            ->view('payment-links.show', [
                'link' => $link,
                'tour' => $link?->tour,
                'locale' => app()->getLocale(),
                // Link de SALDO: la reserva ligada y si todavía se puede cobrar.
                'isBalance' => (bool) $link?->isBalance(),
                'booking' => $link?->isBalance() ? $link->booking : null,
                'balancePayable' => $link ? $link->balanceIsPayable() : false,
                // Tarjeta (Culqi) en el link de saldo: el front la pinta si está activa.
                'culqiEnabled' => (bool) $link?->isBalance() && CheckoutInfo::paymentMethods()['culqi'],
                'culqiPublicKey' => config('services.culqi.public_key'),
                'culqiChargeUrl' => $link ? route('payment-links.culqi.charge', ['code' => $link->code, 'lang' => app()->getLocale()]) : null,
            ], $link ? 200 : 404)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Crea la orden PayPal para el importe del link (nunca del request).
     * Mismo contrato de respuesta que CheckoutController::paypalCreateOrder().
     */
    public function createOrder(Request $request, string $code): JsonResponse
    {
        $this->resolveLocale($request);

        $link = PaymentLink::where('code', $code)->first();

        if (! $link) {
            return response()->json(['error' => __('payment_links.not_found')], 404);
        }

        $link->checkAndExpire();
        $link->refresh();

        if (! $link->isUsable()) {
            return response()->json(['error' => $this->unavailableMessage($link)], 422);
        }

        // PayPal oculto por el admin: no se crea ninguna orden nueva desde /pagar (el servidor
        // rechaza, no solo se esconde el botón). La captura de órdenes ya creadas NO se toca.
        if (! PayPalService::offeredForLink($link)) {
            Log::info('payment_link.create_order: rejected, PayPal hidden for customers', ['link' => $link->id]);

            return response()->json(['code' => 'paypal_disabled', 'error' => __('checkout_payment.paypal_disabled')], 403);
        }

        // Link de saldo: la reserva ya no debe tener ESE saldo pendiente => no se crea la orden.
        if (! $link->balanceIsPayable()) {
            return response()->json(['error' => $this->unavailableMessage($link, 'balance_changed')], 422);
        }

        // B-6: si ya hay una orden PayPal VIVA (CREATED/APPROVED) para este
        // link, se reutiliza en vez de pisarla. Un link de pago es un enlace
        // compartible: sin esto, cualquier otro poseedor del mismo link (o
        // el propio comprador recargando la página) podía invalidar, con
        // solo volver a golpear este endpoint, la orden que el comprador
        // legítimo ya tenía abierta en su botón de PayPal.
        if ($link->paypal_order_id) {
            try {
                $existing = $this->paypal->getOrder($link->paypal_order_id);
                $existingStatus = $existing['status'] ?? null;

                if (in_array($existingStatus, ['CREATED', 'APPROVED'], true)) {
                    // N-4: reutilizar la orden viva SOLO si su importe y
                    // moneda siguen siendo los del link. Si el admin editó
                    // el monto después de crearla (normalmente esto ya no
                    // debería pasar: PaymentLink::saving() limpia
                    // paypal_order_id cuando cambia el amount de un link
                    // pending — esto es la segunda capa, por si la orden
                    // sobrevivió por otra vía), reutilizarla capturaría el
                    // importe VIEJO o PayPal la rechazaría en el capture.
                    $existingAmount = $existing['purchase_units'][0]['amount']['value'] ?? null;
                    $existingCurrency = $existing['purchase_units'][0]['amount']['currency_code'] ?? null;

                    $stillMatchesLink = $existingAmount !== null
                        && number_format((float) $existingAmount, 2, '.', '') === number_format((float) $link->amount, 2, '.', '')
                        && strtoupper((string) $existingCurrency) === 'USD';

                    if ($stillMatchesLink) {
                        return response()->json(['id' => $link->paypal_order_id]);
                    }

                    Log::info('payment_link.create_order.previous_order_amount_mismatch', [
                        'code' => $this->maskCode($code),
                        'order_id' => $link->paypal_order_id,
                    ]);
                } elseif ($existingStatus === 'COMPLETED') {
                    // N-2: la orden anterior YA se capturó en PayPal — un
                    // timeout o corte de red nos impidió enterarnos cuando
                    // ocurrió (ver captureOrder()). Crear una orden NUEVA
                    // cobraría una segunda vez. Se deja constancia del cobro
                    // (mismo patrón forense que M-3) y se responde "ya está
                    // pagado", nunca un id de orden nuevo.
                    $recoveredCaptureId = $existing['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;

                    if ($recoveredCaptureId) {
                        $this->persistOrphanCapture($code, $recoveredCaptureId);

                        return response()->json([
                            'error' => __('payment_links.captured_booking_pending', ['reference' => $recoveredCaptureId]),
                        ], 422);
                    }
                }
            } catch (\Throwable $e) {
                // No se pudo confirmar el estado de la orden anterior
                // (vencida en PayPal, error de red, etc.) — se continúa y
                // se crea una nueva; no es un motivo para bloquear el pago.
                Log::info('payment_link.create_order.previous_order_check_failed', [
                    'code' => $this->maskCode($code),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        try {
            $order = $this->paypal->createOrder((float) $link->amount, 'USD', [
                // B-1: nunca el código completo en logs/analítica — solo el
                // prefijo, suficiente para correlacionar sin exponer la
                // credencial completa del link si el log se filtra.
                'payment_link_code' => $this->maskCode($link->code),
            ]);
        } catch (\Throwable $e) {
            Log::error('payment_link.create_order.error', [
                'code' => $this->maskCode($code),
                'message' => $e->getMessage(),
            ]);

            return response()->json(['error' => __('booking.payment_failed')], 500);
        }

        // Guarda el orderID bajo lock: si dos pestañas crean orden casi a la
        // vez, la última en escribir gana, y captureOrder() exige que el
        // orderID recibido coincida con el guardado — así solo UNA de las
        // dos órdenes creadas podrá capturarse contra este link.
        DB::transaction(function () use ($code, $order) {
            $fresh = PaymentLink::where('code', $code)->lockForUpdate()->first();
            if ($fresh && $fresh->isUsable()) {
                $fresh->forceFill(['paypal_order_id' => $order['id']])->save();
            }
        });

        return response()->json(['id' => $order['id']]);
    }

    /**
     * Captura la orden aprobada y crea la reserva. Idempotente:
     *  - lockForUpdate() sobre la fila del link evita que un doble clic o
     *    dos pestañas capturen la MISMA orden dos veces en paralelo.
     *  - Si el link YA quedó 'paid' con este mismo orderID (replay tras
     *    éxito), responde el mismo resultado sin volver a cobrar ni crear
     *    una segunda reserva.
     *  - El orderID debe coincidir con el que createOrder() guardó para
     *    ESTE código — evita que una captura destinada a otro link "preste"
     *    su dinero a este.
     *  - El importe se re-verifica contra PayPal (getOrder) ANTES de
     *    capturar, comparado siempre contra $link->amount, nunca contra el
     *    request.
     *  - M-2: la creación de la Booking (paso 2) vuelve a tomar el lock del
     *    link y re-chequea booking_id ANTES de crear — si el webhook ganó
     *    la carrera mientras tanto, se reutiliza SU reserva en vez de crear
     *    una segunda. WebhookController hace el mismo compare-and-set sobre
     *    la misma fila.
     */
    public function captureOrder(Request $request, string $code): JsonResponse
    {
        $locale = $this->resolveLocale($request);

        // En un link de SALDO el pagador es el cliente de la reserva: sus datos
        // vienen de la reserva y el formulario no es obligatorio.
        $isBalanceLink = PaymentLink::where('code', $code)->where('purpose', PaymentLink::PURPOSE_BALANCE)->exists();
        $personRule = $isBalanceLink ? 'nullable' : 'required';

        $validated = $request->validate([
            'orderID' => ['required', 'string', 'max:100'],
            'customer_name' => [$personRule, 'string', 'max:255'],
            'customer_email' => [$personRule, 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'regex:/^\+?\d{7,15}$/'],
        ]);

        $orderId = $validated['orderID'];
        // M-3: referencia POR VALOR (&) — si el forceFill()->save() de más
        // abajo revienta DESPUÉS de que PayPal ya capturó el dinero, el
        // catch de abajo necesita saber que el cobro SÍ ocurrió para no
        // decirle al comprador "pago fallido" (ver docs/payment-links/
        // SECURITY.md M-3).
        $captureId = null;

        try {
            $step1 = DB::transaction(function () use ($code, $orderId, $validated, &$captureId) {
                /** @var PaymentLink|null $link */
                $link = PaymentLink::where('code', $code)->lockForUpdate()->first();

                if (! $link) {
                    throw new PaymentLinkUnavailableException('not_found');
                }

                // Replay idempotente: ya se pagó con ESTE mismo orderID.
                if ($link->status === 'paid' && $link->paypal_order_id === $orderId && $link->paypal_capture_id) {
                    return ['already_paid' => true, 'link' => $link, 'capture_id' => $link->paypal_capture_id];
                }

                if (! $link->isUsable()) {
                    throw new PaymentLinkUnavailableException('not_usable');
                }

                if ($link->paypal_order_id !== $orderId) {
                    throw new PaymentLinkUnavailableException('order_mismatch');
                }

                // Link de saldo: antes de mover dinero, la reserva debe seguir
                // con ESE saldo pendiente (si se pagó por otra vía, no se cobra de nuevo).
                if (! $link->balanceIsPayable()) {
                    throw new PaymentLinkUnavailableException('balance_changed');
                }

                // Revalida la fecha (si el link tiene una fija) ANTES de
                // capturar dinero real — mismo principio que
                // CheckoutController::assertBookableDates().
                if ($link->travel_date) {
                    $travelDate = $link->travel_date->toDateString();

                    // Operator-created links are exempt from the daily booking cutoff
                    // (the operator decides); only a departure that already
                    // happened (Lima clock, tour's departure time) is refused.
                    if (! BookingCalendar::isUpcoming($travelDate, $link->tour)) {
                        throw new UnbookableDateException($travelDate, $link->tour_id);
                    }

                    if (BlockedDate::isBlocked($travelDate, $link->tour_id)) {
                        throw new UnbookableDateException($travelDate, $link->tour_id);
                    }
                }

                // GET del pedido en PayPal y comparación del importe contra
                // el link — NUNCA contra el request. Comparado como texto
                // normalizado a 2 decimales, nunca con `==` sobre floats.
                $remoteOrder = $this->paypal->getOrder($orderId);
                $remoteAmount = $remoteOrder['purchase_units'][0]['amount']['value'] ?? null;
                $remoteCurrency = $remoteOrder['purchase_units'][0]['amount']['currency_code'] ?? null;

                $amountMatches = $remoteAmount !== null
                    && number_format((float) $remoteAmount, 2, '.', '') === number_format((float) $link->amount, 2, '.', '');
                $currencyMatches = $remoteCurrency !== null && strtoupper($remoteCurrency) === 'USD';

                if (! $amountMatches || ! $currencyMatches) {
                    Log::error('payment_link.capture.amount_mismatch', [
                        'code' => $this->maskCode($code),
                        'order_id' => $orderId,
                        'expected_amount' => (string) $link->amount,
                        'received_amount' => $remoteAmount,
                        'received_currency' => $remoteCurrency,
                    ]);

                    throw new PaymentLinkUnavailableException('amount_mismatch');
                }

                $captureResponse = $this->paypal->captureOrder($orderId);
                // Asignación INMEDIATA a la variable por referencia: a
                // partir de esta línea, el dinero YA está cobrado en
                // PayPal, pase lo que pase después.
                $captureId = $this->paypal->captureId($captureResponse);

                // Escritura durable AHORA, en esta misma transacción corta:
                // aunque la creación del Booking (paso 2, fuera de aquí)
                // falle después, el link queda con constancia de que
                // PayPal SÍ cobró — rastro para revisión manual, igual que
                // el marcador de sesión de PaymentLockService en el
                // checkout normal, pero persistente en BD. `buyer_*` (no
                // `customer_*`, ver docs/payment-links/SECURITY.md A-1)
                // guarda los datos REALES del comprador (los del
                // formulario, no el prefill del admin): si el paso 2
                // falla, es lo único que le queda al webhook / a un humano
                // para poder armar la reserva después.
                $link->forceFill([
                    'paypal_capture_id' => $captureId,
                    'paid_at' => now(),
                    // N-1: siempre 'paid' — ya no existe el link multiuso
                    // que se quedaba 'pending' después de cobrar.
                    'status' => 'paid',
                    'buyer_name' => $validated['customer_name'] ?? $link->booking?->customer_name,
                    'buyer_email' => $validated['customer_email'] ?? $link->booking?->customer_email,
                    'buyer_phone' => $validated['customer_phone'] ?? $link->booking?->customer_phone,
                ])->save();

                return ['already_paid' => false, 'link' => $link, 'capture_id' => $captureId];
            });
        } catch (PaymentLinkUnavailableException $e) {
            return response()->json(['error' => $this->unavailableMessage(
                PaymentLink::where('code', $code)->first(), $e->reason
            )], 422);
        } catch (UnbookableDateException $e) {
            return response()->json(['success' => false, 'message' => __('booking.date_outdated')], 422);
        } catch (PayPalCardDeclinedException $e) {
            return response()->json([
                'success' => false,
                'code' => 'card_declined',
                'message' => __('booking.card_declined'),
            ], 402);
        } catch (\Throwable $e) {
            if ($captureId) {
                // M-3: PayPal YA capturó el dinero, pero la escritura en BD
                // (forceFill()->save() de arriba) falló DESPUÉS — la
                // transacción completa de ese paso se revirtió, así que el
                // link sigue 'pending' sin rastro del cobro. No es seguro
                // decirle al comprador "pago fallido" (reintentaría y
                // pagaría dos veces): se deja constancia del cobro en un
                // escritura AISLADA (fuera de la tx que reventó) para que
                // createOrder()/captureOrder() ya no vuelvan a aceptar un
                // nuevo intento sobre este link, y se avisa al admin.
                Log::error('payment_link.capture.db_write_failed_after_paypal_capture', [
                    'code' => $this->maskCode($code),
                    'order_id' => $orderId,
                    'capture_id' => $captureId,
                    'message' => $e->getMessage(),
                ]);

                $this->persistOrphanCapture($code, $captureId);
                $this->notifyAdminOfCaptureWriteFailure($code, $orderId, $captureId, $e);

                return response()->json([
                    'success' => false,
                    'code' => 'payment_captured_booking_pending',
                    'message' => __('payment_links.captured_booking_pending', ['reference' => $captureId]),
                    'reference' => $captureId,
                ], 200);
            }

            // N-2: acá $captureId sigue null — no sabemos con CERTEZA si el
            // fallo pasó ANTES de que PayPal cobrara (seguro decir "pago
            // fallido") o DESPUÉS (p.ej. un timeout justo tras el capture,
            // en cuyo caso decirle "pago fallido" al comprador lo empuja a
            // reintentar y pagar dos veces). Se confirma el estado REAL en
            // PayPal con un getOrder() antes de decidir la respuesta.
            $recoveredCaptureId = $this->recoverCaptureIdFromPaypal($orderId);

            if ($recoveredCaptureId) {
                Log::warning('payment_link.capture.recovered_via_get_order_after_exception', [
                    'code' => $this->maskCode($code),
                    'order_id' => $orderId,
                    'capture_id' => $recoveredCaptureId,
                    'original_message' => $e->getMessage(),
                ]);

                $this->persistOrphanCapture($code, $recoveredCaptureId);
                $this->notifyAdminOfCaptureWriteFailure($code, $orderId, $recoveredCaptureId, $e);

                return response()->json([
                    'success' => false,
                    'code' => 'payment_captured_booking_pending',
                    'message' => __('payment_links.captured_booking_pending', ['reference' => $recoveredCaptureId]),
                    'reference' => $recoveredCaptureId,
                ], 200);
            }

            Log::error('payment_link.capture.error', [
                'code' => $this->maskCode($code),
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => __('booking.payment_failed')], 500);
        }

        /** @var PaymentLink $link */
        $link = $step1['link'];
        $captureId = $step1['capture_id'];

        // BUG CORREGIDO (2026-11-21): un link LIGADO a una reserva (booking_id)
        // se trataba como "reserva ya creada" (más abajo: `$link->booking_id`
        // => success sin hacer nada). Con links de saldo eso devolvía éxito
        // SIN marcar la reserva como pagada. Un link de saldo tiene su propia
        // rama: nunca crea reserva, liquida la existente.
        if ($link->isBalance()) {
            return $this->finishBalancePayment($link, 'paypal', $captureId, (string) ($validated['customer_email'] ?? ''), $locale, $step1['already_paid']);
        }

        if ($step1['already_paid']) {
            $booking = $link->booking_id ? Booking::find($link->booking_id) : null;

            if (! $booking) {
                // Ventana minúscula: el paso 2 (creación de la reserva) de
                // OTRA request concurrente todavía no terminó. No es un
                // error del comprador — pedirle que espere, nunca que
                // reintente el pago (ya se cobró).
                return response()->json([
                    'success' => false,
                    'code' => 'payment_captured_booking_pending',
                    'message' => __('payment_links.processing_wait'),
                    'reference' => $captureId,
                ], 200);
            }

            // B-7: un replay con el code+orderID de OTRA persona no debe
            // cargar la reserva ajena en la sesión de quien pregunta (la
            // página de gracias mostraría su PII) ni disparar de nuevo el
            // evento de conversión. Solo se rellena la sesión cuando el
            // correo que llega en ESTE request coincide con el de la
            // reserva — cubre el caso real (doble clic / reintento de red
            // del MISMO comprador) sin abrir la puerta a un tercero.
            if (strcasecmp(trim($validated['customer_email']), (string) $booking->customer_email) === 0) {
                session()->put('last_bookings', collect([$booking])->toArray());
                session()->put('conversion_pending', true);
            } else {
                Log::warning('payment_link.capture.replay_email_mismatch', [
                    'code' => $this->maskCode($code),
                    'booking_id' => $booking->id,
                ]);
            }

            return response()->json([
                'success' => true,
                'redirect' => route('checkout.thanks', ['locale' => $locale]),
            ]);
        }

        // M-2: se re-toma el lock de la MISMA fila (misma que usa el
        // webhook para reconciliar) y se re-chequea booking_id ANTES de
        // crear — si PAYMENT.CAPTURE.COMPLETED llegó y el webhook ya creó
        // la Booking mientras este request hacía la captura, se reutiliza
        // esa reserva en vez de crear una segunda.
        try {
            $result = DB::transaction(function () use ($link, $validated, $captureId, $locale) {
                $fresh = PaymentLink::where('id', $link->id)->lockForUpdate()->first();

                if ($fresh->booking_id) {
                    return ['booking' => Booking::find($fresh->booking_id), 'created' => false];
                }

                // N-1 (decisión del coordinador): el re-chequeo anti-duplicado
                // no se fía SOLO de $fresh->booking_id — también pregunta si
                // YA existe una Booking con esta misma referencia de cobro
                // (por ejemplo, si el webhook la creó y el forceFill()
                // de más abajo que enlaza booking_id no llegó a correr por
                // cualquier motivo). Evita crear una segunda reserva para el
                // mismo dinero cobrado una sola vez.
                $existingBooking = Booking::where('payment_reference', $captureId)->first();

                if ($existingBooking) {
                    $fresh->forceFill(['booking_id' => $existingBooking->id])->save();

                    return ['booking' => $existingBooking, 'created' => false];
                }

                $item = [
                    'tour_id' => $fresh->tour_id,
                    'title_snapshot' => $fresh->tour?->title_es ?? __('payment_links.generic_tour_name'),
                    'travel_date' => $fresh->travel_date?->toDateString(),
                    'adults' => $fresh->adults,
                    'children' => $fresh->children,
                    // El link tiene un monto plano (no precio-por-persona),
                    // así que unit_price es una derivada informativa para
                    // el registro del Booking (mismas columnas que el
                    // checkout), repartiendo el total entre los pasajeros.
                    'unit_price' => round(((float) $fresh->amount) / max(1, $fresh->total_pax), 2),
                    'subtotal' => (float) $fresh->amount,
                ];

                $customer = [
                    'customer_name' => $validated['customer_name'],
                    'customer_email' => $validated['customer_email'],
                    'customer_phone' => $validated['customer_phone'] ?? null,
                    'travel_date' => $item['travel_date'],
                    'pickup_point' => null,
                    'pickup_detail' => null,
                ];

                $bookings = $this->bookingCreator->createBookings(
                    collect([$item]), $customer, 'payment_link', $captureId, true, $locale
                );

                $newBooking = $bookings->first();

                $fresh->forceFill(['booking_id' => $newBooking->id])->save();

                return ['booking' => $newBooking, 'created' => true, 'bookings' => $bookings, 'customer' => $customer];
            });

            $booking = $result['booking'];

            if ($result['created']) {
                Log::info('payment_link.paid', [
                    'code' => $this->maskCode($link->code),
                    'order_id' => $link->paypal_order_id,
                    'capture_id' => $captureId,
                    'booking_id' => $booking->id,
                ]);

                $this->notifier->send($result['bookings'], true, $result['customer']['customer_email']);
            } else {
                Log::info('payment_link.capture.booking_already_created_by_webhook', [
                    'code' => $this->maskCode($link->code),
                    'booking_id' => $booking->id,
                ]);
            }

            session()->put('last_bookings', collect([$booking])->toArray());
            session()->put('conversion_pending', true);

            return response()->json([
                'success' => true,
                'redirect' => route('checkout.thanks', ['locale' => $locale]),
            ]);

        } catch (\Throwable $e) {
            // El dinero YA se cobró (constancia durable dejada arriba en
            // $link->paypal_capture_id/paid_at) y la reserva no se pudo
            // crear. No hay reintento seguro: mismo contrato que
            // CheckoutController::paypalCaptureOrder() para este caso.
            Log::error('payment_link.capture_succeeded_booking_failed', [
                'code' => $this->maskCode($link->code),
                'order_id' => $link->paypal_order_id,
                'capture_id' => $captureId,
                'message' => $e->getMessage(),
            ]);

            $this->notifyAdminOfCaptureWriteFailure($link->code, $link->paypal_order_id, $captureId, $e);

            return response()->json([
                'success' => false,
                'code' => 'payment_captured_booking_pending',
                'message' => __('payment_links.captured_booking_pending', ['reference' => $captureId]),
                'reference' => $captureId,
            ], 200);
        }
    }

    /**
     * Paga el SALDO con tarjeta (Culqi): token de un solo uso -> cargo -> reserva
     * pagada. Solo para links purpose=balance. El monto sale SIEMPRE del link
     * (que a su vez debe coincidir con el saldo de la reserva), nunca del request.
     */
    public function culqiCharge(Request $request, string $code): JsonResponse
    {
        $locale = $this->resolveLocale($request);

        if (! CheckoutInfo::paymentMethods()['culqi']) {
            return response()->json(['success' => false, 'message' => __('checkout_culqi.unavailable')], 503);
        }

        $validated = $request->validate([
            'culqi_token' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_]+$/'],
            'customer_email' => ['nullable', 'email', 'max:255'],
        ], [
            'culqi_token.required' => __('checkout_culqi.token_missing'),
            'culqi_token.regex' => __('checkout_culqi.token_missing'),
        ]);

        $link = PaymentLink::with('booking')->where('code', $code)->first();

        if (! $link || ! $link->isBalance()) {
            return response()->json(['success' => false, 'message' => __('payment_links.not_found')], 404);
        }

        $link->checkAndExpire();
        $link->refresh();

        if (! $link->isUsable()) {
            return response()->json(['success' => false, 'message' => $this->unavailableMessage($link)], 422);
        }

        if (! $link->balanceIsPayable()) {
            return response()->json(['success' => false, 'message' => $this->unavailableMessage($link, 'balance_changed')], 422);
        }

        // Un solo cargo en vuelo por link (doble clic / dos pestañas).
        $mutex = Cache::lock('culqi-link:'.$link->id, 90);
        if (! $mutex->get()) {
            return response()->json(['success' => false, 'message' => __('checkout_culqi.in_progress')], 409);
        }

        $chargeId = null;
        $booking = $link->booking;

        try {
            // Re-lectura bajo el mutex: otra request pudo pagarlo mientras esperaba.
            $link->refresh();
            $link->load('booking');

            if (! $link->isUsable() || ! $link->balanceIsPayable()) {
                return response()->json(['success' => false, 'message' => $this->unavailableMessage($link, 'balance_changed')], 422);
            }

            $amountCents = PaymentPlan::toCents($link->amount);
            $email = $booking->customer_email ?: ($validated['customer_email'] ?? null);

            if ($amountCents <= 0 || ! $email) {
                return response()->json(['success' => false, 'message' => __('checkout_culqi.charge_failed')], 422);
            }

            $charge = $this->culqi->createCharge([
                'amount' => $amountCents,
                'currency_code' => 'USD',
                'email' => $email,
                'source_id' => $validated['culqi_token'],
                'capture' => true,
                'description' => mb_substr('Lima View Tours - saldo '.$booking->reference, 0, 80),
                'metadata' => [
                    'locale' => $locale,
                    'kind' => 'balance',
                    'booking' => $booking->reference,
                    'payment_link' => $this->maskCode($link->code),
                    'charged_now' => number_format(PaymentPlan::fromCents($amountCents), 2, '.', ''),
                ],
            ]);

            $chargeId = (string) ($charge['id'] ?? '');
            $outcome = $charge['outcome']['type'] ?? null;

            if ($chargeId === '' || $outcome !== 'venta_exitosa' || ($charge['action_code'] ?? null) === 'REVIEW') {
                Log::warning('payment_link.culqi_charge.not_approved', [
                    'code' => $this->maskCode($code),
                    'charge_id' => $chargeId,
                    'outcome' => $outcome,
                ]);
                $chargeId = null;

                return response()->json([
                    'success' => false,
                    'code' => 'card_declined',
                    'message' => __('checkout_culqi.declined.generic'),
                ], 402);
            }

            // Dinero cobrado: constancia durable AHORA (mismo patrón M-3 que PayPal).
            DB::transaction(function () use ($link, $chargeId, $booking) {
                $fresh = PaymentLink::where('id', $link->id)->lockForUpdate()->first();
                $fresh->forceFill([
                    'culqi_charge_id' => $chargeId,
                    'paid_at' => now(),
                    'status' => 'paid',
                    'buyer_name' => $booking->customer_name,
                    'buyer_email' => $booking->customer_email,
                    'buyer_phone' => $booking->customer_phone,
                ])->save();
            });

            return $this->finishBalancePayment($link->refresh(), 'culqi', $chargeId, (string) $booking->customer_email, $locale, false);

        } catch (CulqiChargeDeclinedException $e) {
            Log::info('payment_link.culqi_charge.declined', [
                'code' => $this->maskCode($code),
                'decline_code' => $e->declineCode,
            ]);

            $key = 'checkout_culqi.declined.'.($e->declineCode ?? '');
            $message = ($e->declineCode && Lang::has($key)) ? __($key) : __('checkout_culqi.declined.generic');

            return response()->json(['success' => false, 'code' => 'card_declined', 'message' => $message], 402);

        } catch (\Throwable $e) {
            Log::error('payment_link.culqi_charge.error', [
                'code' => $this->maskCode($code),
                'charge_id' => $chargeId,
                'message' => $e->getMessage(),
            ]);

            if ($chargeId !== null) {
                // Cobrado pero no registrado: rastro aislado + aviso al admin. NO invita a reintentar.
                PaymentLink::where('id', $link->id)->update([
                    'culqi_charge_id' => $chargeId,
                    'paid_at' => $link->paid_at ?? now(),
                    'status' => 'paid',
                ]);
                $this->notifyAdminOfCaptureWriteFailure($link->code, null, $chargeId, $e);

                return response()->json([
                    'success' => false,
                    'code' => 'payment_captured_booking_pending',
                    'message' => __('payment_links.captured_booking_pending', ['reference' => $chargeId]),
                    'reference' => $chargeId,
                ], 200);
            }

            return response()->json(['success' => false, 'message' => __('checkout_culqi.charge_failed')], 500);

        } finally {
            $mutex->release();
        }
    }

    /**
     * Cierre común (PayPal y Culqi) del pago de un link de SALDO: liquida la
     * reserva LIGADA — nunca crea una reserva nueva. Idempotente: un replay
     * (doble clic, reintento de red) no vuelve a registrar ni a notificar.
     *
     * @param  string  $type  paypal | culqi
     */
    private function finishBalancePayment(PaymentLink $link, string $type, string $reference, string $payerEmail, string $locale, bool $alreadyPaid): JsonResponse
    {
        $booking = $link->booking_id ? Booking::find($link->booking_id) : null;

        if (! $booking) {
            // El link dice pagado pero no hay reserva a la que aplicarlo: revisión manual.
            Log::error('payment_link.balance.booking_missing', ['code' => $this->maskCode($link->code), 'reference' => $reference]);
            $this->notifyAdminOfCaptureWriteFailure($link->code, $link->paypal_order_id, $reference, new \RuntimeException('El link de saldo no tiene reserva asociada.'));

            return response()->json([
                'success' => false,
                'code' => 'payment_captured_booking_pending',
                'message' => __('payment_links.captured_booking_pending', ['reference' => $reference]),
                'reference' => $reference,
            ], 200);
        }

        try {
            $settledNow = $this->balance->settle($booking, $type, $reference, (float) $link->amount, $link);
        } catch (\Throwable $e) {
            // El dinero YA se cobró y no se pudo registrar: sin reintento seguro.
            Log::error('payment_link.balance.settle_failed_after_charge', [
                'code' => $this->maskCode($link->code),
                'booking' => $booking->reference,
                'reference' => $reference,
                'message' => $e->getMessage(),
            ]);
            $this->notifyAdminOfCaptureWriteFailure($link->code, $link->paypal_order_id, $reference, $e);

            return response()->json([
                'success' => false,
                'code' => 'payment_captured_booking_pending',
                'message' => __('payment_links.captured_booking_pending', ['reference' => $reference]),
                'reference' => $reference,
            ], 200);
        }

        if ($settledNow) {
            Log::info('payment_link.balance_paid', [
                'code' => $this->maskCode($link->code),
                'booking' => $booking->reference,
                'reference' => $reference,
                'type' => $type,
            ]);

            $this->balance->notifyBalancePaid($booking);
        }

        // B-7: un replay con el code de OTRA persona no debe cargar la reserva
        // ajena en la sesión de quien pregunta (la página de gracias muestra PII).
        // Quien acaba de pagar (settledNow) es el dueño de la sesión; en un replay se exige el correo de la reserva.
        $sameOwner = $settledNow || ($payerEmail !== '' && strcasecmp(trim($payerEmail), (string) $booking->customer_email) === 0);

        if ($sameOwner) {
            session()->put('last_bookings', collect([$booking->fresh()])->toArray());
            session()->put('last_payment', [
                'kind' => 'balance',
                'type' => $type,
                'mode' => 'balance',
                'amount' => (float) $link->amount,
                'balance' => 0.0,
                'total' => (float) $booking->total_price,
                'currency' => 'USD',
                'reference' => $reference,
            ]);
            // Un replay no re-dispara el evento de conversión.
            session()->put('conversion_pending', $settledNow);
        } else {
            Log::warning('payment_link.capture.replay_email_mismatch', [
                'code' => $this->maskCode($link->code),
                'booking_id' => $booking->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'redirect' => route('checkout.thanks', ['locale' => $locale]),
        ]);
    }

    /**
     * Texto claro para cada motivo de "no disponible", compartido por
     * show() (render) y create/captureOrder() (JSON). $link puede ser null
     * (código inexistente).
     */
    private function unavailableMessage(?PaymentLink $link, ?string $reason = null): string
    {
        if (! $link) {
            return __('payment_links.not_found');
        }

        return match (true) {
            $reason === 'order_mismatch', $reason === 'amount_mismatch' => __('booking.payment_failed'),
            // Link de saldo cuya reserva ya está pagada => "ya pagado"; si solo cambió el monto => aviso de saldo cambiado.
            $reason === 'balance_changed' && $link->booking?->payment_status === 'paid' => __('payment_links.already_paid'),
            $reason === 'balance_changed' => __('payment_links.balance_changed'),
            $link->status === 'paid' => __('payment_links.already_paid'),
            $link->status === 'cancelled' => __('payment_links.cancelled'),
            $link->status === 'expired' || $link->isPastExpiry() => __('payment_links.expired'),
            default => __('payment_links.not_available'),
        };
    }

    /**
     * Detecta el idioma preferido del navegador, igual que la redirección
     * de '/' en routes/web.php — esta ruta vive FUERA del grupo {locale}
     * (un link de pago es un único enlace compartible, no traducido por URL).
     */
    private function resolveLocale(Request $request, ?PaymentLink $link = null): string
    {
        $supported = config('app.supported_locales', ['es', 'en', 'pt']);

        // 1) ?lang= explícito (solo valores soportados). La página lo añade a
        //    las URLs de createOrder/captureOrder para que el POST use el
        //    MISMO idioma que se mostró, sin recalcularlo por navegador.
        $lang = $request->query('lang');
        if (is_string($lang) && in_array($lang, $supported, true)) {
            app()->setLocale($lang);

            return $lang;
        }

        // 2) Idioma fijado por el admin en el link.
        if ($link === null) {
            $code = $request->route('code');
            $link = is_string($code) ? PaymentLink::where('code', $code)->first() : null;
        }
        if ($link && is_string($link->locale) && in_array($link->locale, $supported, true)) {
            app()->setLocale($link->locale);

            return $link->locale;
        }

        // 3) Accept-Language del navegador, 4) fallback.
        $preferred = $request->getPreferredLanguage($supported);

        if (! $preferred) {
            $rawLang = substr($request->server('HTTP_ACCEPT_LANGUAGE', ''), 0, 2);
            $preferred = ($rawLang === 'pt') ? 'pt' : config('app.locale', 'es');
        }

        app()->setLocale($preferred);

        return $preferred;
    }

    /**
     * B-1: nunca el código completo del link en logs/analítica — ver
     * PaymentLink::maskCode().
     */
    private function maskCode(string $code): string
    {
        return PaymentLink::maskCode($code);
    }

    /**
     * N-2: deja constancia AISLADA (bajo lock, en su propia transacción) de
     * que PayPal capturó un pago que nuestro flujo síncrono no llegó a
     * registrar a tiempo — mismo patrón forense que M-3, reutilizado tanto
     * desde createOrder() (orden anterior encontrada ya COMPLETED) como
     * desde el catch de captureOrder() (getOrder() de recuperación).
     * Idempotente: si el link YA tiene ESTE mismo capture_id, no hace nada.
     */
    private function persistOrphanCapture(string $code, string $captureId): void
    {
        try {
            DB::transaction(function () use ($code, $captureId) {
                $link = PaymentLink::where('code', $code)->lockForUpdate()->first();

                if (! $link || $link->paypal_capture_id === $captureId) {
                    return;
                }

                // Query builder (update()), NO $link->save(): igual que el
                // escrito forense original de M-3, esta escritura tiene que
                // sobrevivir aunque el guardado NORMAL del modelo esté
                // fallando — que es justamente el escenario que la dispara.
                // ->save() dispararía 'saving' y heredaría cualquier motivo
                // por el que el guardado normal ya está fallando.
                PaymentLink::where('id', $link->id)->update([
                    'paypal_capture_id' => $captureId,
                    'paid_at' => $link->paid_at ?? now(),
                    // Conservador a propósito: es un camino de recuperación
                    // ante ambigüedad, no el flujo normal — se bloquea el
                    // link a que un humano lo revise, en vez de arriesgar un
                    // segundo cobro.
                    'status' => 'paid',
                ]);

                Log::warning('payment_link.orphan_capture_persisted', [
                    'code' => $this->maskCode($code),
                    'capture_id' => $captureId,
                ]);
            });
        } catch (\Throwable $inner) {
            Log::critical('payment_link.orphan_capture_persist_failed', [
                'code' => $this->maskCode($code),
                'capture_id' => $captureId,
                'message' => $inner->getMessage(),
            ]);
        }
    }

    /**
     * N-2: cuando captureOrder() revienta SIN haber llegado a asignar
     * $captureId (la llamada a PayPal::captureOrder() en sí falló o el
     * cliente nunca recibió la respuesta), no es seguro asumir que el cobro
     * NO ocurrió — un timeout puede ocurrir justo DESPUÉS de que PayPal ya
     * capturó. Se confirma el estado real con un getOrder() antes de decidir
     * "pago fallido". Devuelve null si la orden sigue sin estar COMPLETED, o
     * si el propio getOrder() también falla (red caída, etc. — en ese caso
     * "pago fallido" sigue siendo la respuesta más segura, no se asume éxito).
     */
    private function recoverCaptureIdFromPaypal(string $orderId): ?string
    {
        try {
            $order = $this->paypal->getOrder($orderId);
        } catch (\Throwable $e) {
            return null;
        }

        if (($order['status'] ?? null) !== 'COMPLETED') {
            return null;
        }

        return $order['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
    }

    /**
     * M-3: aviso al admin cuando PayPal ya cobró pero algo falló después
     * (escritura en BD o creación de la reserva). Reutiliza la misma
     * resolución de destinatarios que BookingNotifier usa para el aviso
     * normal de reserva nueva (Setting::get('booking_notification_email')).
     * Best-effort: un fallo de correo se loguea pero no cambia la respuesta
     * ya decidida al comprador.
     */
    private function notifyAdminOfCaptureWriteFailure(string $code, ?string $orderId, ?string $captureId, \Throwable $e): void
    {
        try {
            $recipients = $this->notifier->adminRecipients();

            if (empty($recipients)) {
                return;
            }

            Mail::raw(
                "Un cobro de PayPal para el link de pago {$this->maskCode($code)} se capturó correctamente "
                ."(capture_id: {$captureId}, order_id: {$orderId}), pero la reserva no se pudo confirmar "
                ."automáticamente. Motivo técnico: {$e->getMessage()}\n\n"
                .'Revisa el link en /admin/payment-links y, si hace falta, crea la reserva a mano desde el panel. '
                .'NO se debe volver a cobrar: el pago ya está registrado en PayPal.',
                function ($m) use ($recipients) {
                    $m->to($recipients)->subject('[Acción requerida] Cobro capturado sin reserva — Lima View Tours');
                }
            );
        } catch (\Throwable $mailException) {
            Log::warning('payment_link.capture.admin_alert_failed', [
                'code' => $this->maskCode($code),
                'message' => $mailException->getMessage(),
            ]);
        }
    }
}
