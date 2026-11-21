<?php

namespace App\Http\Controllers;

use App\Models\Testimonial;
use App\Models\Tour;
use App\Support\HomeFinalSections;
use App\Support\HomeSections;
use App\Support\TourFinder;
use App\Support\TourSheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON público para las ventanas (sheets) del home TAL CUAL. Solo lectura
 * (GET), sin sesión ni cookies (ver routes/web.php), limitado por IP
 * (limiter `home-api`) y con caché corta pública. Contrato:
 * contrato-datos.md, sección "HOME TAL CUAL (21-nov)".
 */
class HomeApiController extends Controller
{
    /** Segundos de caché pública (navegador/CDN). Los precios/reseñas cambian poco. */
    private const CACHE_SECONDS = 300;

    /** Reseñas por página de la ventana "ver todas" y tope de reseñas que se leen. */
    public const REVIEWS_PER_PAGE = 12;

    public const REVIEWS_MAX = 200;

    /** GET /{locale}/api/tours/{slug}/sheet */
    public function sheet(string $locale, string $slug): JsonResponse
    {
        $slug = mb_substr($slug, 0, 190);
        $resolution = Tour::resolveForLocale($locale, $slug, fn ($q) => $q->published());
        $tour = $resolution['model'];

        // No publicado / borrado / inexistente: 404 JSON (nunca datos de un borrador).
        if (! $tour) {
            return $this->json(['message' => 'not_found'], 404, false);
        }

        return $this->json(['data' => TourSheet::build($tour, $locale)]);
    }

    /** GET /{locale}/api/tours/finder?q=&duracion=&experiencia=&destino=&limit= */
    public function finder(Request $request, string $locale): JsonResponse
    {
        return $this->json(['data' => TourFinder::run(
            TourFinder::filtersFromRequest($request),
            $locale,
            TourFinder::limitFromRequest($request)
        )]);
    }

    /** GET /{locale}/api/reviews?plataforma=&destino=&fotos=1&pagina= */
    public function reviews(Request $request, string $locale): JsonResponse
    {
        $platform = $request->query('plataforma');
        $platform = is_string($platform) && in_array($platform, HomeFinalSections::PLATFORMS, true) ? $platform : null;

        $destination = $request->query('destino');
        $destination = is_string($destination) && preg_match('/^[a-z0-9-]{1,80}$/', $destination) ? $destination : null;

        $photos = $request->query('fotos') === '1';

        $page = $request->query('pagina');
        $page = is_string($page) && ctype_digit($page) && strlen($page) <= 4 ? max(1, (int) $page) : 1;

        $all = Testimonial::published()
            ->orderByDesc('is_featured')->orderBy('order')->orderByDesc('id')
            ->limit(self::REVIEWS_MAX)->get();

        $data = HomeFinalSections::reviews($all, $locale);

        if ($data === null) {
            return $this->json(['data' => [
                'summary' => null,
                'filters' => ['platforms' => [], 'destinations' => [], 'photos_count' => 0,
                    'show_platform_filter' => false, 'show_destination_filter' => false, 'show_photos_filter' => false],
                'applied' => ['plataforma' => null, 'destino' => null, 'fotos' => false],
                'cards' => [],
                'pagination' => ['page' => 1, 'per_page' => self::REVIEWS_PER_PAGE, 'total' => 0, 'last_page' => 1],
            ]]);
        }

        $cards = collect($data['cards'])
            ->when($platform, fn ($c) => $c->where('platform', $platform))
            ->when($destination, fn ($c) => $c->where('destination', $destination))
            ->when($photos, fn ($c) => $c->where('has_photos', true))
            ->values();

        $total = $cards->count();
        $lastPage = max(1, (int) ceil($total / self::REVIEWS_PER_PAGE));
        $page = min($page, $lastPage);

        $stats = HomeSections::reviewStats($locale);

        return $this->json(['data' => [
            'summary' => $stats ? [
                'total' => $stats['total'],
                'average' => $stats['average'],
                'distribution' => $stats['distribution'],
            ] : null,
            'filters' => [
                'platforms' => $data['platforms'],
                'destinations' => $data['destinations'],
                'photos_count' => $data['photos_count'],
                'show_platform_filter' => $data['show_platform_filter'],
                'show_destination_filter' => $data['show_destination_filter'],
                'show_photos_filter' => $data['show_photos_filter'],
            ],
            'applied' => ['plataforma' => $platform, 'destino' => $destination, 'fotos' => $photos],
            'cards' => $cards->slice(($page - 1) * self::REVIEWS_PER_PAGE, self::REVIEWS_PER_PAGE)
                ->map(fn (array $c): array => $this->publicReview($c))->values()->all(),
            'pagination' => ['page' => $page, 'per_page' => self::REVIEWS_PER_PAGE, 'total' => $total, 'last_page' => $lastPage],
        ]]);
    }

    /**
     * Tarjeta de reseña lista para JSON: la fecha viaja como ISO (`date_iso`)
     * y no se expone el objeto Carbon ni nada que no sea público.
     *
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    private function publicReview(array $c): array
    {
        unset($c['date']);

        return $c;
    }

    private function json(array $payload, int $status = 200, bool $cache = true): JsonResponse
    {
        $response = response()->json($payload, $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $response->headers->set('Cache-Control', $cache ? 'public, max-age='.self::CACHE_SECONDS : 'no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
