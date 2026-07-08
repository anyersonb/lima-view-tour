<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleReviewsService
{
    private const CACHE_TTL     = 6 * 3600; // 6 hours in seconds
    private const EMPTY_TTL     = 600;      // failures/empty: 10 min, para no ocultar reseñas tras corregir la API key
    private const PLACES_API    = 'https://maps.googleapis.com/maps/api/place/details/json';
    private const PLACES_API_V1 = 'https://places.googleapis.com/v1/places/'; // Places API (New) — acepta keys con restricción de referer
    private const CACHE_PREFIX  = 'google_reviews.v3.'; // v3: invalida el [] cacheado antes del fallback con Referer

    /**
     * Whether the service is fully configured and enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) (
            $this->apiKey()
            && $this->placeId()
            && Setting::get('google_reviews_enabled', false)
        );
    }

    /**
     * Return normalized reviews from the Places Details API.
     * Each review: [author, rating, text, time, profile_photo].
     * Returns an empty array if not configured or on any error.
     *
     * @return array<int, array{author: string, rating: int, text: string, time: int, profile_photo: string|null}>
     */
    public function getReviews(string $language = 'es'): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $cacheKey = self::CACHE_PREFIX . $this->placeId() . '.' . $language;

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $reviews = $this->fetchReviews($language);

        Cache::put($cacheKey, $reviews, $reviews === [] ? self::EMPTY_TTL : self::CACHE_TTL);

        return $reviews;
    }

    /**
     * Return the aggregate rating and total review count from the API.
     * Returns null if not configured or on error.
     *
     * @return array{rating: float, total: int}|null
     */
    public function getRatingStats(string $language = 'es'): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX . 'stats.' . $this->placeId() . '.' . $language;

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $stats = $this->fetchStats($language);

        if ($stats !== null) {
            Cache::put($cacheKey, $stats, self::CACHE_TTL);
        }

        return $stats;
    }

    // ─────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────

    private function apiKey(): ?string
    {
        return Setting::get('google_maps_api_key') ?: config('services.google.maps_api_key') ?: null;
    }

    private function placeId(): ?string
    {
        return Setting::get('google_place_id') ?: config('services.google.place_id') ?: null;
    }

    /**
     * Hit the Places Details API and return raw decoded body, or null on failure.
     *
     * Se envía el header Referer del sitio: las keys con restricción de
     * "sitios web" (HTTP referrers) rechazan llamadas server-side sin él.
     * Si el endpoint legacy la rechaza igual (REQUEST_DENIED), se reintenta
     * contra Places API (New), que sí valida el Referer enviado.
     */
    private function callApi(string $language): ?array
    {
        $referer = rtrim((string) config('app.url'), '/') . '/';

        try {
            $response = Http::timeout(8)
                ->withHeaders(['Referer' => $referer])
                ->get(self::PLACES_API, [
                    'place_id' => $this->placeId(),
                    'fields'   => 'reviews,rating,user_ratings_total',
                    'key'      => $this->apiKey(),
                    'language' => $language,
                ]);

            $body   = $response->json() ?: [];
            $status = $body['status'] ?? '';

            if ($response->successful() && $status === 'OK') {
                return $body;
            }

            Log::error('GoogleReviewsService: legacy Places API failed, trying Places API (New)', [
                'http_status'   => $response->status(),
                'status'        => $status ?: 'unknown',
                'error_message' => $body['error_message'] ?? '',
            ]);

            return $this->callApiV1($language, $referer);
        } catch (\Throwable $e) {
            Log::error('GoogleReviewsService: exception calling Places API', [
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fallback: Places API (New). Devuelve el body ya normalizado al formato
     * del endpoint legacy (result.reviews / result.rating / user_ratings_total)
     * para no tocar los consumidores.
     */
    private function callApiV1(string $language, string $referer): ?array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'X-Goog-Api-Key'   => $this->apiKey(),
                    'X-Goog-FieldMask' => 'rating,userRatingCount,reviews',
                    'Referer'          => $referer,
                ])
                ->get(self::PLACES_API_V1 . $this->placeId(), [
                    'languageCode' => $language,
                ]);

            if (! $response->successful()) {
                Log::error('GoogleReviewsService: Places API (New) also failed', [
                    'http_status' => $response->status(),
                    'error'       => $response->json('error.message') ?? $response->body(),
                ]);

                return null;
            }

            $body = $response->json() ?: [];

            return [
                'status' => 'OK',
                'result' => [
                    'rating'             => $body['rating'] ?? 0,
                    'user_ratings_total' => $body['userRatingCount'] ?? 0,
                    'reviews'            => array_map(static fn (array $r): array => [
                        'author_name'       => $r['authorAttribution']['displayName'] ?? '',
                        'rating'            => $r['rating'] ?? 5,
                        'text'              => $r['text']['text'] ?? '',
                        'time'              => isset($r['publishTime']) ? (int) strtotime($r['publishTime']) : 0,
                        'profile_photo_url' => $r['authorAttribution']['photoUri'] ?? null,
                    ], $body['reviews'] ?? []),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('GoogleReviewsService: exception calling Places API (New)', [
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<int, array{author: string, rating: int, text: string, time: int, profile_photo: string|null}>
     */
    private function fetchReviews(string $language): array
    {
        $body = $this->callApi($language);

        if ($body === null) {
            return [];
        }

        $raw = $body['result']['reviews'] ?? [];

        return array_map(fn (array $r): array => [
            'author'        => $r['author_name']         ?? '',
            'rating'        => (int) ($r['rating']       ?? 5),
            'text'          => $r['text']                ?? '',
            'time'          => (int) ($r['time']         ?? 0),
            'profile_photo' => $r['profile_photo_url']   ?? null,
        ], $raw);
    }

    /**
     * @return array{rating: float, total: int}|null
     */
    private function fetchStats(string $language): ?array
    {
        $body = $this->callApi($language);

        if ($body === null) {
            return null;
        }

        return [
            'rating' => (float) ($body['result']['rating']              ?? 0),
            'total'  => (int)   ($body['result']['user_ratings_total']  ?? 0),
        ];
    }
}
