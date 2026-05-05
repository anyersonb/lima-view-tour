<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Models\Testimonial;
use App\Models\Tour;
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

    public function show(string $locale, string $slug): View
    {
        $tour = Tour::published()->where('slug', $slug)->firstOrFail();
        $related = Tour::published()->where('id', '!=', $tour->id)
            ->when($tour->region_id, fn ($q) => $q->where('region_id', $tour->region_id))
            ->limit(4)->get();
        if ($related->count() < 4) {
            $related = Tour::published()->where('id', '!=', $tour->id)->limit(4)->get();
        }

        return view('tours.show', [
            'tour' => $tour,
            'related' => $related,
            'testimonials' => Testimonial::active()->featured()->orderBy('order')->limit(4)->get(),
        ]);
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
