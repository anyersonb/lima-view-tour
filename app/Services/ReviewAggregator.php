<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Combina reseñas de las APIs externas (Google, Tripadvisor) con los
 * testimonios cargados desde el CMS, deduplicando por origen.
 *
 * Se usa tanto en la página pública de reseñas (/resenas) como en la
 * sección de opiniones del home, para que ambas muestren la misma mezcla.
 */
class ReviewAggregator
{
    public function __construct(
        private readonly GoogleReviewsService $googleReviews,
        private readonly TripadvisorReviewsService $tripadvisorReviews,
    ) {}

    /**
     * Reseñas de la API de Google como objetos "pseudo-Testimonial"
     * (mismo interface que usa la vista: name, rating, quote, source, avatar…).
     * Devuelve colección vacía si la API no está habilitada/configurada.
     */
    public function googleApiReviews(string $locale): Collection
    {
        if (! $this->googleReviews->isEnabled()) {
            return collect();
        }

        return collect($this->googleReviews->getReviews($locale))->map(fn (array $r) => (object) [
            'name'        => $r['author'],
            'rating'      => $r['rating'],
            'quote'       => $r['text'],
            'source'      => 'google',
            'avatar'      => $r['profile_photo'],
            'country'     => null,
            'tour'        => null,
            'is_featured' => false,
        ]);
    }

    /**
     * Reseñas de la API de Tripadvisor (stub: vacío hasta que se apruebe el acceso).
     */
    public function tripadvisorApiReviews(): Collection
    {
        if (! $this->tripadvisorReviews->isEnabled()) {
            return collect();
        }

        return collect($this->tripadvisorReviews->getReviews())->map(fn (array $r) => (object) [
            'name'        => $r['author'],
            'rating'      => $r['rating'],
            'quote'       => $r['text'],
            'source'      => 'tripadvisor',
            'avatar'      => $r['profile_photo'] ?? null,
            'country'     => null,
            'tour'        => null,
            'is_featured' => false,
        ]);
    }

    /**
     * Fusiona reseñas de API con testimonios del CMS.
     *
     * Cuando una API está activa y devuelve datos, los testimonios del CMS
     * con ese mismo origen se descartan (la API manda, para no duplicar). Los
     * testimonios de otros orígenes (web, etc.) siempre se conservan.
     *
     * Orden resultante: Google (API) → Tripadvisor (API) → CMS filtrado.
     *
     * @param  Collection  $cms  Colección de testimonios del CMS (modelos Testimonial).
     */
    public function merge(Collection $cms, string $locale): Collection
    {
        $googleApi      = $this->googleApiReviews($locale);
        $tripadvisorApi = $this->tripadvisorApiReviews();

        $dropSources = [];

        if ($this->googleReviews->isEnabled() && $googleApi->isNotEmpty()) {
            $dropSources[] = 'google';
        }

        if ($this->tripadvisorReviews->isEnabled() && $tripadvisorApi->isNotEmpty()) {
            $dropSources[] = 'tripadvisor';
        }

        $filteredCms = $dropSources
            ? $cms->reject(fn ($t) => in_array(
                $this->normalizeSource(is_object($t) ? ($t->source ?? '') : ($t['source'] ?? '')),
                $dropSources,
                true
            ))->values()
            : $cms;

        return $googleApi->concat($tripadvisorApi)->concat($filteredCms)->values();
    }

    /** Normaliza el origen a una clave estable: google | tripadvisor | trivago | web */
    public function normalizeSource(?string $source): string
    {
        $s = strtolower(trim((string) $source));

        return match (true) {
            str_contains($s, 'google')      => 'google',
            str_contains($s, 'tripadvisor') => 'tripadvisor',
            str_contains($s, 'trivago')     => 'trivago',
            default                         => 'web',
        };
    }

    /** ¿La API de Google está habilitada y configurada? */
    public function isGoogleEnabled(): bool
    {
        return $this->googleReviews->isEnabled();
    }

    /**
     * Estadísticas agregadas de Google (rating + total), o null si no aplica.
     *
     * @return array{rating: float, total: int}|null
     */
    public function googleStats(string $locale): ?array
    {
        return $this->googleReviews->isEnabled()
            ? $this->googleReviews->getRatingStats($locale)
            : null;
    }
}
