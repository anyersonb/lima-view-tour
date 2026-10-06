<?php

namespace Tests\Feature;

use App\Models\Testimonial;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Defecto CRÍTICO corregido (2026-09-21): el badge de cabecera de la ficha
 * ("★★★★★ 5.0 (N reseñas verificadas) ✓ Verificado") y la sección "Otros
 * viajeros también reservaron" leían `tours.rating` / `tours.reviews_count`
 * con fallback fijo (`?? 4.8` / `?? 30`) y un fallback ficticio de tours
 * inventados. Única fuente de verdad ahora: Tour::reviewStats(). Sin
 * reseñas publicadas reales, el badge no se pinta (ni el texto "verified
 * reviews", que solo vive en esas dos maquetas — ver
 * feedback_asserdontsee_texto_compartido_dos_secciones en memoria: 'ui.verified'
 * SÍ se repite en review-card, por eso se afirma sobre 'ui.verified_reviews',
 * exclusivo del badge de cabecera).
 */
class TourHeaderRatingBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function tour(array $overrides = []): Tour
    {
        return Tour::factory()->create(array_merge(['is_published' => true], $overrides));
    }

    private function review(Tour $tour, array $overrides = []): Testimonial
    {
        return Testimonial::create(array_merge([
            'tour_id'   => $tour->id,
            'name'      => 'Viajero visible',
            'quote_es'  => 'Comentario de prueba con más de diez caracteres.',
            'rating'    => 5,
            'source'    => 'Web',
            'is_active' => true,
            'status'    => 'approved',
        ], $overrides));
    }

    public function test_header_badge_is_absent_without_real_published_reviews(): void
    {
        $tour = $this->tour(['slug' => 'badge-sin-resenas', 'rating' => 4.8, 'reviews_count' => 0]);

        $html = $this->get("/es/tours/detalle/{$tour->slug}")->assertOk()->getContent();

        $this->assertStringNotContainsString(__('ui.verified_reviews'), $html);
    }

    public function test_header_badge_ignores_dirty_legacy_columns_even_with_stale_counts(): void
    {
        // Simula el estado real medido en producción: legacy rating/reviews_count
        // pisados con datos que no cuadran con las reseñas reales (0 reales).
        $tour = $this->tour(['slug' => 'badge-legacy-sucio', 'rating' => 4.8, 'reviews_count' => 4]);

        $html = $this->get("/es/tours/detalle/{$tour->slug}")->assertOk()->getContent();

        $this->assertStringNotContainsString(__('ui.verified_reviews'), $html);
    }

    public function test_header_badge_shows_real_average_and_total_when_published_reviews_exist(): void
    {
        $tour = $this->tour(['slug' => 'badge-con-resenas', 'rating' => 4.8, 'reviews_count' => 30]);
        $this->review($tour, ['rating' => 5]);
        $this->review($tour, ['rating' => 5]);
        $this->review($tour, ['rating' => 4]);
        // Una reseña pendiente NO debe contarse en el badge.
        $this->review($tour, ['rating' => 1, 'status' => 'pending', 'is_active' => false]);

        $html = $this->get("/es/tours/detalle/{$tour->slug}")->assertOk()->getContent();

        $stats = $tour->fresh()->reviewStats();
        $this->assertSame(3, $stats['total']);

        $this->assertStringContainsString(__('ui.verified_reviews'), $html);
        $this->assertStringContainsString((string) $stats['average'], $html);
        $this->assertStringContainsString('(3 '.__('ui.verified_reviews').')', $html);
        // El número legacy sucio (30) no debe aparecer donde antes se pintaba.
        $this->assertStringNotContainsString('(30 '.__('ui.verified_reviews').')', $html);
    }

    public function test_related_tours_section_hides_when_no_other_published_tour_exists(): void
    {
        $tour = $this->tour(['slug' => 'unico-tour-publicado']);

        $html = $this->get("/es/tours/detalle/{$tour->slug}")->assertOk()->getContent();

        // El texto también vive en un comentario CSS estático (siempre presente);
        // lo que prueba que la SECCIÓN no se pinta es que no aparece como
        // contenido de etiqueta real (">texto<").
        $this->assertStringNotContainsString('>'.__('ui.others_also_booked').'<', $html);
        // Los tours ficticios que antes rellenaban el fallback ya no existen en el código.
        $this->assertStringNotContainsString('Tour a Machu Picchu Full Day', $html);
        $this->assertStringNotContainsString('Montaña de 7 Colores Full Day', $html);
    }

    public function test_related_tours_rating_uses_real_reviewstats_not_dirty_column(): void
    {
        $tour = $this->tour(['slug' => 'principal-con-relacionados']);
        $related = $this->tour(['slug' => 'relacionado-sin-resenas', 'rating' => 4.8, 'reviews_count' => 0]);

        $html = $this->get("/es/tours/detalle/{$tour->slug}")->assertOk()->getContent();

        // El tour relacionado se lista, pero sin reseñas reales su fragmento de
        // rating (m-rec-rating, único productor de "★★★★★</span>4.8") no debe
        // imprimirse — antes salía siempre por el fallback ?? 4.8.
        $this->assertStringContainsString($related->title, $html);
        $this->assertStringNotContainsString('★★★★★</span>4.8', $html);
    }

    /**
     * Página de resultados de búsqueda (/buscar): mismo criterio. Detectado en
     * verificación manual: un tour sin reseñas reales no debe imprimir su
     * rating legacy sucio, y la página no debe devolver 500.
     */
    public function test_search_results_page_hides_dirty_legacy_rating(): void
    {
        $this->tour(['slug' => 'buscar-sin-resenas', 'title_es' => 'Tour Buscar Sin Reseñas', 'rating' => 4.8, 'reviews_count' => 0]);

        $response = $this->get('/es/buscar?q=Buscar');

        $response->assertOk();
        // '4.8' aparece como substring en paths SVG (ej. "4.875") ajenos al
        // rating; se afirma sobre el fragmento exacto que imprime el rating.
        $response->assertDontSee('font-semibold">4.8</span>', false);
    }

    public function test_search_results_page_shows_real_rating_when_published_reviews_exist(): void
    {
        $tour = $this->tour(['slug' => 'buscar-con-resenas', 'title_es' => 'Tour Buscar Con Reseñas', 'rating' => 4.8, 'reviews_count' => 0]);
        $this->review($tour, ['rating' => 5]);

        $response = $this->get('/es/buscar?q=Buscar');

        $response->assertOk();
        $response->assertSee('5.0', false);
    }
}
