<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    private string $secretKey;
    private string $publicKey;
    private string $webhookSecret;
    private string $apiUrl;

    public function __construct()
    {
        $this->secretKey     = config('services.culqi.secret_key', '');
        $this->publicKey     = config('services.culqi.public_key', '');
        $this->webhookSecret = config('services.culqi.webhook_secret', '');
        $this->apiUrl        = rtrim(config('services.culqi.api_url', 'https://api.culqi.com/v2'), '/');
    }

    /**
     * Create a charge in Culqi.
     *
     * @param  array{
     *   amount: int,
     *   currency: string,
     *   email: string,
     *   source_id: string,
     *   antifraud_details?: array,
     *   metadata?: array
     * } $data
     * @return array
     *
     * @throws \RuntimeException on non-2xx response
     */
    public function createCharge(array $data): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->acceptJson()
                ->post("{$this->apiUrl}/charges", $data);

            if ($response->failed()) {
                Log::error('culqi.create_charge.failed', [
                    'status'   => $response->status(),
                    'body'     => $response->body(),
                    'metadata' => $data['metadata'] ?? [],
                ]);

                throw new \RuntimeException(
                    'Culqi charge failed: ' . ($response->json('user_message') ?? $response->body())
                );
            }

            Log::info('culqi.create_charge.success', [
                'charge_id' => $response->json('id'),
                'metadata'  => $data['metadata'] ?? [],
            ]);

            return $response->json();
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('culqi.create_charge.exception', [
                'message'  => $e->getMessage(),
                'metadata' => $data['metadata'] ?? [],
            ]);

            throw new \RuntimeException('Culqi connection error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Retrieve a charge by ID.
     *
     * @throws \RuntimeException on non-2xx response
     */
    public function retrieveCharge(string $id): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->acceptJson()
                ->get("{$this->apiUrl}/charges/{$id}");

            if ($response->failed()) {
                Log::error('culqi.retrieve_charge.failed', [
                    'charge_id' => $id,
                    'status'    => $response->status(),
                    'body'      => $response->body(),
                ]);

                throw new \RuntimeException('Culqi retrieve charge failed: ' . $response->body());
            }

            return $response->json();
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('culqi.retrieve_charge.exception', [
                'charge_id' => $id,
                'message'   => $e->getMessage(),
            ]);

            throw new \RuntimeException('Culqi connection error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Verify that an incoming webhook payload matches the expected HMAC signature.
     *
     * Culqi signs the raw JSON body with HMAC-SHA256 using the webhook secret.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (empty($this->webhookSecret)) {
            Log::warning('culqi.webhook.secret_not_configured');
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expected, $signature);
    }
}
