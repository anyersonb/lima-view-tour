<?php

namespace Tests\Unit\Models;

use App\Models\Testimonial;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Tour::reviewStats() — agregados REALES (average/total/distribution) para
 * la sección "Opiniones de nuestros viajeros". Cubre: el cálculo con varias
 * calificaciones, el caso 0 reseñas (nunca inventar un promedio), que solo
 * las reseñas published() (is_active=true AND status=approved) entran al
 * cálculo, y que la caché se invalida sola en alta/edición/borrado.
 */
class TourReviewStatsTest extends TestCase
{
    use RefreshDatabase;

    private function tour(array $overrides = []): Tour
    {
        return Tour::factory()->create($overrides);
    }

    private function review(Tour $tour, array $overrides = []): Testimonial
    {
        return Testimonial::create(array_merge([
            'tour_id'   => $tour->id,
            'name'      => 'Viajero de prueba',
            'quote_es'  => 'Una reseña de prueba con más de diez caracteres.',
            'rating'    => 5,
            'source'    => 'Web',
            'is_active' => true,
            'status'    => 'approved',
        ], $overrides));
    }

    public function test_zero_published_reviews_returns_total_zero_and_null_average_never_a_fabricated_number(): void
    {
        $tour = $this->tour();

        $stats = $tour->reviewStats();

        $this->assertSame(0, $stats['total']);
        $this->assertNull($stats['average'], 'Con 0 reseñas el promedio NUNCA debe inventarse.');
        foreach ([5, 4, 3, 2, 1] as $stars) {
            $this->assertSame(0, $stats['distribution'][$stars]['count']);
            $this->assertSame(0, $stats['distribution'][$stars]['percent']);
        }
    }

    public function test_average_and_distribution_with_several_real_ratings(): void
    {
        $tour = $this->tour();

        // 3×5, 1×4, 1×3 → promedio real (3*5+4+3)/5 = 22/5 = 4.4
        $this->review($tour, ['rating' => 5]);
        $this->review($tour, ['rating' => 5]);
        $this->review($tour, ['rating' => 5]);
        $this->review($tour, ['rating' => 4]);
        $this->review($tour, ['rating' => 3]);

        $stats = $tour->reviewStats();

        $this->assertSame(5, $stats['total']);
        $this->assertSame(4.4, $stats['average']);
        $this->assertSame(3, $stats['distribution'][5]['count']);
        $this->assertSame(1, $stats['distribution'][4]['count']);
        $this->assertSame(1, $stats['distribution'][3]['count']);
        $this->assertSame(0, $stats['distribution'][2]['count']);
        $this->assertSame(0, $stats['distribution'][1]['count']);

        // 3/5 = 60%, 1/5 = 20%, 1/5 = 20% → suma exacta 100
        $this->assertSame(60, $stats['distribution'][5]['percent']);
        $this->assertSame(20, $stats['distribution'][4]['percent']);
        $this->assertSame(20, $stats['distribution'][3]['percent']);
    }

    public function test_percent_distribution_never_exceeds_100_even_with_awkward_division(): void
    {
        $tour = $this->tour();

        // 3 reseñas de 5 estrellas: 1/3 = 33.33...% cada una. floor() por
        // bucket da 33+33+33=99, nunca 100+ por redondeo hacia arriba.
        $this->review($tour, ['rating' => 5]);
        $this->review($tour, ['rating' => 4]);
        $this->review($tour, ['rating' => 3]);

        $stats = $tour->reviewStats();

        $sum = array_sum(array_column($stats['distribution'], 'percent'));

        $this->assertLessThanOrEqual(100, $sum);
        $this->assertSame(33, $stats['distribution'][5]['percent']);
        $this->assertSame(33, $stats['distribution'][4]['percent']);
        $this->assertSame(33, $stats['distribution'][3]['percent']);
    }

    public function test_pending_and_rejected_reviews_are_excluded_from_the_aggregate(): void
    {
        $tour = $this->tour();

        $this->review($tour, ['rating' => 5, 'status' => 'approved', 'is_active' => true]);
        $this->review($tour, ['rating' => 1, 'status' => 'pending', 'is_active' => false]);
        $this->review($tour, ['rating' => 1, 'status' => 'rejected', 'is_active' => false]);
        // is_active=false a mano (ocultada) aunque esté approved: tampoco cuenta.
        $this->review($tour, ['rating' => 1, 'status' => 'approved', 'is_active' => false]);

        $stats = $tour->reviewStats();

        $this->assertSame(1, $stats['total']);
        $this->assertSame(5.0, $stats['average']);
    }

    public function test_reviews_from_another_tour_never_leak_into_this_tours_stats(): void
    {
        $tourA = $this->tour();
        $tourB = $this->tour();

        $this->review($tourA, ['rating' => 5]);
        $this->review($tourB, ['rating' => 1]);

        $this->assertSame(5.0, $tourA->reviewStats()['average']);
        $this->assertSame(1.0, $tourB->reviewStats()['average']);
    }

    public function test_cache_is_invalidated_automatically_on_create_update_and_delete(): void
    {
        $tour = $this->tour();

        $this->assertSame(0, $tour->reviewStats()['total'], 'Precondición: cachea el estado "0 reseñas".');

        $review = $this->review($tour, ['rating' => 5]);
        $this->assertSame(1, $tour->reviewStats()['total'], 'Un alta debe invalidar la caché sola.');

        $review->update(['status' => 'rejected']);
        $this->assertSame(0, $tour->reviewStats()['total'], 'Una edición (ej. rechazar desde el panel) debe invalidar la caché sola.');

        $review->update(['status' => 'approved']);
        $this->assertSame(1, $tour->reviewStats()['total']);

        $review->delete();
        $this->assertSame(0, $tour->reviewStats()['total'], 'Un borrado debe invalidar la caché sola.');
    }

    public function test_average_rounds_to_one_decimal(): void
    {
        $tour = $this->tour();

        // (5+5+4)/3 = 4.666... → 4.7
        $this->review($tour, ['rating' => 5]);
        $this->review($tour, ['rating' => 5]);
        $this->review($tour, ['rating' => 4]);

        $this->assertSame(4.7, $tour->reviewStats()['average']);
    }
}
