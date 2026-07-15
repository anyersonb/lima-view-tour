<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Testimonial;
use App\Services\ReviewAggregator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewAggregator $reviews,
    ) {}

    /**
     * Página pública "Comentarios de nuestros clientes":
     * reúne en un solo lugar las reseñas de Google (API), Tripadvisor
     * y las enviadas desde la propia web (campo `source` en testimonials).
     */
    public function index(): View
    {
        $locale = app()->getLocale();

        // Fusión de reseñas: Google/Tripadvisor (API) + testimonios del CMS.
        // La lógica de merge/dedup vive en ReviewAggregator (compartida con el home).
        $testimonials = $this->reviews->merge($this->fetchTestimonials(), $locale);

        // Resumen por origen (conteo + promedio de estrellas)
        $stats = $testimonials
            ->groupBy(fn ($t) => $this->reviews->normalizeSource(
                is_object($t) && isset($t->source) ? $t->source : ($t['source'] ?? 'web')
            ))
            ->map(fn ($group) => [
                'count'  => $group->count(),
                'rating' => round((float) $group->avg(
                    fn ($t) => is_object($t) ? ($t->rating ?? 5) : ($t['rating'] ?? 5)
                ), 1),
            ]);

        // Si la API de Google está activa, superpone sus estadísticas agregadas
        if ($this->reviews->isGoogleEnabled()) {
            $googleStats = $this->reviews->googleStats($locale);
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

    private function cleanLink(?string $url): ?string
    {
        $url = trim((string) $url);

        return ($url === '' || $url === '#') ? null : $url;
    }
}
