<?php

namespace App\Http\Controllers;

use App\Models\BlockedDate;
use App\Models\Region;
use App\Models\Testimonial;
use App\Models\Tour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TourController extends Controller
{
    public function index(): View
    {
        return view('tours.index', [
            'categoria' => null,
            'tours' => Tour::published()->ordered()->get(),
            'regions' => Region::active()->orderBy('order')->get(),
            'testimonials' => Testimonial::active()->featured()->orderBy('order')->limit(4)->get(),
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
            'testimonials' => Testimonial::active()->featured()->orderBy('order')->limit(4)->get(),
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
            'testimonials'  => Testimonial::active()->featured()->orderBy('order')->limit(4)->get(),
            'tourReviews'   => $tour->testimonials()
                ->where('is_active', true)
                ->latest()
                ->get(),
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
            'is_featured' => false,
        ]);

        return redirect()
            ->route('tours.show', ['locale' => $locale, 'slug' => $slug])
            ->with('review_status', '¡Gracias! Tu reseña fue enviada y se publicará tras ser revisada.')
            ->withFragment('reviews');
    }

    public function search(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $tours = Tour::published()
            ->when($q, fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('title_es', 'like', "%{$q}%")
                  ->orWhere('title_en', 'like', "%{$q}%")
                  ->orWhere('description_es', 'like', "%{$q}%");
            }))
            ->ordered()->paginate(12)->withQueryString();

        return view('tours.results', [
            'q' => $q,
            'tours' => $tours,
        ]);
    }
}
