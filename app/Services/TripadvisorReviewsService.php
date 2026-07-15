<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente de la Tripadvisor Content API.
 *
 * La Content API requiere una API Key propia de Tripadvisor (distinta de la de
 * Google) y el Location ID del negocio. Se configuran en Settings → APIs.
 * El endpoint de reviews devuelve como máximo las 5 reseñas más recientes.
 *
 * Docs: https://tripadvisor-content-api.readme.io/reference/getlocationreviews
 */
class TripadvisorReviewsService
{
    private const CACHE_TTL    = 6 * 3600; // 6 horas
    private const EMPTY_TTL    = 600;      // fallos/vacío: 10 min (para no ocultar reseñas tras corregir la key)
    private const REVIEWS_API  = 'https://api.content.tripadvisor.com/api/v1/location/%s/reviews';
    private const CACHE_PREFIX = 'tripadvisor_reviews.v1.';

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
     * Returns an empty array if not configured or on any error.
     *
     * @return array<int, array{author: string, rating: int, text: string, time: int, profile_photo: string|null}>
     */
    public function getReviews(?string $language = null): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $language = $language ?: app()->getLocale();
        $cacheKey = self::CACHE_PREFIX . $this->locationId() . '.' . $language;

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $reviews = $this->fetchReviews($language);

        Cache::put($cacheKey, $reviews, $reviews === [] ? self::EMPTY_TTL : self::CACHE_TTL);

        return $reviews;
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

    /**
     * Hit the Content API reviews endpoint and return normalized reviews.
     *
     * Se envía el header Referer del sitio: las keys de Tripadvisor con
     * restricción por dominio (HTTP referrers) rechazan llamadas sin él.
     *
     * @return array<int, array{author: string, rating: int, text: string, time: int, profile_photo: string|null}>
     */
    private function fetchReviews(string $language): array
    {
        $referer = rtrim((string) config('app.url'), '/') . '/';
        $url     = sprintf(self::REVIEWS_API, rawurlencode((string) $this->locationId()));

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'Referer' => $referer,
                    'Accept'  => 'application/json',
                ])
                ->get($url, [
                    'key'      => $this->apiKey(),
                    'language' => $this->normalizeLanguage($language),
                ]);

            if (! $response->successful()) {
                Log::error('TripadvisorReviewsService: reviews request failed', [
                    'http_status' => $response->status(),
                    'error'       => $response->json('error.message') ?? $response->body(),
                ]);

                return [];
            }

            $data = $response->json('data') ?? [];

            return array_map(fn (array $r): array => [
                'author'        => $r['user']['username'] ?? ($r['user']['name'] ?? ''),
                'rating'        => (int) ($r['rating'] ?? 5),
                'text'          => trim((string) ($r['text'] ?? '')),
                'time'          => isset($r['published_date']) ? (int) strtotime($r['published_date']) : 0,
                'profile_photo' => $r['user']['avatar']['small']
                    ?? $r['user']['avatar']['thumbnail']
                    ?? null,
            ], array_filter($data, 'is_array'));
        } catch (\Throwable $e) {
            Log::error('TripadvisorReviewsService: exception calling Content API', [
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Tripadvisor usa códigos como es, en, pt_BR. Mapeamos los locales del sitio.
     */
    private function normalizeLanguage(string $language): string
    {
        return match ($language) {
            'pt'    => 'pt_BR',
            default => $language,
        };
    }
}
