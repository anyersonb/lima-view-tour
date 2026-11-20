<?php

namespace App\Http\Controllers;

use App\Models\BlockedDate;
use App\Models\Region;
use App\Models\Testimonial;
use App\Models\Tour;
use App\Support\BookingCalendar;
use App\Support\TourSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TourController extends Controller
{
    public function index(Request $request): View
    {
        // Buscador del home: ?duracion=&experiencia=&destino= (whitelist en
        // TourSearch; un valor inválido se ignora). Sin parámetros válidos el
        // listado es el de siempre.
        $searchFilters = TourSearch::filtersFromRequest($request);
        $searchActive = TourSearch::isActive($searchFilters);
        $tours = $searchActive ? TourSearch::apply($searchFilters) : Tour::published()->ordered()->get();

        return view('tours.index', [
            'categoria' => null,
            'tours' => $tours,
            // Contrato para la vista (ver contrato-datos.md, sección 1).
            'searchFilters' => $searchFilters,
            'searchActive' => $searchActive,
            'searchNoResults' => $searchActive && $tours->isEmpty(),
            'searchQuery' => $searchFilters[TourSearch::PARAM_QUERY],
            'searchChips' => $searchActive ? TourSearch::chips($searchFilters) : [],
            'searchWhatsappUrl' => $searchActive && $tours->isEmpty() ? TourSearch::whatsappUrl() : null,
            'regions' => Region::active()->orderBy('order')->get(),
            'testimonials' => Testimonial::published()->featured()->orderBy('order')->limit(4)->get(),
        ]);
    }

    public function category(string $locale, string $categoria): View
    {
        $region = Region::where('slug', $categoria)->firstOrFail();

        return view('tours.index', [
            'categoria' => $categoria,
            'region' => $region,
            'tours' => Tour::published()->where('region_id', $region->id)->ordered()->get(),
            'regions' => Region::active()->orderBy('order')->get(),
            'testimonials' => Testimonial::published()->featured()->orderBy('order')->limit(4)->get(),
        ]);
    }

    public function show(string $locale, string $slug): View|RedirectResponse
    {
        // Resolve by the active locale's slug column (slug_en/slug_pt) with
        // fallback to the Spanish `slug`. If the visitor hit the Spanish slug
        // under a locale that already has its own translated slug, 301 to the
        // canonical translated URL instead of serving duplicate content.
        $resolution = Tour::resolveForLocale($locale, $slug, fn ($q) => $q->published());
        $tour = $resolution['model'];

        abort_if(! $tour, 404);

        if ($resolution['redirect_slug']) {
            return redirect()->route('tours.show', [
                'locale' => $locale,
                'slug' => $resolution['redirect_slug'],
            ], 301);
        }

        $related = Tour::published()->where('id', '!=', $tour->id)
            ->when($tour->region_id, fn ($q) => $q->where('region_id', $tour->region_id))
            ->limit(4)->get();
        if ($related->count() < 4) {
            $related = Tour::published()->where('id', '!=', $tour->id)->limit(4)->get();
        }

        return view('tours.show', [
            'tour'          => $tour,
            'related'       => $related,
            'testimonials'  => Testimonial::published()->featured()->orderBy('order')->limit(4)->get(),
            // Reseñas propias del tour (sección "Opiniones de nuestros viajeros").
            // published() exige is_active=true Y status=approved: las dos
            // maquetas (móvil y escritorio) consumen esta MISMA colección.
            'tourReviews'   => $tour->testimonials()
                ->published()
                ->latest()
                ->get(),
            // Agregados reales (average/total/distribution) — ver firma en
            // Tour::reviewStats(). Ningún cálculo de promedios/porcentajes debe
            // hacerse en el Blade: ya vienen resueltos acá.
            'reviewStats'   => $tour->reviewStats(),
            // Booking calendar data (Lima clock). The first
            // bookable date depends on the daily cutoff (see BookingCalendar) and the current
            // time, so every calendar/min="" in the view reads these.
            'bookingMinDate'      => BookingCalendar::earliestDate($tour),
            'bookingDepartureTime' => BookingCalendar::departureTime($tour),
            'blockedDates'    => BlockedDate::blockedDatesFor($tour->id),
            'blockedWeekdays' => BlockedDate::blockedWeekdaysFor($tour->id),
        ]);
    }

    /**
     * Guarda una reseña enviada por el visitante (caja estilo WooCommerce).
     * Queda is_active=false (pendiente) hasta que un admin la apruebe en Filament → Testimonios.
     */
    public function storeReview(Request $request, string $locale, string $slug): RedirectResponse
    {
        $tour = Tour::published()->where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'rating'  => ['required', 'integer', 'between:1,5'],
            'name'    => ['required', 'string', 'max:120'],
            'email'   => ['required', 'email', 'max:160'],
            'comment' => ['required', 'string', 'min:10', 'max:2000'],
            // Honeypot anti-spam: debe venir vacío
            'website' => ['nullable', 'size:0'],
        ], [], [
            'rating'  => 'puntuación',
            'name'    => 'nombre',
            'email'   => 'correo',
            'comment' => 'valoración',
        ]);

        Testimonial::create([
            'tour_id'     => $tour->id,
            'name'        => $data['name'],
            'quote_es'    => $data['comment'],
            'rating'      => $data['rating'],
            'source'      => 'Web',
            'is_active'   => false, // pendiente de moderación
            'status'      => 'pending', // cola de moderación distinguible en el panel
            'is_featured' => false,
        ]);

        return redirect()
            ->route('tours.show', ['locale' => $locale, 'slug' => $slug])
            ->with('review_status', '¡Gracias! Tu reseña fue enviada y se publicará tras ser revisada.')
            ->withFragment('reviews');
    }

    public function search(Request $request): View
    {
        // Mismo saneo/escape que ?q= de /tours (TourSearch): máx. 80 caracteres,
        // LIKE escapado, y aquí además la descripción ES (comportamiento previo).
        $q = (string) TourSearch::normalizeQuery($request->query('q'));
        $tours = Tour::published()
            ->when($q !== '', fn ($query) => TourSearch::scopeText($query, $q, true))
            ->ordered()->paginate(12)->withQueryString();

        return view('tours.results', [
            'q' => $q,
            'tours' => $tours,
        ]);
    }
}
