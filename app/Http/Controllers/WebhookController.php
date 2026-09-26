<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\PaymentLink;
use App\Models\Setting;
use App\Services\PaymentService;
use App\Services\PayPalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * M-4: límite de tamaño del cuerpo ANTES de decodificar nada — el
     * webhook no requiere autenticación previa (la firma se verifica DESPUÉS
     * de leer el body), así que cualquiera puede mandar un POST con un
     * payload enorme; se corta temprano con 413.
     */
    private const MAX_PAYLOAD_BYTES = 65536; // 64 KB

    public function __construct(
        private readonly PaymentService $payment,
        private readonly PayPalService $paypal,
    ) {}

    /**
     * Handle incoming Culqi webhook events.
     *
     * Events handled:
     *  - charge.succeeded
     *  - charge.failed
     */
    public function culqi(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Culqi-Signature', '');

        // Validate HMAC signature
        if (! $this->payment->verifyWebhookSignature($payload, $signature)) {
            Log::warning('webhook.culqi.invalid_signature', [
                'ip' => $request->ip(),
                'signature' => $signature,
            ]);

            abort(400, 'Invalid webhook signature.');
        }

        $event = json_decode($payload, true);
        $eventType = $event['type'] ?? null;
        $chargeId = $event['data']['id'] ?? null;

        Log::info('webhook.culqi.received', [
            'type' => $eventType,
            'charge_id' => $chargeId,
        ]);

        match ($eventType) {
            'charge.succeeded' => $this->handleChargeSucceeded($chargeId),
            'charge.failed' => $this->handleChargeFailed($chargeId),
            default => Log::info('webhook.culqi.unhandled_event', ['type' => $eventType]),
        };

        return response()->json(['received' => true]);
    }

    /**
     * Handle incoming PayPal webhook events. Red de seguridad para cuando el
     * comprador cierra la ventana o pierde la conexión justo después de que
     * nuestro servidor confirmó el cobro pero ANTES de terminar de crear la
     * reserva (ver PaymentLinkController::captureOrder() y
     * CheckoutController::paypalCaptureOrder(), ambos dejan un rastro de
     * "capturado, reserva pendiente" para este escenario). Cubre el checkout
     * normal (Booking.payment_reference = capture_id) Y los links de pago
     * (PaymentLink.paypal_capture_id/buyer_*), sin duplicar ninguna reserva.
     *
     * Firma verificada SIEMPRE contra la API oficial verify-webhook-signature
     * (nunca localmente): si falta el Webhook ID o la verificación falla por
     * cualquier motivo (red, credenciales, respuesta inesperada, Cert-Url
     * sospechoso), se rechaza — fail-closed, igual que
     * PaymentService::verifyWebhookSignature() para Culqi.
     *
     * M-4 (endurecimiento): límite de tamaño de payload (413), validación
     * del host de Paypal-Cert-Url ANTES de llamar a PayPal, y throttle en la
     * ruta (ver routes/web.php).
     *
     * Events handled:
     *  - PAYMENT.CAPTURE.COMPLETED
     *  - PAYMENT.CAPTURE.DENIED
     *  - PAYMENT.CAPTURE.REFUNDED
     */
    public function paypal(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        if (strlen($payload) > self::MAX_PAYLOAD_BYTES) {
            Log::warning('webhook.paypal.payload_too_large', [
                'bytes' => strlen($payload),
                'ip' => $request->ip(),
            ]);

            abort(413, 'Payload too large.');
        }

        $event = json_decode($payload, true);

        if (! $this->verifyPaypalSignature($request, $event, $payload)) {
            Log::warning('webhook.paypal.invalid_signature', ['ip' => $request->ip()]);

            abort(400, 'Invalid webhook signature.');
        }

        $eventType = $event['event_type'] ?? null;
        $resource = $event['resource'] ?? [];

        Log::info('webhook.paypal.received', [
            'type' => $eventType,
            'resource_id' => $resource['id'] ?? null,
        ]);

        match ($eventType) {
            'PAYMENT.CAPTURE.COMPLETED' => $this->handlePaypalCaptureCompleted($resource),
            'PAYMENT.CAPTURE.DENIED' => $this->handlePaypalCaptureDenied($resource),
            'PAYMENT.CAPTURE.REFUNDED' => $this->handlePaypalCaptureRefunded($resource),
            default => Log::info('webhook.paypal.unhandled_event', ['type' => $eventType]),
        };

        return response()->json(['received' => true]);
    }

    /**
     * Verifica la firma contra la API oficial de PayPal
     * (v1/notifications/verify-webhook-signature). Fail-closed: cualquier
     * cosa que no sea un "SUCCESS" explícito de PayPal rechaza el webhook —
     * cabecera faltante, Webhook ID sin configurar, Cert-Url que no sea un
     * host *.paypal.com por HTTPS, error de red, o una respuesta que no se
     * pudo interpretar.
     *
     * `$rawPayload` es el cuerpo EXACTO recibido (bytes tal cual, sin
     * decode/encode de por medio): PayPal firma esos bytes, así que
     * reenviar `webhook_event` re-serializado desde el array PHP (orden de
     * claves distinto, escapes distintos) puede hacer que la verificación
     * rechace eventos reales. Se concatena como JSON crudo dentro del body
     * de la llamada a PayPal, nunca como un array que Laravel re-codifique.
     */
    private function verifyPaypalSignature(Request $request, mixed $event, string $rawPayload): bool
    {
        $webhookId = Setting::get('paypal_webhook_id') ?: config('services.paypal.webhook_id');

        if (empty($webhookId)) {
            Log::warning('webhook.paypal.webhook_id_not_configured');

            return false;
        }

        if (! is_array($event)) {
            return false;
        }

        $transmissionId = $request->header('Paypal-Transmission-Id');
        $transmissionTime = $request->header('Paypal-Transmission-Time');
        $certUrl = $request->header('Paypal-Cert-Url');
        $authAlgo = $request->header('Paypal-Auth-Algo');
        $transmissionSig = $request->header('Paypal-Transmission-Sig');

        if (! $transmissionId || ! $transmissionTime || ! $certUrl || ! $authAlgo || ! $transmissionSig) {
            Log::warning('webhook.paypal.missing_headers');

            return false;
        }

        if (! $this->isTrustedPaypalCertUrl($certUrl)) {
            Log::warning('webhook.paypal.untrusted_cert_url', ['cert_url' => $certUrl]);

            return false;
        }

        try {
            // N-5: cabeceras con bytes UTF-8 inválidos hacían que
            // json_encode() devolviera `false` EN SILENCIO (sin
            // JSON_THROW_ON_ERROR), dejando el body malformado
            // ("transmission_id":,...) — PayPal lo iba a rechazar de todos
            // modos, pero recién DESPUÉS de gastar una llamada saliente (el
            // access token + la propia verificación). Con
            // JSON_THROW_ON_ERROR se detecta ANTES, en este bloque propio,
            // sin haber llamado a PayPal todavía.
            //
            // Cuerpo armado a mano: todos los campos van con json_encode()
            // salvo webhook_event, que es el body CRUDO recibido (ya es un
            // string JSON válido — se validó arriba con is_array($event))
            // insertado tal cual, sin volver a codificarlo.
            $body = sprintf(
                '{"transmission_id":%s,"transmission_time":%s,"cert_url":%s,"auth_algo":%s,"transmission_sig":%s,"webhook_id":%s,"webhook_event":%s}',
                json_encode($transmissionId, JSON_THROW_ON_ERROR),
                json_encode($transmissionTime, JSON_THROW_ON_ERROR),
                json_encode($certUrl, JSON_THROW_ON_ERROR),
                json_encode($authAlgo, JSON_THROW_ON_ERROR),
                json_encode($transmissionSig, JSON_THROW_ON_ERROR),
                json_encode($webhookId, JSON_THROW_ON_ERROR),
                $rawPayload
            );
        } catch (\JsonException $e) {
            Log::warning('webhook.paypal.invalid_header_encoding', ['message' => $e->getMessage()]);

            return false;
        }

        try {
            $response = Http::withToken($this->paypal->accessToken())
                ->acceptJson()
                ->withBody($body, 'application/json')
                ->post("{$this->paypal->baseUrl()}/v1/notifications/verify-webhook-signature");

            if ($response->failed()) {
                Log::warning('webhook.paypal.verify_signature_call_failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return $response->json('verification_status') === 'SUCCESS';
        } catch (\Throwable $e) {
            Log::warning('webhook.paypal.verify_signature_exception', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * M-4: Paypal-Cert-Url debe ser https y un host *.paypal.com — antes de
     * hacerle ninguna llamada a PayPal con lo que venga en la cabecera.
     */
    private function isTrustedPaypalCertUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! $parts || ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');

        return (bool) preg_match('/^([a-z0-9-]+\.)*paypal\.com$/', $host);
    }

    private function handlePaypalCaptureCompleted(array $resource): void
    {
        $captureId = $resource['id'] ?? null;
        $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;

        if (! $captureId) {
            Log::warning('webhook.paypal.capture_completed.missing_id');

            return;
        }

        // 1) Checkout normal Y links YA reservados: Booking.payment_reference
        //    = capture_id en ambos flujos (ver PaymentLinkController y
        //    CheckoutController::finalizeBookings).
        $bookings = Booking::where('payment_reference', $captureId)->get();

        if ($bookings->isNotEmpty()) {
            foreach ($bookings as $booking) {
                if ($booking->payment_status === 'paid') {
                    Log::info('webhook.paypal.capture_completed.skipped_idempotent', [
                        'booking_id' => $booking->id,
                    ]);

                    continue;
                }

                $booking->update(['payment_status' => 'paid', 'status' => 'confirmed']);

                Log::info('webhook.paypal.capture_completed.booking_updated', [
                    'booking_id' => $booking->id,
                    'capture_id' => $captureId,
                ]);
            }

            // N-1 (decisión del coordinador): si alguna de estas reservas
            // viene de un link de pago, hay que enlazarla de vuelta aunque
            // PaymentLink.booking_id se haya quedado sin escribir (p.ej. el
            // forceFill() de PaymentLinkController::captureOrder(), paso 2,
            // creó la Booking pero falló DESPUÉS, al guardar booking_id en
            // el link). Sin esto, el link queda huérfano para siempre —
            // "sin reserva" en el panel — aunque la reserva SÍ exista y
            // esté pagada. whereNull('booking_id') hace el update seguro
            // sin lock: si dos llamadas compiten, la segunda actualiza 0
            // filas porque la primera ya lo dejó sin null.
            $linkBooking = $bookings->firstWhere('payment_method', 'payment_link');

            if ($linkBooking) {
                PaymentLink::where(function ($q) use ($captureId, $orderId) {
                    $q->where('paypal_capture_id', $captureId);
                    if ($orderId) {
                        $q->orWhere('paypal_order_id', $orderId);
                    }
                })
                    ->whereNull('booking_id')
                    ->update(['booking_id' => $linkBooking->id]);
            }

            return;
        }

        // 2) Link de pago capturado pero sin reserva creada todavía: el
        //    flujo síncrono (PaymentLinkController::captureOrder()) ya dejó
        //    paypal_capture_id/paid_at/buyer_* ANTES de intentar crear el
        //    Booking — si ese paso falló (o el comprador cerró la ventana
        //    justo ahí), este webhook completa lo que falta, sin volver a
        //    cobrar nada.
        //
        //    M-2: `capture_id` y `order_id` van agrupados en UN SOLO
        //    where(closure) — antes, `->where(capture)->orWhere(order)
        //    ->whereNull(booking_id)` compilaba a
        //    `capture = ? OR (order = ? AND booking_id IS NULL)`: si el
        //    link coincidía por capture_id, NO se exigía booking_id nulo.
        //    Además, todo el bloque corre bajo lockForUpdate() sobre la
        //    MISMA fila que usa PaymentLinkController::captureOrder(), y se
        //    re-chequea booking_id DESPUÉS de tomar el lock — así, si las
        //    dos rutas llegan casi a la vez, la segunda en tomar el lock ve
        //    el booking_id que la primera ya escribió y no crea una
        //    segunda reserva.
        // N-3: la notificación al comprador/admin ya NO se envía DENTRO de
        // esta transacción — con QUEUE_CONNECTION=sync, un SMTP lento
        // retenía el lockForUpdate() de abajo hasta el timeout de MySQL. La
        // transacción devuelve solo los datos necesarios para notificar
        // DESPUÉS de haber soltado el lock (mismo patrón que
        // PaymentLinkController::captureOrder(), paso 2).
        $reconciliation = DB::transaction(function () use ($captureId, $orderId, $resource): ?array {
            $link = PaymentLink::where(function ($q) use ($captureId, $orderId) {
                $q->where('paypal_capture_id', $captureId);
                if ($orderId) {
                    $q->orWhere('paypal_order_id', $orderId);
                }
            })
                ->lockForUpdate()
                ->first();

            if (! $link) {
                Log::info('webhook.paypal.capture_completed.no_match', [
                    'capture_id' => $captureId,
                    'order_id' => $orderId,
                ]);

                return null;
            }

            if ($link->booking_id) {
                Log::info('webhook.paypal.capture_completed.already_reconciled', [
                    'payment_link_id' => $link->id,
                    'booking_id' => $link->booking_id,
                ]);

                return null;
            }

            // N-1 (decisión del coordinador): el re-chequeo anti-duplicado
            // no se fía SOLO de $link->booking_id — también pregunta si YA
            // existe una Booking con esta misma referencia de cobro (por
            // ejemplo, si el paso síncrono la creó y el forceFill() que
            // enlaza booking_id en el link no llegó a correr). Si existe,
            // se enlaza y no se notifica de nuevo (ya se notificó cuando se
            // creó por primera vez).
            $existingBooking = Booking::where('payment_reference', $captureId)->first();

            if ($existingBooking) {
                $link->forceFill(['booking_id' => $existingBooking->id])->save();

                Log::info('webhook.paypal.capture_completed.link_booking_matched_by_reference', [
                    'payment_link_id' => $link->id,
                    'booking_id' => $existingBooking->id,
                    'capture_id' => $captureId,
                ]);

                return null;
            }

            // B-2: el importe/moneda capturados deben coincidir con el
            // link. Hoy la orden solo la crea nuestro propio servidor con
            // el monto del link (ver PaymentLinkController::createOrder()),
            // así que esto no debería divergir nunca en el flujo normal —
            // pero si alguien edita el monto del link entre la creación de
            // la orden y el cobro, o el cobro entra por otra vía, no se
            // reserva sin revisión manual.
            $capturedAmount = $resource['amount']['value'] ?? null;
            $capturedCurrency = $resource['amount']['currency_code'] ?? null;
            $amountMatches = $capturedAmount !== null
                && number_format((float) $capturedAmount, 2, '.', '') === number_format((float) $link->amount, 2, '.', '');
            $currencyMatches = $capturedCurrency !== null && strtoupper($capturedCurrency) === 'USD';

            if (! $amountMatches || ! $currencyMatches) {
                Log::error('webhook.paypal.capture_completed.amount_mismatch', [
                    'payment_link_id' => $link->id,
                    'capture_id' => $captureId,
                    'expected_amount' => (string) $link->amount,
                    'received_amount' => $capturedAmount,
                    'received_currency' => $capturedCurrency,
                ]);

                return null;
            }

            if (! $link->buyer_email) {
                // No hay a quién asignarle la reserva (el paso 1 síncrono
                // nunca llegó a guardar los datos del comprador). Queda
                // para revisión manual — el rastro completo está en los
                // logs de PaymentLinkController y en esta fila
                // (paypal_capture_id/paid_at).
                Log::error('webhook.paypal.capture_completed.link_missing_customer_data', [
                    'payment_link_id' => $link->id,
                    'code' => $link->maskedCode(),
                    'capture_id' => $captureId,
                ]);

                return null;
            }

            try {
                $creator = app(\App\Services\BookingCreationService::class);

                $item = [
                    'tour_id' => $link->tour_id,
                    'title_snapshot' => $link->tour?->title_es ?? __('payment_links.generic_tour_name'),
                    'travel_date' => $link->travel_date?->toDateString(),
                    'adults' => $link->adults,
                    'children' => $link->children,
                    'unit_price' => round(((float) $link->amount) / max(1, $link->total_pax), 2),
                    'subtotal' => (float) $link->amount,
                ];

                $customer = [
                    'customer_name' => $link->buyer_name,
                    'customer_email' => $link->buyer_email,
                    'customer_phone' => $link->buyer_phone,
                    'travel_date' => $item['travel_date'],
                    'pickup_point' => null,
                    'pickup_detail' => null,
                ];

                // PaymentLink no guarda el idioma del comprador (el
                // formulario de /pagar/{code} no lo pide); 'es' es el
                // default del sitio.
                $bookings = $creator->createBookings(collect([$item]), $customer, 'payment_link', $captureId, true, 'es');
                $booking = $bookings->first();

                $link->forceFill(['booking_id' => $booking->id])->save();

                Log::info('webhook.paypal.capture_completed.link_booking_reconciled', [
                    'payment_link_id' => $link->id,
                    'booking_id' => $booking->id,
                    'capture_id' => $captureId,
                ]);

                return ['bookings' => $bookings, 'customer_email' => $customer['customer_email']];
            } catch (\Throwable $e) {
                Log::error('webhook.paypal.capture_completed.link_reconcile_failed', [
                    'payment_link_id' => $link->id,
                    'capture_id' => $captureId,
                    'message' => $e->getMessage(),
                ]);

                return null;
            }
        });

        if ($reconciliation) {
            app(\App\Services\BookingNotifier::class)->send(
                $reconciliation['bookings'], true, $reconciliation['customer_email']
            );
        }
    }

    private function handlePaypalCaptureDenied(array $resource): void
    {
        $captureId = $resource['id'] ?? null;

        if (! $captureId) {
            Log::warning('webhook.paypal.capture_denied.missing_id');

            return;
        }

        $bookings = Booking::where('payment_reference', $captureId)->get();

        foreach ($bookings as $booking) {
            if ($booking->payment_status === 'failed') {
                continue;
            }

            $booking->update(['payment_status' => 'failed']);

            Log::info('webhook.paypal.capture_denied.booking_updated', ['booking_id' => $booking->id]);
        }

        // B-4: un link cuya captura termina DENIED (raro, pero posible si
        // el capture inicial queda pendiente de revisión de fraude en
        // PayPal y luego se rechaza) no debe quedar marcado como pagado.
        $links = PaymentLink::where('paypal_capture_id', $captureId)->get();

        foreach ($links as $link) {
            if ($link->status === 'denied') {
                continue;
            }

            $link->update(['status' => 'denied']);

            Log::info('webhook.paypal.capture_denied.link_updated', ['payment_link_id' => $link->id]);
        }

        if ($bookings->isEmpty() && $links->isEmpty()) {
            Log::info('webhook.paypal.capture_denied.no_match', ['capture_id' => $captureId]);
        }
    }

    private function handlePaypalCaptureRefunded(array $resource): void
    {
        // El recurso de un reembolso no trae el capture_id como campo plano:
        // viene en el link `rel=up` (`/v2/payments/captures/{id}`).
        $captureId = null;

        foreach ($resource['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'up') {
                $captureId = basename((string) parse_url($link['href'] ?? '', PHP_URL_PATH));
                break;
            }
        }

        if (! $captureId) {
            Log::warning('webhook.paypal.capture_refunded.missing_capture_id', ['refund_id' => $resource['id'] ?? null]);

            return;
        }

        $bookings = Booking::where('payment_reference', $captureId)->get();

        foreach ($bookings as $booking) {
            if ($booking->payment_status === 'refunded') {
                continue;
            }

            $booking->update(['payment_status' => 'refunded']);

            Log::info('webhook.paypal.capture_refunded.booking_updated', ['booking_id' => $booking->id]);
        }

        // B-4: reflejar el reembolso en el link — hoy seguía marcado
        // "Pagado" en el panel para siempre, aunque el dinero ya se hubiera
        // devuelto.
        $links = PaymentLink::where('paypal_capture_id', $captureId)->get();

        foreach ($links as $link) {
            if ($link->status === 'refunded') {
                continue;
            }

            $link->update(['status' => 'refunded']);

            Log::info('webhook.paypal.capture_refunded.link_updated', ['payment_link_id' => $link->id]);
        }

        if ($bookings->isEmpty() && $links->isEmpty()) {
            Log::info('webhook.paypal.capture_refunded.no_bookings', ['capture_id' => $captureId]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Private event handlers (Culqi)
    // ─────────────────────────────────────────────────────────────

    private function handleChargeSucceeded(?string $chargeId): void
    {
        if (! $chargeId) {
            Log::warning('webhook.culqi.charge_succeeded.missing_id');

            return;
        }

        $bookings = Booking::where('payment_reference', $chargeId)->get();

        if ($bookings->isEmpty()) {
            Log::warning('webhook.culqi.charge_succeeded.no_bookings', ['charge_id' => $chargeId]);

            return;
        }

        foreach ($bookings as $booking) {
            // Idempotent: skip if already processed
            if ($booking->payment_status === 'paid') {
                Log::info('webhook.culqi.charge_succeeded.skipped_idempotent', [
                    'booking_id' => $booking->id,
                    'reference' => $booking->reference,
                ]);

                continue;
            }

            $booking->update([
                'payment_status' => 'paid',
                'status' => 'confirmed',
            ]);

            Log::info('webhook.culqi.charge_succeeded.updated', [
                'booking_id' => $booking->id,
                'reference' => $booking->reference,
                'charge_id' => $chargeId,
            ]);
        }
    }

    private function handleChargeFailed(?string $chargeId): void
    {
        if (! $chargeId) {
            Log::warning('webhook.culqi.charge_failed.missing_id');

            return;
        }

        $bookings = Booking::where('payment_reference', $chargeId)->get();

        if ($bookings->isEmpty()) {
            Log::warning('webhook.culqi.charge_failed.no_bookings', ['charge_id' => $chargeId]);

            return;
        }

        foreach ($bookings as $booking) {
            // Idempotent: skip if already in final failed state
            if ($booking->payment_status === 'failed') {
                Log::info('webhook.culqi.charge_failed.skipped_idempotent', [
                    'booking_id' => $booking->id,
                ]);

                continue;
            }

            $booking->update(['payment_status' => 'failed']);

            Log::info('webhook.culqi.charge_failed.updated', [
                'booking_id' => $booking->id,
                'reference' => $booking->reference,
                'charge_id' => $chargeId,
            ]);
        }
    }
}
