<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Stub for the Tripadvisor Content API.
 *
 * The Tripadvisor Content API requires prior approval from Tripadvisor.
 * This service is a no-op until a valid API key and location ID are
 * configured in Settings → APIs. Once approved, replace the stub
 * implementation in getReviews() with actual HTTP calls.
 *
 * Docs: https://tripadvisor-content-api.readme.io/reference/overview
 */
class TripadvisorReviewsService
{
    /**
     * Whether the service is fully configured and enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) (
            $this->apiKey()
            && $this->locationId()
            && Setting::get('tripadvisor_reviews_enabled', false)
        );
    }

    /**
     * Return normalized reviews from the Tripadvisor Content API.
     * Each review: [author, rating, text, time, profile_photo].
     *
     * Returns an empty array until the API is approved and implemented.
     *
     * @return array<int, array{author: string, rating: int, text: string, time: int, profile_photo: string|null}>
     */
    public function getReviews(): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        // TODO: implement once Tripadvisor Content API access is approved.
        // Endpoint: GET https://api.content.tripadvisor.com/api/v1/location/{locationId}/reviews
        // Headers: ['X-TripAdvisor-API-Key' => $this->apiKey()]
        return [];
    }

    // ─────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────

    private function apiKey(): ?string
    {
        return Setting::get('tripadvisor_api_key') ?: config('services.tripadvisor.api_key') ?: null;
    }

    private function locationId(): ?string
    {
        return Setting::get('tripadvisor_location_id') ?: config('services.tripadvisor.location_id') ?: null;
    }
}
