<?php

namespace App\Http\Controllers;

use App\Models\Page;
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

        // Página del CMS (Filament → Páginas → slug "resenas") para meta
        // título/descripción/JSON-LD administrables. No existe hoy en el
        // seeder de páginas estáticas — null-safe en la vista, igual que
        // "contacto"/"nosotros" cuando el registro no está publicado.
        $page = Page::where('slug', 'resenas')->first();

        return view('reviews', compact('testimonials', 'stats', 'overall', 'links', 'page'));
    }

    /**
     * Guarda una reseña enviada desde la página pública de reseñas.
     * No está ligada a ningún tour. Queda is_active=false (pendiente de
     * moderación) hasta que un admin la apruebe en Filament → Testimonios.
     */
    public function store(\Illuminate\Http\Request $request, string $locale): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'rating'  => ['required', 'integer', 'between:1,5'],
            'name'    => ['required', 'string', 'max:120'],
            'email'   => ['required', 'email', 'max:160'],
            'country' => ['nullable', 'string', 'max:120'],
            'comment' => ['required', 'string', 'min:10', 'max:2000'],
            // Honeypot anti-spam: debe venir vacío
            'website' => ['nullable', 'size:0'],
        ], [], [
            'rating'  => 'puntuación',
            'name'    => 'nombre',
            'email'   => 'correo',
            'comment' => 'comentario',
        ]);

        Testimonial::create([
            'name'        => $data['name'],
            'country'     => $data['country'] ?? null,
            'quote_es'    => $data['comment'],
            'rating'      => $data['rating'],
            'source'      => 'Web',
            'is_active'   => false, // pendiente de moderación
            'is_featured' => false,
        ]);

        return redirect()
            ->to(\App\Support\LocalizedPages::url('reviews', $locale))
            ->with('review_status', $locale === 'en'
                ? 'Thank you! Your review was submitted and will be published after moderation.'
                : ($locale === 'pt'
                    ? 'Obrigado! Sua avaliação foi enviada e será publicada após moderação.'
                    : '¡Gracias! Tu reseña fue enviada y se publicará tras ser revisada.'))
            ->withFragment('dejar-resena');
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
