<?php

namespace App\Services;

use App\Exceptions\PayPalCardDeclinedException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    private string $clientId;

    private string $secret;

    private string $mode;

    public function __construct()
    {
        // Las credenciales son administrables desde el panel (Configuración → Pagos);
        // si no están en la BD, se usa el valor del .env como respaldo.
        $this->clientId = (string) (\App\Models\Setting::get('paypal_client_id') ?: config('services.paypal.client_id') ?: '');
        $this->secret = (string) (\App\Models\Setting::get('paypal_secret') ?: config('services.paypal.secret') ?: '');
        $this->mode = (string) (\App\Models\Setting::get('paypal_mode') ?: config('services.paypal.mode') ?: 'sandbox');
    }

    /**
     * Returns the PayPal API base URL based on the configured mode.
     */
    public function baseUrl(): string
    {
        return $this->mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Retrieves a cached OAuth 2.0 access token from PayPal.
     * Token is cached for ~8 hours (28800 seconds).
     *
     * @throws \RuntimeException when the token request fails
     */
    public function accessToken(): string
    {
        $cacheKey = 'paypal_access_token_'.$this->mode;

        return Cache::remember($cacheKey, 28800, function () {
            try {
                $response = Http::withBasicAuth($this->clientId, $this->secret)
                    ->asForm()
                    ->post("{$this->baseUrl()}/v1/oauth2/token", [
                        'grant_type' => 'client_credentials',
                    ]);

                if ($response->failed()) {
                    Log::error('paypal.access_token.failed', [
                        'status' => $response->status(),
                        'error' => $this->safeErrorSummary($response),
                    ]);

                    throw new \RuntimeException(
                        'PayPal token request failed: status '.$response->status().' '.json_encode($this->safeErrorSummary($response))
                    );
                }

                $token = $response->json('access_token');

                if (empty($token)) {
                    throw new \RuntimeException('PayPal returned an empty access token.');
                }

                Log::info('paypal.access_token.obtained', ['mode' => $this->mode]);

                return $token;

            } catch (\RuntimeException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::error('paypal.access_token.exception', ['message' => $e->getMessage()]);
                throw new \RuntimeException('PayPal token error: '.$e->getMessage(), 0, $e);
            }
        });
    }

    /**
     * Creates a PayPal order with intent=CAPTURE.
     *
     * @param  float  $amount  Total in USD (will be formatted to 2 decimals)
     * @param  string  $currency  Currency code, e.g. "USD"
     * @param  array  $metadata  Optional metadata stored under purchase_units[0].custom_id
     * @param  array  $payer  B2 (checkout normal ÚNICAMENTE — payment-links no manda
     *                        esto y queda sin cambios): payload `payer` de la Orders
     *                        v2 API ya armado por el llamador (ver
     *                        CheckoutController::buildPayer()), p.ej.
     *                        ['name' => ['given_name' => ..., 'surname' => ...],
     *                        'email_address' => ..., 'phone' => [...]]. Vacío = no
     *                        se agrega `payer` ni `application_context` al payload:
     *                        es exactamente el comportamiento de antes de este
     *                        cambio.
     * @return array Full PayPal order response (includes 'id')
     *
     * @throws \RuntimeException on API error
     */
    public function createOrder(float $amount, string $currency = 'USD', array $metadata = [], array $payer = []): array
    {
        try {
            $token = $this->accessToken();

            $payload = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => strtoupper($currency),
                            'value' => number_format($amount, 2, '.', ''),
                        ],
                        'custom_id' => isset($metadata['booking_references'])
                            ? (string) $metadata['booking_references']
                            : null,
                    ],
                ],
            ];

            // Remove null custom_id to keep the payload clean
            if ($payload['purchase_units'][0]['custom_id'] === null) {
                unset($payload['purchase_units'][0]['custom_id']);
            }

            // B2: prellena el formulario de invitado de PayPal con lo que el
            // cliente ya escribió en nuestro checkout (menos campos para
            // volver a teclear) y le dice a PayPal que esto NO es un pedido
            // con envío físico y que arranque directo en "pagar ahora" en
            // vez del resumen de carrito. Solo se agrega si el llamador
            // mandó algo en $payer — payment-links sigue sin tocar.
            //
            // `application_context` está deprecado en Orders v2, pero se
            // mantiene a propósito: ver docs/fixes/2026-10-05-backend-paypal-todas.md.
            if ($payer !== []) {
                $payload['payer'] = $payer;
                $payload['application_context'] = [
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                ];
            }

            $url = "{$this->baseUrl()}/v2/checkout/orders";
            $response = Http::withToken($token)->acceptJson()->post($url, $payload);

            // El payer es solo comodidad: si PayPal lo rechaza (4xx sobre
            // /payer), se reintenta UNA vez sin él, conservando el resto
            // del payload (importe y application_context). Nunca debe
            // dejar al cliente sin poder pagar.
            if ($response->failed() && isset($payload['payer']) && $this->isPayerRejection($response)) {
                Log::warning('paypal.create_order.payer_rejected_retrying_without_payer', [
                    'status' => $response->status(),
                    'error' => $this->safeErrorSummary($response),
                ]);

                unset($payload['payer']);
                $response = Http::withToken($token)->acceptJson()->post($url, $payload);
            }

            if ($response->failed()) {
                // Sin PII: el body de un 422 de PayPal puede traer details[].value
                // con el correo/teléfono/nombre enviados. Solo ids y códigos.
                $summary = $this->safeErrorSummary($response);

                Log::error('paypal.create_order.failed', [
                    'status' => $response->status(),
                    'error' => $summary,
                    'amount' => $amount,
                    'currency' => $currency,
                    'metadata' => $metadata,
                ]);

                throw new \RuntimeException(
                    'PayPal create order failed: status '.$response->status().' '.json_encode($summary)
                );
            }

            $order = $response->json();

            Log::info('paypal.create_order.success', [
                'order_id' => $order['id'] ?? null,
                'amount' => $amount,
                'currency' => $currency,
                'metadata' => $metadata,
            ]);

            return $order;

        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('paypal.create_order.exception', [
                'message' => $e->getMessage(),
                'metadata' => $metadata,
            ]);

            throw new \RuntimeException('PayPal connection error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * True when a 4xx from create-order points at the `payer` object
     * (details[].field starting with /payer, or "payer" in the field).
     */
    private function isPayerRejection(\Illuminate\Http\Client\Response $response): bool
    {
        if (! in_array($response->status(), [400, 422], true)) {
            return false;
        }

        foreach ((array) $response->json('details', []) as $detail) {
            $field = (string) ($detail['field'] ?? '');
            if ($field !== '' && str_contains($field, 'payer')) {
                return true;
            }
        }

        return false;
    }

    /**
     * PII-free view of a PayPal error response: name, debug_id and
     * details[].field/issue. Never `value`, `description` or the raw body.
     *
     * @return array{name: ?string, debug_id: ?string, details: array<int, array{field: ?string, issue: ?string}>}
     */
    private function safeErrorSummary(\Illuminate\Http\Client\Response $response): array
    {
        $details = [];
        foreach ((array) $response->json('details', []) as $detail) {
            $details[] = [
                'field' => isset($detail['field']) ? (string) $detail['field'] : null,
                'issue' => isset($detail['issue']) ? (string) $detail['issue'] : null,
            ];
        }

        return [
            'name' => $response->json('name') ?? $response->json('error'),
            'debug_id' => $response->json('debug_id'),
            'details' => $details,
        ];
    }

    /**
     * Retrieves the current state of a PayPal order (amount, currency,
     * status) WITHOUT capturing it. Used to verify server-side, right
     * before capturing, that the order PayPal has on file still matches
     * what we calculated when it was created — see
     * CheckoutController::paypalCaptureOrder().
     *
     * @param  string  $orderId  The PayPal order ID returned by createOrder()
     * @return array Full PayPal order response
     *
     * @throws \RuntimeException on API error
     */
    public function getOrder(string $orderId): array
    {
        try {
            $token = $this->accessToken();

            $response = Http::withToken($token)
                ->acceptJson()
                ->get("{$this->baseUrl()}/v2/checkout/orders/{$orderId}");

            if ($response->failed()) {
                Log::error('paypal.get_order.failed', [
                    'order_id' => $orderId,
                    'status' => $response->status(),
                    'error' => $this->safeErrorSummary($response),
                ]);

                throw new \RuntimeException(
                    'PayPal get order failed: status '.$response->status().' '.json_encode($this->safeErrorSummary($response))
                );
            }

            return $response->json();

        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('paypal.get_order.exception', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ]);

            throw new \RuntimeException('PayPal connection error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Captures (finalizes) an approved PayPal order.
     *
     * @param  string  $orderId  The PayPal order ID returned by createOrder()
     * @return array Full capture response
     *
     * @throws \RuntimeException if the capture fails or status is not COMPLETED
     */
    public function captureOrder(string $orderId): array
    {
        try {
            $token = $this->accessToken();

            // PayPal exige un objeto JSON ({} o vacío) en el capture; un array []
            // (lo que produce ->post($url, [])) responde 400 MALFORMED_REQUEST_JSON.
            //
            // N-2 (docs/payment-links/SECURITY.md): PayPal-Request-Id
            // derivado DETERMINÍSTICAMENTE del order_id — si nuestro propio
            // cliente HTTP reintenta el mismo capture() (timeout, retry
            // automático), PayPal reconoce la misma clave de idempotencia y
            // devuelve el resultado de la captura original en vez de
            // intentar cobrar una segunda vez.
            $response = Http::withToken($token)
                ->acceptJson()
                ->withHeaders(['PayPal-Request-Id' => $this->captureIdempotencyKey($orderId)])
                ->withBody('{}', 'application/json')
                ->post("{$this->baseUrl()}/v2/checkout/orders/{$orderId}/capture");

            if ($response->failed()) {
                $issue = $response->json('details.0.issue');

                Log::error('paypal.capture_order.failed', [
                    'order_id' => $orderId,
                    'status' => $response->status(),
                    'issue' => $issue,
                    'error' => $this->safeErrorSummary($response),
                ]);

                // INSTRUMENT_DECLINED: the buyer's bank/issuer rejected the
                // card at capture time. This is a buyer-side payment failure,
                // not a bug in our integration, so it gets its own exception
                // instead of the generic RuntimeException below — the
                // controller can then answer "try another card" instead of
                // "contact us".
                //
                // We deliberately do NOT fold PAYER_ACTION_REQUIRED into this
                // same bucket: PayPal's own remediation for that issue is to
                // redirect the buyer to the `payer-action` link to complete
                // an extra verification step (e.g. 3-D Secure), not to retry
                // with a different instrument. Telling the buyer to "try
                // another card" for that issue would be wrong. Implementing
                // that redirect flow is a separate feature, out of scope
                // here.
                if ($response->status() === 422 && $issue === 'INSTRUMENT_DECLINED') {
                    throw new PayPalCardDeclinedException($issue);
                }

                throw new \RuntimeException(
                    'PayPal capture failed: status '.$response->status().' '.json_encode($this->safeErrorSummary($response))
                );
            }

            $capture = $response->json();

            if (($capture['status'] ?? '') !== 'COMPLETED') {
                Log::warning('paypal.capture_order.not_completed', [
                    'order_id' => $orderId,
                    'returned_status' => $capture['status'] ?? 'unknown',
                ]);

                throw new \RuntimeException(
                    'PayPal capture status was not COMPLETED: '.($capture['status'] ?? 'unknown')
                );
            }

            Log::info('paypal.capture_order.success', [
                'order_id' => $orderId,
                'capture_id' => $capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? null,
            ]);

            return $capture;

        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('paypal.capture_order.exception', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ]);

            throw new \RuntimeException('PayPal connection error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Extracts the capture transaction ID from a completed capture response.
     */
    public function captureId(array $captureResponse): ?string
    {
        return $captureResponse['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
    }

    /**
     * N-2: clave de idempotencia para el header PayPal-Request-Id del
     * capture, derivada del order_id — el MISMO order_id siempre produce la
     * MISMA clave, así que un reintento del propio proceso (no un segundo
     * pago real: eso siempre trae un order_id distinto) no puede duplicar el
     * cobro en el lado de PayPal. Hasheada porque PayPal-Request-Id tiene un
     * límite de longitud; el hash de un mismo input siempre es el mismo.
     */
    private function captureIdempotencyKey(string $orderId): string
    {
        return substr(hash('sha256', 'payment-link-capture:'.$orderId), 0, 36);
    }
}
