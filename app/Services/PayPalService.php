<?php

namespace App\Services;

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
        $this->secret   = (string) (\App\Models\Setting::get('paypal_secret')    ?: config('services.paypal.secret') ?: '');
        $this->mode     = (string) (\App\Models\Setting::get('paypal_mode')      ?: config('services.paypal.mode') ?: 'sandbox');
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
        $cacheKey = 'paypal_access_token_' . $this->mode;

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
                        'body'   => $response->body(),
                    ]);

                    throw new \RuntimeException(
                        'PayPal token request failed: ' . $response->body()
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
                throw new \RuntimeException('PayPal token error: ' . $e->getMessage(), 0, $e);
            }
        });
    }

    /**
     * Creates a PayPal order with intent=CAPTURE.
     *
     * @param  float  $amount    Total in USD (will be formatted to 2 decimals)
     * @param  string $currency  Currency code, e.g. "USD"
     * @param  array  $metadata  Optional metadata stored under purchase_units[0].custom_id
     * @return array             Full PayPal order response (includes 'id')
     *
     * @throws \RuntimeException on API error
     */
    public function createOrder(float $amount, string $currency = 'USD', array $metadata = []): array
    {
        try {
            $token = $this->accessToken();

            $payload = [
                'intent'         => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount'    => [
                            'currency_code' => strtoupper($currency),
                            'value'         => number_format($amount, 2, '.', ''),
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

            $response = Http::withToken($token)
                ->acceptJson()
                ->post("{$this->baseUrl()}/v2/checkout/orders", $payload);

            if ($response->failed()) {
                Log::error('paypal.create_order.failed', [
                    'status'   => $response->status(),
                    'body'     => $response->body(),
                    'amount'   => $amount,
                    'currency' => $currency,
                    'metadata' => $metadata,
                ]);

                throw new \RuntimeException(
                    'PayPal create order failed: ' . $response->body()
                );
            }

            $order = $response->json();

            Log::info('paypal.create_order.success', [
                'order_id' => $order['id'] ?? null,
                'amount'   => $amount,
                'currency' => $currency,
                'metadata' => $metadata,
            ]);

            return $order;

        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('paypal.create_order.exception', [
                'message'  => $e->getMessage(),
                'metadata' => $metadata,
            ]);

            throw new \RuntimeException('PayPal connection error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Captures (finalizes) an approved PayPal order.
     *
     * @param  string $orderId  The PayPal order ID returned by createOrder()
     * @return array            Full capture response
     *
     * @throws \RuntimeException if the capture fails or status is not COMPLETED
     */
    public function captureOrder(string $orderId): array
    {
        try {
            $token = $this->accessToken();

            // PayPal exige un objeto JSON ({} o vacío) en el capture; un array []
            // (lo que produce ->post($url, [])) responde 400 MALFORMED_REQUEST_JSON.
            $response = Http::withToken($token)
                ->acceptJson()
                ->withBody('{}', 'application/json')
                ->post("{$this->baseUrl()}/v2/checkout/orders/{$orderId}/capture");

            if ($response->failed()) {
                Log::error('paypal.capture_order.failed', [
                    'order_id' => $orderId,
                    'status'   => $response->status(),
                    'body'     => $response->body(),
                ]);

                throw new \RuntimeException(
                    'PayPal capture failed: ' . $response->body()
                );
            }

            $capture = $response->json();

            if (($capture['status'] ?? '') !== 'COMPLETED') {
                Log::warning('paypal.capture_order.not_completed', [
                    'order_id'       => $orderId,
                    'returned_status' => $capture['status'] ?? 'unknown',
                ]);

                throw new \RuntimeException(
                    'PayPal capture status was not COMPLETED: ' . ($capture['status'] ?? 'unknown')
                );
            }

            Log::info('paypal.capture_order.success', [
                'order_id'   => $orderId,
                'capture_id' => $capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? null,
            ]);

            return $capture;

        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('paypal.capture_order.exception', [
                'order_id' => $orderId,
                'message'  => $e->getMessage(),
            ]);

            throw new \RuntimeException('PayPal connection error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Extracts the capture transaction ID from a completed capture response.
     */
    public function captureId(array $captureResponse): ?string
    {
        return $captureResponse['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
    }
}
