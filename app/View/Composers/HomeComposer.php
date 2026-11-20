<?php

namespace App\View\Composers;

use App\Support\HomeSections;
use App\Support\TourSearch;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Expone a resources/views/home.blade.php los datos REALES de las secciones
 * del rediseño. Cada variable es null (o trae valores null) cuando el cliente
 * aún no cargó el dato: la vista oculta la sección. Una falla en una sección
 * nunca rompe el home: esa sección queda en null y se registra en el log.
 *
 * Variables: $homeSearch, $homeStar, $homeCompare, $homeStats,
 * $homeReviewStats, $homePromo. Ver contrato-datos.md.
 */
class HomeComposer
{
    public function compose(View $view): void
    {
        $search = $this->safe('search', static fn () => TourSearch::options());

        $view->with([
            'homeSearch' => ($search['has_options'] ?? false) ? $search : null,
            'homeStar' => $this->safe('star', static fn () => HomeSections::star()),
            'homeCompare' => $this->safe('compare', static fn () => HomeSections::compare()),
            'homeStats' => $this->safe('stats', static fn () => HomeSections::stats()),
            'homeReviewStats' => $this->safe('review_stats', static fn () => HomeSections::reviewStats()),
            'homePromo' => $this->safe('promo', static fn () => HomeSections::promo()),
        ]);
    }

    private function safe(string $section, \Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            Log::error('HomeComposer: sección falló', ['section' => $section, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
