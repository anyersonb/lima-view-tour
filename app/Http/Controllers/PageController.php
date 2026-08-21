<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Testimonial;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PageController extends Controller
{
    /**
     * Nosotros / About Us / Sobre Nós.
     *
     * Vivía como closure en routes/web.php; se movió acá al traducir la URL por
     * idioma (propuesta ESPASEO v3) para que el handler tenga nombre, se pueda
     * testear y comparta el mismo camino que el resto de las institucionales.
     * El registro del CMS se sigue buscando por el slug español, que es el
     * identificador interno de la página y no cambia nunca.
     */
    public function about(): View
    {
        $page = Page::where('slug', 'nosotros')->first();

        // Active testimonials — 4 cards + 1 featured Tripadvisor quote
        $testimonials = Testimonial::active()->latest('order')->take(4)->get();
        $featured     = Testimonial::active()
            ->where('source', 'tripadvisor')
            ->latest()
            ->first()
            ?? Testimonial::active()->latest()->first();

        return view('about', compact('page', 'testimonials', 'featured'));
    }

    public function terms(): View
    {
        try {
            $locale = app()->getLocale();
            $title = __('legal.terms_title');
            // Página del CMS (Filament → Páginas → slug "terminos") para meta
            // título/descripción/JSON-LD administrables. No hay registro
            // seeded hoy: null-safe en la vista, igual que "resenas".
            $page = Page::where('slug', 'terminos')->first();

            return view('pages.terms', compact('locale', 'title', 'page'));
        } catch (\Throwable $e) {
            Log::error('PageController@terms: failed to render terms page', [
                'locale' => app()->getLocale(),
                'exception' => $e->getMessage(),
            ]);

            abort(500);
        }
    }

    public function privacy(): View
    {
        try {
            $locale = app()->getLocale();
            $title = __('legal.privacy_title');
            // Página del CMS (Filament → Páginas → slug "privacidad") para
            // meta título/descripción/JSON-LD administrables.
            $page = Page::where('slug', 'privacidad')->first();

            return view('pages.privacy', compact('locale', 'title', 'page'));
        } catch (\Throwable $e) {
            Log::error('PageController@privacy: failed to render privacy page', [
                'locale' => app()->getLocale(),
                'exception' => $e->getMessage(),
            ]);

            abort(500);
        }
    }
}
