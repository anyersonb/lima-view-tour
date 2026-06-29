<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Testimonial;
use App\Services\GoogleReviewsService;
use App\Services\TripadvisorReviewsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function __construct(
        private readonly GoogleReviewsService $googleReviews,
        private readonly TripadvisorReviewsService $tripadvisorReviews,
    ) {}

    /**
     * Página pública "Comentarios de nuestros clientes":
     * reúne en un solo lugar las reseñas de Google (API), Tripadvisor
     * y las enviadas desde la propia web (campo `source` en testimonials).
     */
    public function index(): View
    {
        $locale = app()->getLocale();

        // CMS testimonials (always shown)
        $cmsTestimonials = $this->fetchTestimonials();

        // Google API reviews — only if configured; converted to pseudo-Testimonial objects
        $googleApiReviews = $this->fetchGoogleApiReviews($locale);

        // Tripadvisor stub — returns [] until API access is approved
        $tripadvisorApiReviews = $this->fetchTripadvisorApiReviews();

        // Merge: API reviews first (freshest), then CMS entries
        // Deduplicate: if a CMS entry already has source=google, API ones take precedence
        // We keep CMS google entries only when the API is NOT enabled
        $testimonials = $this->mergeReviews(
            $cmsTestimonials,
            $googleApiReviews,
            $tripadvisorApiReviews
        );

        // Resumen por origen (conteo + promedio de estrellas)
        $stats = $testimonials
            ->groupBy(fn ($t) => $this->normalizeSource(
                is_object($t) && isset($t->source) ? $t->source : ($t['source'] ?? 'web')
            ))
            ->map(fn ($group) => [
                'count'  => $group->count(),
                'rating' => round((float) $group->avg(
                    fn ($t) => is_object($t) ? ($t->rating ?? 5) : ($t['rating'] ?? 5)
                ), 1),
            ]);

        // If Google API is enabled, overlay its aggregate stats over the per-group stats
        if ($this->googleReviews->isEnabled()) {
            $googleStats = $this->googleReviews->getRatingStats($locale);
            if ($googleStats) {
                $stats['google'] = [
                    'count'  => $googleStats['total'],
                    'rating' => $googleStats['rating'],
                ];
            }
        }

        $totalCount = $testimonials->count();
        $overall = [
            'count'  => $totalCount,
            'rating' => $totalCount
                ? round((float) $testimonials->avg(
                    fn ($t) => is_object($t) ? ($t->rating ?? 5) : ($t['rating'] ?? 5)
                ), 1)
                : 5.0,
        ];

        // Enlaces externos (para "déjanos tu reseña" / "ver todas")
        $links = [
            'google'      => $this->cleanLink(Setting::get('social_google_reviews')),
            'tripadvisor' => $this->cleanLink(Setting::get('social_tripadvisor')),
            'trivago'     => $this->cleanLink(Setting::get('social_trivago')),
        ];

        return view('reviews', compact('testimonials', 'stats', 'overall', 'links'));
    }

    private function fetchTestimonials(): Collection
    {
        try {
            return Testimonial::active()
                ->orderByDesc('is_featured')
                ->orderBy('order')
                ->orderByDesc('created_at')
                ->get();
        } catch (\Throwable $e) {
            Log::error('ReviewController: failed to fetch testimonials', [
                'exception' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Fetch Google reviews from the API and convert them to plain objects
     * that share the same interface used by the reviews.blade.php template
     * (name, rating, quote, source, avatar, country, tour = null).
     */
    private function fetchGoogleApiReviews(string $locale): Collection
    {
        if (! $this->googleReviews->isEnabled()) {
            return collect();
        }

        $raw = $this->googleReviews->getReviews($locale);

        return collect($raw)->map(fn (array $r) => (object) [
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
     * Fetch Tripadvisor reviews (stub — returns empty collection until API is approved).
     */
    private function fetchTripadvisorApiReviews(): Collection
    {
        if (! $this->tripadvisorReviews->isEnabled()) {
            return collect();
        }

        $raw = $this->tripadvisorReviews->getReviews();

        return collect($raw)->map(fn (array $r) => (object) [
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
     * Merge API reviews with CMS testimonials.
     *
     * When the Google API is enabled, CMS entries with source=google are
     * replaced by the live API data (API takes precedence). Same logic for
     * Tripadvisor. CMS entries of other sources are always preserved.
     */
    private function mergeReviews(
        Collection $cms,
        Collection $googleApi,
        Collection $tripadvisorApi
    ): Collection {
        $dropSources = [];

        if ($this->googleReviews->isEnabled() && $googleApi->isNotEmpty()) {
            $dropSources[] = 'google';
        }

        if ($this->tripadvisorReviews->isEnabled() && $tripadvisorApi->isNotEmpty()) {
            $dropSources[] = 'tripadvisor';
        }

        $filteredCms = $dropSources
            ? $cms->filter(fn ($t) => ! in_array(
                $this->normalizeSource($t->source ?? ''),
                $dropSources,
                true
            ))
            : $cms;

        return $googleApi
            ->concat($tripadvisorApi)
            ->concat($filteredCms);
    }

    /** Normaliza el origen a una clave estable: google | tripadvisor | trivago | web */
    private function normalizeSource(?string $source): string
    {
        $s = strtolower(trim((string) $source));

        return match (true) {
            str_contains($s, 'google')      => 'google',
            str_contains($s, 'tripadvisor') => 'tripadvisor',
            str_contains($s, 'trivago')     => 'trivago',
            default                         => 'web',
        };
    }

    private function cleanLink(?string $url): ?string
    {
        $url = trim((string) $url);

        return ($url === '' || $url === '#') ? null : $url;
    }
}
