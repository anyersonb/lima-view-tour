<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\Region;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\Tour;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('home', [
            'featuredTours' => Tour::published()->featured()->ordered()->limit(4)->get(),
            'regions' => Region::active()->orderBy('order')->get(),
            'testimonials' => Testimonial::active()->featured()->orderBy('order')->limit(4)->get(),
            'offers' => Offer::active()->orderBy('order')->limit(3)->get(),
        ]);
    }
}
