<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\Region;
use App\Models\Testimonial;
use App\Models\Tour;
use App\Services\ReviewAggregator;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        private readonly ReviewAggregator $reviews,
    ) {}

    public function index(): View
    {
        $featuredTours = $this->fetchFeaturedTours();
        $toursIca = $this->fetchToursByRegion('Ica');
        $toursLima = $this->fetchToursByRegion('Lima');
        $toursCusco = $this->fetchToursByRegion('Cusco');
        $regions = $this->fetchRegions();
        $testimonials = $this->fetchTestimonials();
        $offers = $this->fetchOffers();

        return view('home', compact(
            'featuredTours',
            'toursIca',
            'toursLima',
            'toursCusco',
            'regions',
            'testimonials',
            'offers',
        ));
    }

    // ─── Private query helpers ────────────────────────────────────────────────

    private function fetchFeaturedTours(): \Illuminate\Support\Collection
    {
        try {
            // "Más Comprados" tiene su propio orden manual: `featured_order`
            // (1 = primero; NULL va al final y se ordena por número real de
            // reservas, excluyendo canceladas). Es independiente de `order`,
            // que siguen usando las secciones por región (Ica/Lima/Cusco).
            $byPurchases = static fn ($query) => $query
                ->withCount(['bookings as purchases_count' => static fn ($b) => $b->where('status', '!=', 'cancelled')])
                ->orderByRaw('featured_order IS NULL')
                ->orderBy('featured_order')
                ->orderByDesc('purchases_count');

            $featured = $byPurchases(Tour::published()->featured())
                ->ordered()
                ->limit(8)
                ->get();

            // Si hay menos de 3 destacados, completar con tours publicados
            // para garantizar al menos 3 cards visibles en el grid desktop.
            if ($featured->count() < 3) {
                $existingIds = $featured->pluck('id')->all();
                $fill = $byPurchases(Tour::published())
                    ->ordered()
                    ->when(count($existingIds) > 0, fn ($q) => $q->whereNotIn('id', $existingIds))
                    ->limit(8 - $featured->count())
                    ->get();

                $featured = $featured->concat($fill);
            }

            return $featured;
        } catch (\Throwable $e) {
            Log::error('HomeController: failed to fetch featured tours', [
                'exception' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Fetch published tours whose region name (name_es) matches the given
     * destination string. Uses whereHas so no new migration is required.
     *
     * @param  non-empty-string  $regionName  e.g. 'Ica', 'Lima', 'Cusco'
     */
    private function fetchToursByRegion(string $regionName): \Illuminate\Support\Collection
    {
        try {
            $regionTours = Tour::published()
                ->ordered()
                ->whereHas('region', static function ($query) use ($regionName): void {
                    $query->where('name_es', $regionName);
                })
                ->limit(8)
                ->get();

            // Respaldo a tours reales si la región no tiene tours (evita placeholders 404).
            if ($regionTours->isEmpty()) {
                $regionTours = Tour::published()->ordered()->limit(8)->get();
            }

            return $regionTours;
        } catch (\Throwable $e) {
            Log::error('HomeController: failed to fetch tours by region', [
                'region' => $regionName,
                'exception' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    private function fetchRegions(): \Illuminate\Support\Collection
    {
        try {
            return Region::active()->orderBy('order')->get();
        } catch (\Throwable $e) {
            Log::error('HomeController: failed to fetch regions', [
                'exception' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Testimonios del home: mezcla reseñas reales de Google (API) con los
     * testimonios destacados del CMS, usando el mismo servicio que /resenas.
     * Si la API de Google no está activa, muestra solo los del CMS (como antes).
     */
    private function fetchTestimonials(): \Illuminate\Support\Collection
    {
        try {
            $cms = Testimonial::active()->featured()->orderBy('order')->limit(8)->get();

            return $this->reviews->merge($cms, app()->getLocale())->take(9);
        } catch (\Throwable $e) {
            Log::error('HomeController: failed to fetch testimonials', [
                'exception' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    private function fetchOffers(): \Illuminate\Support\Collection
    {
        try {
            return Offer::active()->orderBy('order')->limit(3)->get();
        } catch (\Throwable $e) {
            Log::error('HomeController: failed to fetch offers', [
                'exception' => $e->getMessage(),
            ]);

            return collect();
        }
    }
}
