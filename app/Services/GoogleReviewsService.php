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
    private const CACHE_PREFIX  = 'google_reviews.v2.'; // v2: invalida entradas [] cacheadas con el TTL largo

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
     */
    private function callApi(string $language): ?array
    {
        try {
            $response = Http::timeout(8)->get(self::PLACES_API, [
                'place_id' => $this->placeId(),
                'fields'   => 'reviews,rating,user_ratings_total',
                'key'      => $this->apiKey(),
                'language' => $language,
            ]);

            if (! $response->successful()) {
                Log::warning('GoogleReviewsService: HTTP error', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $body = $response->json();

            if (($body['status'] ?? '') !== 'OK') {
                Log::warning('GoogleReviewsService: Places API returned non-OK status', [
                    'status'        => $body['status'] ?? 'unknown',
                    'error_message' => $body['error_message'] ?? '',
                ]);

                return null;
            }

            return $body;
        } catch (\Throwable $e) {
            Log::error('GoogleReviewsService: exception calling Places API', [
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
